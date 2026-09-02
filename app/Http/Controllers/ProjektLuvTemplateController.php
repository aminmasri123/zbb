<?php

namespace App\Http\Controllers;

use App\Models\Projekt;
use App\Models\ProjektLuvTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;
use ZipArchive;

class ProjektLuvTemplateController extends Controller
{
    public function store(Request $request, Projekt $projekt): JsonResponse
    {
        $request->merge([
            'luv_type' => $request->input('luv_type', 'Start'),
            'form_version' => $request->input('form_version', 'BA-BvB-2023'),
        ]);

        foreach (['sections', 'field_schema', 'source_settings', 'schedule_settings'] as $jsonField) {
            if (is_string($request->input($jsonField))) {
                $decoded = json_decode($request->input($jsonField), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request->merge([$jsonField => $decoded]);
                }
            }
        }

        $validated = $request->validate([
            'luv_type' => ['required', Rule::in(ProjektLuvTemplate::TYPES)],
            'name' => ['required', 'string', 'max:120'],
            'form_version' => ['required', 'string', 'max:60'],
            'ai_instructions' => ['nullable', 'string', 'max:4000'],
            'sections' => ['required', 'array', 'min:1', 'max:6'],
            'sections.*.key' => ['required', 'string', 'regex:/^[a-z0-9_\-]+$/', 'max:60', 'distinct'],
            'sections.*.heading' => ['required', 'string', 'max:120'],
            'sections.*.instruction' => ['required', 'string', 'max:800'],
            'sections.*.required' => ['required', 'boolean'],
            'field_schema' => ['nullable', 'array', 'max:120'],
            'field_schema.*.key' => ['required_with:field_schema', 'string', 'max:120'],
            'field_schema.*.label' => ['required_with:field_schema', 'string', 'max:160'],
            'field_schema.*.type' => ['required_with:field_schema', 'string', 'max:40'],
            'field_schema.*.required' => ['required_with:field_schema', 'boolean'],
            'field_schema.*.ai_writable' => ['required_with:field_schema', 'boolean'],
            'source_settings' => ['nullable', 'array'],
            'source_settings.*' => ['boolean'],
            'schedule_settings' => ['nullable', 'array'],
            'schedule_settings.enabled' => ['nullable', 'boolean'],
            'schedule_settings.trigger' => ['nullable', 'string', 'max:60'],
            'schedule_settings.offset_days' => ['nullable', 'integer', 'between:-365,365'],
            'template' => ['nullable', 'file', 'max:15360', 'extensions:docx,pdf'],
        ], [
            'template.extensions' => 'Als LuV-Vorlage ist ausschließlich eine DOCX- oder PDF-Datei erlaubt.',
            'sections.max' => 'Die lokale KI unterstützt höchstens sechs LuV-Abschnitte.',
        ]);

        $newFilePath = null;
        $originalFilename = null;
        $templateFormat = null;

        if ($request->hasFile('template')) {
            $uploadedFile = $request->file('template');
            $extension = strtolower($uploadedFile->getClientOriginalExtension());
            $templateFormat = $extension;
            $unsupported = [];

            if ($extension === 'docx') {
                try {
                    $this->assertSafeDocx($uploadedFile->getRealPath());
                    $processor = new TemplateProcessor($uploadedFile->getRealPath());
                    $unsupported = array_values(array_diff(
                        array_unique($processor->getVariables()),
                        ProjektLuvTemplate::SUPPORTED_PLACEHOLDERS
                    ));
                } catch (Throwable) {
                    throw ValidationException::withMessages([
                        'template' => 'Die Datei ist keine lesbare Word-DOCX-Vorlage.',
                    ]);
                }
            } else {
                $this->assertSafePdf($uploadedFile->getRealPath());
            }

            if ($unsupported !== []) {
                throw ValidationException::withMessages([
                    'template' => 'Nicht unterstützte Platzhalter: '.implode(', ', $unsupported),
                ]);
            }

            $originalFilename = $uploadedFile->getClientOriginalName();
            $newFilePath = $uploadedFile->storeAs(
                "project-luv-templates/{$projekt->id}",
                Str::uuid().".{$extension}",
                'local'
            );
        }

        try {
            DB::transaction(function () use ($projekt, $validated, $newFilePath, $originalFilename, $templateFormat): void {
                Projekt::query()->whereKey($projekt->id)->lockForUpdate()->firstOrFail();
                $type = $validated['luv_type'];
                $current = ProjektLuvTemplate::query()
                    ->where('projekt_id', $projekt->id)
                    ->where('luv_type', $type)
                    ->where('is_active', true)
                    ->first();
                $version = ((int) ProjektLuvTemplate::query()
                    ->where('projekt_id', $projekt->id)
                    ->where('luv_type', $type)
                    ->max('version')) + 1;

                ProjektLuvTemplate::query()
                    ->where('projekt_id', $projekt->id)
                    ->where('luv_type', $type)
                    ->update(['is_active' => false]);

                ProjektLuvTemplate::create([
                    'projekt_id' => $projekt->id,
                    'luv_type' => $type,
                    'version' => $version,
                    'name' => $validated['name'],
                    'form_version' => $validated['form_version'],
                    'original_filename' => $originalFilename ?: $current?->original_filename,
                    'template_format' => $templateFormat ?: $current?->template_format,
                    'file_path' => $newFilePath ?: $current?->file_path,
                    'sections' => array_values($validated['sections']),
                    'field_schema' => array_values($validated['field_schema'] ?? ProjektLuvTemplate::defaultFieldSchemaFor($type)),
                    'source_settings' => array_replace(ProjektLuvTemplate::DEFAULT_SOURCE_SETTINGS, $validated['source_settings'] ?? []),
                    'schedule_settings' => array_replace(ProjektLuvTemplate::DEFAULT_SCHEDULE_SETTINGS[$type], $validated['schedule_settings'] ?? []),
                    'ai_instructions' => filled($validated['ai_instructions'] ?? null)
                        ? trim($validated['ai_instructions'])
                        : null,
                    'is_active' => true,
                    'created_by' => auth()->id(),
                ]);
            });
        } catch (Throwable $exception) {
            if ($newFilePath) {
                Storage::disk('local')->delete($newFilePath);
            }
            throw $exception;
        }

        return response()->json([
            'message' => 'Neue LuV-Vorlagenversion wurde aktiviert.',
            'templates' => $this->templatesFor($projekt),
        ], 201);
    }

