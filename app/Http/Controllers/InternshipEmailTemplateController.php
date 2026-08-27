<?php

namespace App\Http\Controllers;

use App\Models\InternshipEmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class InternshipEmailTemplateController extends Controller
{
    public function index()
    {
        return Inertia::render('Einstellung/InternshipEmailTemplates/Index', [
            'templates' => InternshipEmailTemplate::query()
                ->orderByRaw("case `key` when 'initial' then 1 when 'reminder_1' then 2 else 3 end")
                ->get()
                ->map(fn (InternshipEmailTemplate $template) => $this->payload($template)),
            'placeholders' => [
                '{{teilnehmer_name}}' => 'Vor- und Nachname des Teilnehmers',
                '{{teilnehmer_vorname}}' => 'Vorname des Teilnehmers',
                '{{teilnehmer_nachname}}' => 'Nachname des Teilnehmers',
                '{{betrieb}}' => 'Name des Praktikumsbetriebs',
                '{{ansprechpartner}}' => 'Ansprechperson im Betrieb',
                '{{startdatum}}' => 'Beginn des Praktikums',
                '{{enddatum}}' => 'Ende des Praktikums',
                '{{absender_name}}' => 'Name des eingeloggten Benutzers',
                '{{absender_email}}' => 'E-Mail-Adresse des eingeloggten Benutzers',
            ],
        ]);
    }

    public function update(Request $request, string $templateKey)
    {
        abort_unless(array_key_exists($templateKey, InternshipEmailTemplate::LABELS), 404);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'remove_attachment' => ['nullable', 'boolean'],
            'attachment' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
            ],
        ], [
            'attachment.max' => 'Der Anhang darf höchstens 10 MB groß sein.',
            'attachment.mimes' => 'Erlaubt sind PDF-, Word-, Excel- und Bilddateien.',
        ]);

        $template = InternshipEmailTemplate::query()->where('key', $templateKey)->firstOrFail();
        $oldPath = $template->attachment_path;
        $newPath = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $extension = mb_strtolower($file->extension() ?: $file->getClientOriginalExtension());
            $newPath = $file->storeAs(
                "internship-email-templates/{$templateKey}",
                Str::uuid().($extension !== '' ? ".{$extension}" : ''),
                'local'
            );
            $validated['attachment_path'] = $newPath;
            $validated['attachment_original_name'] = $file->getClientOriginalName();
            $validated['attachment_mime_type'] = $file->getMimeType();
            $validated['attachment_size'] = $file->getSize();
        } elseif ($request->boolean('remove_attachment')) {
            $validated['attachment_path'] = null;
            $validated['attachment_original_name'] = null;
            $validated['attachment_mime_type'] = null;
            $validated['attachment_size'] = null;
        }

        unset($validated['attachment'], $validated['remove_attachment']);
        $template->update([
            ...$validated,
            'updated_by_user_id' => $request->user()->id,
        ]);

        if ($oldPath && $oldPath !== $newPath && ($newPath || $request->boolean('remove_attachment'))) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('success', InternshipEmailTemplate::LABELS[$templateKey].' wurde gespeichert.');
    }

    public function download(InternshipEmailTemplate $template)
    {
        abort_unless($template->attachment_path && Storage::disk('local')->exists($template->attachment_path), 404);

        return Storage::disk('local')->download(
            $template->attachment_path,
            $template->attachment_original_name ?: 'Anhang'
        );
    }

    private function payload(InternshipEmailTemplate $template): array
    {
        return [
            'id' => $template->id,
            'key' => $template->key,
            'label' => InternshipEmailTemplate::LABELS[$template->key] ?? $template->key,
            'subject' => $template->subject,
            'body' => $template->body,
            'attachment_original_name' => $template->attachment_original_name,
            'attachment_size' => $template->attachment_size,
            'attachment_download_url' => $template->attachment_path
                ? route('internship-email-templates.attachment.download', $template)
                : null,
        ];
    }
}
