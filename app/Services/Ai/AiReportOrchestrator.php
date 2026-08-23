<?php

namespace App\Services\Ai;

use App\Models\Personen;
use App\Models\User;
use App\Services\Ai\Exceptions\AgentUnavailableException;
use App\Services\Ai\Tools\GetAttendanceSummaryTool;
use App\Services\Ai\Tools\GetDocumentationEntriesTool;
use App\Services\Ai\Tools\GetParticipantIdentitySummaryTool;
use App\Services\Ai\Tools\GetParticipantLuvDataTool;
use App\Services\Ai\Tools\GetProjectReportRulesTool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

final class AiReportOrchestrator
{
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
        $context = $this->createDraftContext($user, $participantId, $fromDate, $untilDate);

        return $this->runDraft($user, $context, $reportType, $fromDate, $untilDate, $userRequest);
    }

    public function createDraftContext(User $user, int $participantId, string $fromDate, string $untilDate): AiRunContext
    {
        $projectId = (int) $user->current_team_id;
        if ($projectId < 1) {
            throw new AuthorizationException('Fuer den KI-Lauf ist kein aktives Projekt autorisiert.');
        }

        $allowedTools = [
            GetProjectReportRulesTool::NAME,
            GetParticipantIdentitySummaryTool::NAME,
            GetParticipantLuvDataTool::NAME,
            GetAttendanceSummaryTool::NAME,
            GetDocumentationEntriesTool::NAME,
        ];
        $context = new AiRunContext((int) $user->getKey(), $projectId, $allowedTools, $participantId, $fromDate, $untilDate);

        // This authorization must happen before the model is contacted. A model
        // returning a final response immediately must never bypass Laravel.
        $this->authorizer->authorize($user, $context, GetProjectReportRulesTool::PERMISSION);
        $this->authorizeParticipant($user, $projectId, $participantId);

        return $context;
    }

    /**
     * @return array{run_id: string, report: array<string, mixed>}
     */
    private function runDraft(
        User $user,
        AiRunContext $context,
        string $reportType,
        string $fromDate,
        string $untilDate,
        string $userRequest,
    ): array {
        $projectId = $context->projectId;
        $participantId = $context->participantId;
        if ($participantId === null) {
            throw new AuthorizationException('Der Teilnehmer ist fuer diesen KI-Lauf nicht autorisiert.');
        }

        $runId = (string) Str::uuid();
        $toolResults = [];
        foreach ($context->allowedTools as $toolName) {
            $toolResults[] = [
                'role' => 'tool',
                'tool_name' => $toolName,
                'content' => $this->tools->execute($user, $context, $toolName),
            ];
        }

        $response = $this->agent->turn(new AgentTurnPayload(
            runId: $runId,
            projectId: $projectId,
            participantId: $participantId,
            reportType: $reportType,
            fromDate: $fromDate,
            untilDate: $untilDate,
            userRequest: $userRequest,
            allowedTools: $context->allowedTools,
            toolResults: $toolResults,
        ));

        if ($response['kind'] !== 'final') {
            throw new AgentUnavailableException('Der KI-Agent forderte trotz vollstaendiger Daten weitere Tools an.');
        }

        return ['run_id' => $runId, 'report' => $response['report']];
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