    public function activate(Projekt $projekt, ProjektLuvTemplate $template): JsonResponse
    {
        abort_unless((int) $template->projekt_id === (int) $projekt->id, 404);

        DB::transaction(function () use ($projekt, $template): void {
            Projekt::query()->whereKey($projekt->id)->lockForUpdate()->firstOrFail();
            ProjektLuvTemplate::query()
                ->where('projekt_id', $projekt->id)
                ->where('luv_type', $template->luv_type)
                ->update(['is_active' => false]);
            $template->update(['is_active' => true]);
        });

        return response()->json([
            'message' => "LuV-Vorlage Version {$template->version} wurde aktiviert.",
            'templates' => $this->templatesFor($projekt),
        ]);
    }

    public function download(Projekt $projekt, ProjektLuvTemplate $template)
    {
        abort_unless((int) $template->projekt_id === (int) $projekt->id, 404);
        abort_unless($template->file_path && Storage::disk('local')->exists($template->file_path), 404);

        return Storage::disk('local')->download(
            $template->file_path,
            $template->original_filename ?: "LuV-{$projekt->name}-v{$template->version}.".($template->template_format ?: 'docx')
        );
    }

    private function templatesFor(Projekt $projekt): array
    {
        return $projekt->luvTemplates()
            ->latest('version')
            ->get()
            ->map(fn (ProjektLuvTemplate $template) => [
                'id' => $template->id,
                'version' => $template->version,
                'luv_type' => $template->luv_type,
                'name' => $template->name,
                'form_version' => $template->form_version,
                'original_filename' => $template->original_filename,
                'template_format' => $template->template_format,
                'has_file' => filled($template->file_path),
                'sections' => $template->sections,
                'field_schema' => $template->field_schema,
                'source_settings' => $template->source_settings,
                'schedule_settings' => $template->schedule_settings,
                'ai_instructions' => $template->ai_instructions,
                'is_active' => $template->is_active,
                'created_at' => $template->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function assertSafeDocx(string $path): void
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new \RuntimeException('invalid DOCX archive');
        }

        try {
            $totalUncompressedBytes = 0;
            $entries = [];
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index);
                $name = (string) ($stat['name'] ?? '');
                if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\')) {
                    throw new \RuntimeException('unsafe DOCX entry');
                }

                $totalUncompressedBytes += (int) ($stat['size'] ?? 0);
                if ($totalUncompressedBytes > 50 * 1024 * 1024) {
                    throw new \RuntimeException('DOCX expands beyond safety limit');
                }
                $entries[$name] = true;
            }

            if (! isset($entries['[Content_Types].xml'], $entries['word/document.xml'])) {
                throw new \RuntimeException('missing DOCX structure');
            }
        } finally {
            $archive->close();
        }
    }

    private function assertSafePdf(string $path): void
    {
        $handle = fopen($path, 'rb');
        $signature = $handle ? fread($handle, 5) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }

        if ($signature !== '%PDF-') {
            throw ValidationException::withMessages([
                'template' => 'Die Datei ist keine lesbare PDF-Vorlage.',
            ]);
        }
    }
}
