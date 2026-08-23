<?php

namespace App\Services\Ai;

use App\Models\Personen;
use App\Models\User;
use App\Services\Ai\Exceptions\AgentUnavailableException;
use App\Services\Ai\Tools\GetProjectReportRulesTool;
use App\Services\Ai\Tools\GetParticipantIdentitySummaryTool;
use App\Services\Ai\Tools\GetParticipantLuvDataTool;
use App\Services\Ai\Tools\GetAttendanceSummaryTool;
use App\Services\Ai\Tools\GetDocumentationEntriesTool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

final class AiReportOrchestrator
{
    private const MAX_TURNS = 6;

    public function __construct(
        private readonly AgentClient $agent,
        private readonly AiToolRegistry $tools,
        private readonly AiProjectAuthorizer $authorizer,
    ) {}

    /**
     * @return array{run_id: string, report: array<string, mixed>}
     */
    public function draft(
        User $user,
        int $participantId,
        string $reportType,
        string $fromDate,
        string $untilDate,
        string $userRequest,
    ): array {
        $projectId = (int) $user->current_team_id;
        if ($projectId < 1) {
            throw new AuthorizationException('Fuer den KI-Lauf ist kein aktives Projekt autorisiert.');
        }

        $allowedTools = [GetProjectReportRulesTool::NAME,GetParticipantIdentitySummaryTool::NAME,GetParticipantLuvDataTool::NAME,GetAttendanceSummaryTool::NAME,GetDocumentationEntriesTool::NAME];
        $context = new AiRunContext((int) $user->getKey(), $projectId, $allowedTools, $participantId, $fromDate, $untilDate);

        // This authorization must happen before the model is contacted. A model
        // returning a final response immediately must never bypass Laravel.
        $this->authorizer->authorize($user, $context, GetProjectReportRulesTool::PERMISSION);
        $this->authorizeParticipant($user, $projectId, $participantId);

        $runId = (string) Str::uuid();
        $toolResults = [];
        $consumedCallIds = [];

        for ($turn = 0; $turn < self::MAX_TURNS; $turn++) {
            $response = $this->agent->turn(new AgentTurnPayload(
                runId: $runId,
                projectId: $projectId,
                participantId: $participantId,
                reportType: $reportType,
                fromDate: $fromDate,
                untilDate: $untilDate,
                userRequest: $userRequest,
                allowedTools: $allowedTools,
                toolResults: $toolResults,
            ));

            if ($response['kind'] === 'final') {
                return ['run_id' => $runId, 'report' => $response['report']];
            }

            foreach ($response['calls'] as $call) {
                if (isset($consumedCallIds[$call['call_id']])) {
                    throw new AgentUnavailableException('Der KI-Agent wiederholte eine Tool-Call-ID.');
                }

                $consumedCallIds[$call['call_id']] = true;
                $toolResults[] = [
                    'role' => 'tool',
                    'tool_name' => $call['name'],
                    'content' => $this->tools->execute(
                        $user,
                        $context,
                        $call['name'],
                        $call['arguments'],
                    ),
                ];
            }
        }

        throw new AgentUnavailableException('Der KI-Agent ueberschritt die maximale Anzahl von Arbeitsschritten.');
    }

    private function authorizeParticipant(User $user, int $projectId, int $participantId): void
    {
        $visible = Personen::query()
            ->teilnehmer()
            ->visibleForUser($user)
            ->whereKey($participantId)
            ->whereHas('projekte', fn ($query) => $query->where('projekts.id', $projectId))
            ->exists();

        if (! $visible) {
            throw new AuthorizationException('Der Teilnehmer ist fuer diesen KI-Lauf nicht autorisiert.');
        }
    }
}
