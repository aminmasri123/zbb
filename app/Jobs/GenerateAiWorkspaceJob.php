<?php

namespace App\Jobs;

use App\Models\AiWorkspaceRun;
use App\Services\Ai\AgentClient;
use App\Services\Ai\Exceptions\AgentUnavailableException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateAiWorkspaceJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public string $runUuid) {}

    public function handle(AgentClient $agent): void
    {
        $run = AiWorkspaceRun::query()->where('run_uuid', $this->runUuid)->first();
        if (!$run || !in_array($run->status, ['queued', 'running'], true)) {
            return;
        }

        $payload = $run->request_payload;
        if (!is_array($payload)) {
            $this->markFailed($run, 'missing_payload', 'Die verschlüsselte KI-Arbeitsgrundlage fehlt.');
            return;
        }

        $run->forceFill([
            'status' => 'running',
            'progress_percent' => 15,
            'started_at' => now(),
        ])->save();

        try {
            $result = $agent->generate($payload);
            $run->update([
                'title' => $result['title'],
                'content' => $result['content'],
                'citations' => $result['citations'],
                'warnings' => $result['warnings'],
                'status' => 'completed',
                'progress_percent' => 100,
                'request_payload' => null,
                'error_code' => null,
                'error_message' => null,
                'duration_seconds' => $this->durationSeconds($run),
                'completed_at' => now(),
            ]);
        } catch (AgentUnavailableException) {
            $this->markFailed($run, 'agent_unavailable', 'Der lokale KI-Dienst war nicht erreichbar oder lieferte keine gültige Antwort.');
        } catch (Throwable $exception) {
            Log::warning('AI workspace job failed unexpectedly', [
                'run_uuid' => $this->runUuid,
                'error' => $exception->getMessage(),
            ]);
            $this->markFailed($run, 'internal_error', 'Der KI-Arbeitsbereich konnte die Anfrage nicht verarbeiten.');
        }
    }

    public function failed(Throwable $exception): void
    {
        $run = AiWorkspaceRun::query()->where('run_uuid', $this->runUuid)->first();
        if ($run && $run->status !== 'completed') {
            $this->markFailed($run, 'worker_failed', 'Der KI-Hintergrunddienst wurde unerwartet beendet.');
        }
    }

    private function markFailed(AiWorkspaceRun $run, string $code, string $message): void
    {
        $run->update([
            'status' => 'failed',
            'progress_percent' => 100,
            'request_payload' => null,
            'error_code' => $code,
            'error_message' => $message,
            'duration_seconds' => $this->durationSeconds($run),
            'completed_at' => now(),
        ]);
    }

    private function durationSeconds(AiWorkspaceRun $run): ?int
    {
        return $run->started_at
            ? max(0, (int) floor($run->started_at->diffInSeconds(now(), true)))
            : null;
    }
}
