<?php

namespace App\Http\Controllers;

use App\Models\Projekt;
use App\Models\ProjektLuvTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;
use ZipArchive;

class ProjektLuvTemplateController extends Controller
{
    public function store(Request $request, Projekt $projekt): JsonResponse
    {
        if (is_string($request->input('sections'))) {
            $decoded = json_decode($request->input('sections'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['sections' => $decoded]);
            }
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'ai_instructions' => ['nullable', 'string', 'max:4000'],
            'sections' => ['required', 'array', 'min:1', 'max:6'],
            'sections.*.key' => ['required', 'string', 'regex:/^[a-z0-9_\-]+$/', 'max:60', 'distinct'],
            'sections.*.heading' => ['required', 'string', 'max:120'],
            'sections.*.instruction' => ['required', 'string', 'max:800'],
            'sections.*.required' => ['required', 'boolean'],
            'template' => ['nullable', 'file', 'max:10240', 'extensions:docx'],
        ], [
            'template.extensions' => 'Als LuV-Vorlage ist ausschließlich eine DOCX-Datei erlaubt.',
            'sections.max' => 'Die lokale KI unterstützt höchstens sechs LuV-Abschnitte.',
        ]);

        $newFilePath = null;
        $originalFilename = null;

        if ($request->hasFile('template')) {
            $uploadedFile = $request->file('template');
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

            if ($unsupported !== []) {
                throw ValidationException::withMessages([
                    'template' => 'Nicht unterstützte Platzhalter: ' . implode(', ', $unsupported),
                ]);
            }

            $originalFilename = $uploadedFile->getClientOriginalName();
            $newFilePath = $uploadedFile->storeAs(
                "project-luv-templates/{$projekt->id}",
                Str::uuid() . '.docx',
                'local'
            );
        }

        try {
            DB::transaction(function () use ($projekt, $validated, $newFilePath, $originalFilename): void {
                Projekt::query()->whereKey($projekt->id)->lockForUpdate()->firstOrFail();
                $current = ProjektLuvTemplate::query()
                    ->where('projekt_id', $projekt->id)
                    ->where('is_active', true)
                    ->first();
                $version = ((int) ProjektLuvTemplate::query()
                    ->where('projekt_id', $projekt->id)
                    ->max('version')) + 1;

                ProjektLuvTemplate::query()
                    ->where('projekt_id', $projekt->id)
                    ->update(['is_active' => false]);

                ProjektLuvTemplate::create([
                    'projekt_id' => $projekt->id,
                    'version' => $version,
                    'name' => $validated['name'],
                    'original_filename' => $originalFilename ?: $current?->original_filename,
                    'file_path' => $newFilePath ?: $current?->file_path,
                    'sections' => array_values($validated['sections']),
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
            $template->original_filename ?: "LuV-{$projekt->name}-v{$template->version}.docx"
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
                'name' => $template->name,
                'original_filename' => $template->original_filename,
                'has_file' => filled($template->file_path),
                'sections' => $template->sections,
                'ai_instructions' => $template->ai_instructions,
                'is_active' => $template->is_active,
                'created_at' => $template->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function assertSafeDocx(string $path): void
    {
        $archive = new ZipArchive();
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

            if (!isset($entries['[Content_Types].xml'], $entries['word/document.xml'])) {
                throw new \RuntimeException('missing DOCX structure');
            }
        } finally {
            $archive->close();
        }
    }
}
