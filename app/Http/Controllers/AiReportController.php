<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAiReportJob;
use App\Models\AiReportRun;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\ProjektHasTeilnehmerLuv;
use App\Models\ProjektLuvTemplate;
use App\Services\Ai\AiReportOrchestrator;
use App\Services\Ai\Exceptions\AgentUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AiReportController extends Controller
{
    public function __construct(private readonly AiReportOrchestrator $orchestrator) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'participant_id' => ['required', 'integer', 'min:1'],
            'report_type' => ['required', Rule::in(['luv', 'interim', 'final'])],
            'luv_type' => ['nullable', Rule::in(ProjektLuvTemplate::TYPES)],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'until_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'request' => ['required', 'string', 'min:1', 'max:4000'],
        ]);

        try {
            $luvType = $data['luv_type'] ?? ProjektLuvTemplate::fromReportType($data['report_type']);
            $expectedReportType = ProjektLuvTemplate::toReportType($luvType);
            if ($expectedReportType !== $data['report_type']) {
                return response()->json([
                    'message' => 'LuV-Typ und KI-Berichtstyp passen nicht zusammen.',
                    'errors' => ['luv_type' => ['Bitte wählen Sie einen passenden LuV-Typ.']],
                ], 422);
            }

            $context = $this->orchestrator->createDraftContext(
                $request->user(),
                (int) $data['participant_id'],
                $data['from_date'],
                $data['until_date'],
                $data['report_type'],
            );
            $project = Projekt::find($context->projectId);
            $template = $project?->activeLuvTemplateFor($luvType);

            $reportRun = AiReportRun::create([
                'run_uuid' => (string) Str::uuid(),
                'user_id' => $request->user()->getKey(),
                'project_id' => $context->projectId,
                'participant_id' => (int) $data['participant_id'],
                'report_type' => $data['report_type'],
                'luv_type' => $luvType,
                'template_id' => $template?->id,
                'from_date' => $data['from_date'],
                'until_date' => $data['until_date'],
                'request' => $data['request'],
            ]);

            $queueConnection = (string) config('queue.ai_report_connection', config('queue.default', 'sync'));
            GenerateAiReportJob::dispatch($reportRun->run_uuid)->onConnection($queueConnection);
        } catch (AgentUnavailableException) {
            return response()->json([
                'message' => 'Der interne KI-Dienst ist derzeit nicht verfuegbar.',
            ], 503, [
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return response()->json([
            'status' => 'queued',
            'run_id' => $reportRun->run_uuid,
            'status_url' => route('ai.reports.status', ['run' => $reportRun->run_uuid]),
        ], 202, [
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function show(Request $request, string $run): JsonResponse
    {
        $runModel = AiReportRun::query()
            ->where('run_uuid', $run)
            ->where('user_id', $request->user()->getKey())
            ->first();

        if (! $runModel) {
            abort(404);
        }

        $status = (string) $runModel->status;
        $elapsedFrom = $runModel->started_at ?? $runModel->created_at;
        $elapsedSeconds = max(0, (int) floor($elapsedFrom->diffInSeconds(now(), true)));

        $estimatedRemainingSeconds = null;
        if ($status !== 'completed' && $status !== 'failed' && $runModel->started_at !== null) {
            $averageSeconds = AiReportRun::query()
                ->where('user_id', $request->user()->getKey())
                ->where('status', 'completed')
                ->where('report_type', $runModel->report_type)
                ->whereNotNull('duration_seconds')
                ->where('created_at', '>=', now()->subDays(14))
                ->avg('duration_seconds');

            if (is_numeric($averageSeconds)) {
                $estimatedRemainingSeconds = (int) max(0, round((float) $averageSeconds - $elapsedSeconds));
            }
        }

        $payload = [
            'run_id' => $runModel->run_uuid,
            'status' => $status,
            'status_label' => match ($status) {
                'queued' => 'Warte auf KI-Verarbeitung',
                'running' => 'LUV wird erstellt',
                'completed' => 'Fertig',
                'failed' => 'Fehlgeschlagen',
                default => 'Unbekannt',
            },
            'elapsed_seconds' => $elapsedSeconds,
            'estimated_remaining_seconds' => $estimatedRemainingSeconds,
            'progress_percent' => (int) $runModel->progress_percent,
            'report_type' => (string) $runModel->report_type,
            'luv_type' => (string) ($runModel->luv_type ?: ProjektLuvTemplate::fromReportType($runModel->report_type)),
            'created_at' => $runModel->created_at->toIso8601String(),
            'queue_warning' => $status === 'queued' && $elapsedSeconds >= 30
                ? 'Der Auftrag wartet noch auf den Hintergrunddienst. Bitte den Queue-Worker auf dem Webserver prüfen.'
                : null,
        ];

        if ($runModel->status === 'completed') {
            $payload['report'] = $runModel->report['report'] ?? $runModel->report;
        } elseif ($runModel->status === 'failed') {
            $payload['error_code'] = (string) $runModel->error_code;
            $payload['error_message'] = 'Der KI-Dienst konnte die Anfrage nicht verarbeiten.';
        }

        return response()->json($payload, 200, [
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function adopt(Request $request, string $run): JsonResponse
    {
        $runModel = AiReportRun::query()
            ->where('run_uuid', $run)
            ->where('user_id', $request->user()->getKey())
            ->where('status', 'completed')
            ->firstOrFail();

        abort_unless((int) $request->user()->current_team_id === (int) $runModel->project_id, 409, 'Bitte wählen Sie das Projekt des KI-Entwurfs als aktives Projekt aus.');

        $participation = ProjektHasPersonen::query()
            ->where('projekt_id', $runModel->project_id)
            ->where('personen_id', $runModel->participant_id)
            ->whereHas('teilnehmer', fn ($query) => $query->visibleForUser($request->user()))
            ->firstOrFail();

        $report = data_get($runModel->report, 'report', $runModel->report);
        $sections = collect($report['sections'] ?? [])->map(function (array $section): array {
            $claims = collect($section['claims'] ?? [])->map(fn (array $claim) => [
                'claim_id' => $claim['claim_id'] ?? (string) Str::uuid(),
                'text' => trim((string) ($claim['text'] ?? '')),
                'status' => $claim['status'] ?? 'insufficient_data',
                'source_ids' => array_values($claim['source_ids'] ?? []),
            ])->filter(fn (array $claim) => $claim['text'] !== '')->values();

            return [
                'key' => Str::slug((string) ($section['heading'] ?? 'Abschnitt'), '_'),
                'heading' => (string) ($section['heading'] ?? 'Abschnitt'),
                'value' => $claims->pluck('text')->implode("\n\n"),
                'claims' => $claims->all(),
            ];
        })->filter(fn (array $section) => $section['value'] !== '')->values();

        if ($sections->isEmpty()) {
            return response()->json(['message' => 'Der KI-Entwurf enthält keine übernehmbaren Inhalte.'], 422);
        }

        $findText = function (array $needles) use ($sections): string {
            $match = $sections->first(function (array $section) use ($needles): bool {
                $haystack = mb_strtolower($section['key'].' '.$section['heading']);

                return collect($needles)->contains(fn (string $needle) => str_contains($haystack, $needle));
            });

            return (string) ($match['value'] ?? '');
        };

        $type = $runModel->luv_type ?: ProjektLuvTemplate::fromReportType($runModel->report_type);
        $template = $runModel->template ?: Projekt::find($runModel->project_id)?->activeLuvTemplateFor($type);
        $sourceIds = $sections->flatMap(fn (array $section) => collect($section['claims'])->flatMap(fn (array $claim) => $claim['source_ids']))
            ->unique()->values()->all();

        $luv = DB::transaction(function () use ($participation, $runModel, $report, $sections, $findText, $type, $template, $sourceIds, $request): ProjektHasTeilnehmerLuv {
            $existing = ProjektHasTeilnehmerLuv::query()
                ->where('ai_report_run_id', $runModel->id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $version = ((int) ProjektHasTeilnehmerLuv::query()
                ->where('projekt_person_id', $participation->id)
                ->where('typ', $type)
                ->max('version')) + 1;

            return ProjektHasTeilnehmerLuv::create([
                'projekt_person_id' => $participation->id,
                'template_id' => $template?->id,
                'ai_report_run_id' => $runModel->id,
                'typ' => $type,
                'version' => $version,
                'status' => 'draft',
                'form_version' => $template?->form_version,
                'von' => $runModel->from_date,
                'bis' => $runModel->until_date,
                'ausgangssituation' => $findText(['ausgang', 'entwicklung', 'ergebnis']),
                'zielvereinbarung' => $findText(['ziel', 'einglieder']),
                'qualifikationen' => $findText(['qualifikation', 'praktika', 'förder']),
                'payload' => [
                    'report_type' => $runModel->report_type,
                    'luv_type' => $type,
                    'title' => $report['title'] ?? "{$type}-LuV",
                    'sections' => $sections->all(),
                    'warnings' => array_values($report['warnings'] ?? []),
                ],
                'source_snapshot' => [
                    'ai_run_uuid' => $runModel->run_uuid,
                    'source_ids' => $sourceIds,
                    'generated_at' => $runModel->completed_at?->toIso8601String(),
                ],
                'created_by' => $request->user()->getKey(),
            ]);
        });

        return response()->json([
            'message' => 'Der KI-Entwurf wurde als prüfbarer LuV-Entwurf übernommen.',
            'luv' => $luv->fresh(['template']),
        ], 201);
    }
}
