<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAiReportJob;
use App\Models\AiReportRun;
use App\Services\Ai\AiReportOrchestrator;
use App\Services\Ai\Exceptions\AgentUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'from_date' => ['required', 'date_format:Y-m-d'],
            'until_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'request' => ['required', 'string', 'min:1', 'max:4000'],
        ]);

        try {
            $context = $this->orchestrator->createDraftContext(
                $request->user(),
                (int) $data['participant_id'],
                $data['from_date'],
                $data['until_date'],
            );

            $reportRun = AiReportRun::create([
                'run_uuid' => (string) Str::uuid(),
                'user_id' => $request->user()->getKey(),
                'project_id' => $context->projectId,
                'participant_id' => (int) $data['participant_id'],
                'report_type' => $data['report_type'],
                'from_date' => $data['from_date'],
                'until_date' => $data['until_date'],
                'request' => $data['request'],
            ]);

            $queueConnection = env('AI_REPORT_QUEUE_CONNECTION', config('queue.default', 'database'));
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
        $elapsedSeconds = $runModel->started_at
            ? now()->diffInSeconds($runModel->started_at)
            : now()->diffInSeconds($runModel->created_at);

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
            'created_at' => $runModel->created_at->toIso8601String(),
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
}
