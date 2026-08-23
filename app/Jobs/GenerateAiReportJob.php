<?php

namespace App\Jobs;

use App\Models\AiReportRun;
use App\Models\User;
use App\Services\Ai\AiReportOrchestrator;
use App\Services\Ai\Exceptions\AgentUnavailableException as AgentUnavailable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateAiReportJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public string $runUuid;

    public function __construct(string $runUuid)
    {
        $this->runUuid = $runUuid;
    }

    public function handle(AiReportOrchestrator $orchestrator): void
    {
        $run = AiReportRun::query()->where('run_uuid', $this->runUuid)->first();
        if ($run === null) {
            return;
        }

        if (! in_array($run->status, ['queued', 'running'], true)) {
            return;
        }

        $user = User::find($run->user_id);
        if ($user === null) {
            $run->update([
                'status' => 'failed',
                'error_code' => 'missing_user',
                'error_message' => 'Der KI-Lauf hat keine gültige Nutzerzuordnung.',
                'completed_at' => now(),
            ]);

            return;
        }

        $run->forceFill([
            'status' => 'running',
            'started_at' => now(),
            'progress_percent' => 10,
        ])->save();

        // The project was authorized when the run was created. Use it only on
        // this in-memory worker model; never overwrite the user's active
        // project in the database while they continue working in the UI.
        $user->setAttribute('current_team_id', $run->project_id);

        try {
            $draft = $orchestrator->draft(
                $user,
                (int) $run->participant_id,
                (string) $run->report_type,
                $run->from_date->toDateString(),
                $run->until_date->toDateString(),
                (string) $run->request,
            );

            $run->update([
                'status' => 'completed',
                'progress_percent' => 100,
                'report' => $draft,
                'completed_at' => now(),
                'duration_seconds' => $this->durationSeconds($run),
                'error_code' => null,
                'error_message' => null,
            ]);
        } catch (AuthorizationException) {
            $run->update([
                'status' => 'failed',
                'progress_percent' => 100,
                'completed_at' => now(),
                'duration_seconds' => $this->durationSeconds($run),
                'error_code' => 'authorization_error',
                'error_message' => 'Nicht berechtigt, diesen Teilnehmer zu verarbeiten.',
            ]);
        } catch (AgentUnavailable) {
            $run->update([
                'status' => 'failed',
                'progress_percent' => 100,
                'completed_at' => now(),
                'duration_seconds' => $this->durationSeconds($run),
                'error_code' => 'agent_unavailable',
                'error_message' => 'Der KI-Dienst war nicht erreichbar oder lieferte eine ungültige Antwort.',
            ]);
        } catch (Throwable $exception) {
            Log::warning('AI report job failed unexpectedly', [
                'run_uuid' => $this->runUuid,
                'error' => $exception->getMessage(),
            ]);
            $run->update([
                'status' => 'failed',
                'progress_percent' => 100,
                'completed_at' => now(),
                'duration_seconds' => $this->durationSeconds($run),
                'error_code' => 'internal_error',
                'error_message' => 'Der KI-Dienst konnte nicht verarbeitet werden.',
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $run = AiReportRun::query()->where('run_uuid', $this->runUuid)->first();
        if ($run === null || $run->status === 'completed') {
            return;
        }

        $run->update([
            'status' => 'failed',
            'progress_percent' => 100,
            'completed_at' => now(),
            'duration_seconds' => $this->durationSeconds($run),
            'error_code' => 'worker_failed',
            'error_message' => 'Der KI-Worker ist unerwartet beendet.',
        ]);
    }

    private function durationSeconds(AiReportRun $run): ?int
    {
        return $run->started_at
            ? max(0, (int) floor($run->started_at->diffInSeconds(now(), true)))
            : null;
    }
}
