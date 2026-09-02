<?php

namespace App\Http\Controllers;

use App\Models\ProjektHasPersonen;
use App\Models\ProjektHasTeilnehmerLuv;
use App\Models\ProjektLuvTemplate;
use App\Services\Documents\LuvPdfService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpWord\TemplateProcessor;

class ProjektHasTeilnehmerLuvController extends Controller
{
    public function __construct(private readonly LuvPdfService $pdfService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teilnehmer_id' => ['required', 'integer', 'exists:personens,id'],
            'von' => ['required', 'date'],
            'bis' => ['required', 'date', 'after_or_equal:von'],
            'typ' => ['required', Rule::in(ProjektLuvTemplate::TYPES)],
            'ausgangssituation' => ['nullable', 'string', 'max:30000'],
            'zielvereinbarung' => ['nullable', 'string', 'max:30000'],
            'qualifikationen' => ['nullable', 'string', 'max:30000'],
        ]);

        $participation = $this->participationFor($request, (int) $validated['teilnehmer_id']);
        $template = $participation->projekt?->activeLuvTemplateFor($validated['typ']);

        $luv = DB::transaction(function () use ($validated, $participation, $template, $request): ProjektHasTeilnehmerLuv {
            $version = ((int) ProjektHasTeilnehmerLuv::query()
                ->where('projekt_person_id', $participation->id)
                ->where('typ', $validated['typ'])
                ->lockForUpdate()
                ->max('version')) + 1;

            return ProjektHasTeilnehmerLuv::create([
                'projekt_person_id' => $participation->id,
                'template_id' => $template?->id,
                'typ' => $validated['typ'],
                'version' => $version,
                'status' => 'draft',
                'form_version' => $template?->form_version,
                'von' => Carbon::parse($validated['von'])->toDateString(),
                'bis' => Carbon::parse($validated['bis'])->toDateString(),
                'ausgangssituation' => $validated['ausgangssituation'] ?? null,
                'zielvereinbarung' => $validated['zielvereinbarung'] ?? null,
                'qualifikationen' => $validated['qualifikationen'] ?? null,
                'payload' => [
                    'luv_type' => $validated['typ'],
                    'title' => $validated['typ'].'-LuV',
                    'sections' => [
                        ['key' => 'ausgangssituation', 'heading' => 'Ausgangssituation', 'value' => $validated['ausgangssituation'] ?? ''],
                        ['key' => 'zielvereinbarung', 'heading' => 'Schritte zur Zielerreichung', 'value' => $validated['zielvereinbarung'] ?? ''],
                        ['key' => 'qualifikationen', 'heading' => 'Qualifikationen', 'value' => $validated['qualifikationen'] ?? ''],
                    ],
                    'warnings' => [],
                ],
                'created_by' => $request->user()->getKey(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'LuV wurde als prüfbarer Entwurf angelegt.',
            'luv' => $luv->fresh(['template']),
        ], 201);
    }

    public function update(Request $request, ?string $id = null): JsonResponse
    {
        $id ??= (string) $request->input('id');
        abort_unless(ctype_digit($id) && (int) $id > 0, 422, 'Eine gültige LuV-ID ist erforderlich.');
        $luv = $this->authorizedLuvQuery($request)->whereKey((int) $id)->firstOrFail();

        $validated = $request->validate([
            'von' => ['sometimes', 'date'],
            'bis' => ['sometimes', 'date', 'after_or_equal:von'],
            'ausgangssituation' => ['sometimes', 'nullable', 'string', 'max:30000'],
            'zielvereinbarung' => ['sometimes', 'nullable', 'string', 'max:30000'],
            'qualifikationen' => ['sometimes', 'nullable', 'string', 'max:30000'],
            'payload' => ['sometimes', 'array'],
            'payload.sections' => ['required_with:payload', 'array', 'min:1', 'max:20'],
            'payload.sections.*.key' => ['required', 'string', 'max:120'],
            'payload.sections.*.heading' => ['required', 'string', 'max:200'],
            'payload.sections.*.value' => ['nullable', 'string', 'max:30000'],
            'payload.sections.*.claims' => ['sometimes', 'array', 'max:100'],
            'payload.sections.*.claims.*.text' => ['required_with:payload.sections.*.claims', 'string', 'max:30000'],
            'payload.sections.*.claims.*.status' => ['required_with:payload.sections.*.claims', Rule::in(['supported', 'insufficient_data'])],
            'payload.sections.*.claims.*.source_ids' => ['sometimes', 'array', 'max:100'],
            'payload.sections.*.claims.*.source_ids.*' => ['string', 'max:255'],
            'payload.warnings' => ['sometimes', 'array', 'max:100'],
            'payload.warnings.*' => ['string', 'max:1000'],
            'status' => ['sometimes', Rule::in(['draft', 'reviewed', 'approved'])],
            'discussed_on' => ['nullable', 'date'],
            'consent_confirmed' => ['sometimes', 'boolean'],
        ]);

        abort_if($luv->status === 'approved', 409, 'Eine freigegebene LuV-Version ist unveränderbar. Bitte erzeugen Sie eine neue Version.');

        $nextStatus = $validated['status'] ?? $luv->status;
        $updates = collect($validated)->except('status')->all();

        if ($nextStatus === 'reviewed') {
            abort_unless(in_array($luv->status, ['draft', 'reviewed'], true), 409, 'Nur ein Entwurf kann fachlich geprüft werden.');
            $updates['reviewed_by'] = $request->user()->getKey();
            $updates['reviewed_at'] = now();
        }

        if ($nextStatus === 'approved') {
            $discussedOn = $validated['discussed_on'] ?? $luv->discussed_on?->toDateString();
            $consentConfirmed = array_key_exists('consent_confirmed', $validated)
                ? (bool) $validated['consent_confirmed']
                : (bool) $luv->consent_confirmed;
            if (! $discussedOn || ! $consentConfirmed) {
                return response()->json([
                    'message' => 'Für die Freigabe müssen Gesprächsdatum und Einwilligung bestätigt sein.',
                    'errors' => [
                        'discussed_on' => $discussedOn ? [] : ['Bitte das Gesprächsdatum eintragen.'],
                        'consent_confirmed' => $consentConfirmed ? [] : ['Bitte die dokumentierte Einwilligung bestätigen.'],
                    ],
                ], 422);
            }
            $updates['reviewed_by'] ??= $request->user()->getKey();
            $updates['reviewed_at'] ??= $luv->reviewed_at ?: now();
            $updates['approved_by'] = $request->user()->getKey();
            $updates['approved_at'] = now();
        }

        $updates['status'] = $nextStatus;
        $luv->update($updates);

        return response()->json([
            'message' => match ($nextStatus) {
                'reviewed' => 'Die LuV wurde als fachlich geprüft markiert.',
                'approved' => 'Die LuV wurde freigegeben und ist nun unveränderbar.',
                default => 'Der LuV-Entwurf wurde gespeichert.',
            },
            'luv' => $luv->fresh(['template', 'reviewer', 'approver']),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $luv = $this->authorizedLuvQuery($request)->whereKey($id)->firstOrFail();
        abort_if($luv->status === 'approved', 409, 'Eine freigegebene LuV darf nicht gelöscht werden.');
        $luv->delete();

        return response()->json(['message' => 'Der LuV-Entwurf wurde gelöscht.']);
    }

    public function export(Request $request, string $id)
    {
        $luv = $this->authorizedLuvQuery($request)
            ->with([
                'template',
                'projektHasTeilnehmer.zeitraume',
                'projektHasTeilnehmer.projekt',
                'projektHasTeilnehmer.teilnehmer.gruppen',
                'projektHasTeilnehmer.teilnehmer.anwesenheiten.tag',
                'projektHasTeilnehmer.meta',
                'projektHasTeilnehmer.teilnehmer.sozialedaten',
            ])->whereKey($id)->firstOrFail();

        $template = $luv->template ?: $luv->projektHasTeilnehmer->projekt?->activeLuvTemplateFor($luv->typ);
        if ($template?->template_format !== 'docx') {
            $pdf = $this->pdfService->render($luv);
            $filename = Str::slug($luv->typ.'-LuV-'.$luv->projektHasTeilnehmer->teilnehmer->vorname.'-'.$luv->projektHasTeilnehmer->teilnehmer->nachname).'.pdf';

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return $this->exportLegacyDocx($luv, $template);
    }

    private function exportLegacyDocx(ProjektHasTeilnehmerLuv $luv, ?ProjektLuvTemplate $template)
    {
        $participation = $luv->projektHasTeilnehmer;
        $participant = $participation->teilnehmer;
        $project = $participation->projekt;
        $period = $participation->zeitraume()->latest('id')->first();
        $templateFile = $template?->file_path && $template->template_format === 'docx' && Storage::disk('local')->exists($template->file_path)
            ? Storage::disk('local')->path($template->file_path)
            : storage_path('vorlage/projekte/word/LuV.docx');
        abort_unless(is_file($templateFile), 404, 'Die LuV-Vorlage wurde nicht gefunden.');

        $attendances = $participant->anwesenheiten
            ->filter(fn ($row) => (int) $row->gruppe?->projekt_id === (int) $project->id)
            ->filter(fn ($row) => $row->tag?->datum && Carbon::parse($row->tag->datum)->betweenIncluded($luv->von, $luv->bis));
        $counts = collect(['A', 'U', 'F', 'E', 'K'])->mapWithKeys(fn ($key) => [$key => $attendances->where('status.abkuerzung', $key)->count()]);
        $total = max(1, $attendances->count());

        $processor = new TemplateProcessor($templateFile);
        $values = [
            'typ' => $luv->typ,
            'zeitraumStart' => $luv->von?->format('d.m.Y'),
            'zeitraumBis' => $luv->bis?->format('d.m.Y'),
            'geburtsdatum' => $participant->geburtsdatum?->format('d.m.Y') ?? '',
            'kundennummer' => $participant->sozialedaten?->kundennummer ?? '',
            'vorname' => $participant->vorname,
            'nachname' => $participant->nachname,
            'vermittler' => trim(($participation->meta?->projektbegleiter?->vorname ?? '').' '.($participation->meta?->projektbegleiter?->nachname ?? '')),
            'betreuer' => trim(($participation->meta?->betreuer?->vorname ?? '').' '.($participation->meta?->betreuer?->nachname ?? '')),
            'projekt' => $project->name,
            'zuweisungVon' => $period?->starttermin?->format('d.m.Y') ?? '',
            'zuweisungBis' => $period?->endtermin?->format('d.m.Y') ?? '',
            'ausgangssituation' => $luv->ausgangssituation ?? '',
            'zielvereinbarung' => $luv->zielvereinbarung ?? '',
            'qualifikationen' => $luv->qualifikationen ?? '',
            'listeErstellteLuvs' => $participation->luv()->orderBy('created_at')->get()->map(fn ($entry) => '- '.$entry->typ.'-LuV vom '.$entry->created_at->format('d.m.Y'))->implode("\n"),
            'A' => $counts['A'], 'U' => $counts['U'], 'F' => $counts['F'], 'E' => $counts['E'], 'K' => $counts['K'],
            'PAU' => round((($counts['A'] + $counts['U']) / $total) * 100, 1),
            'PKE' => round((($counts['K'] + $counts['E']) / $total) * 100, 1),
            'PF' => round(($counts['F'] / $total) * 100, 1),
            'listeBereiche' => $participant->gruppen->where('projekt_id', $project->id)->pluck('bereich.name')->filter()->unique()->implode(', '),
        ];
        foreach ($values as $key => $value) {
            $processor->setValue($key, $value ?? '');
        }

        $directory = storage_path('app/temp');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $path = $directory.'/luv-'.Str::uuid().'.docx';
        $processor->saveAs($path);

        return response()->download($path, Str::slug($luv->typ.'-LuV-'.$participant->vorname.'-'.$participant->nachname).'.docx')->deleteFileAfterSend(true);
    }

    private function participationFor(Request $request, int $participantId): ProjektHasPersonen
    {
        $projectId = (int) $request->user()->current_team_id;
        abort_if($projectId < 1, 409, 'Bitte wählen Sie zuerst ein aktives Projekt aus.');

        return ProjektHasPersonen::query()
            ->where('projekt_id', $projectId)
            ->where('personen_id', $participantId)
            ->whereHas('teilnehmer', fn ($query) => $query->visibleForUser($request->user()))
            ->with('projekt')
            ->firstOrFail();
    }

    private function authorizedLuvQuery(Request $request): Builder
    {
        $projectId = (int) $request->user()->current_team_id;
        abort_if($projectId < 1, 409, 'Bitte wählen Sie zuerst ein aktives Projekt aus.');

        return ProjektHasTeilnehmerLuv::query()
            ->whereHas('projektHasTeilnehmer', fn ($query) => $query
                ->where('projekt_id', $projectId)
                ->whereHas('teilnehmer', fn ($participants) => $participants->visibleForUser($request->user())));
    }
}
