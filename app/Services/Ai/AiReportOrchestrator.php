<?php

namespace App\Services\Ai;

use App\Models\Personen;
use App\Models\ProjektLuvTemplate;
use App\Models\User;
use App\Services\Ai\Exceptions\AgentUnavailableException;
use App\Services\Ai\Tools\GetAttendanceSummaryTool;
use App\Services\Ai\Tools\GetDocumentationEntriesTool;
use App\Services\Ai\Tools\GetParticipantDevelopmentDataTool;
use App\Services\Ai\Tools\GetParticipantIdentitySummaryTool;
use App\Services\Ai\Tools\GetParticipantLuvDataTool;
use App\Services\Ai\Tools\GetParticipantPotentialAnalysisSupportNeedsTool;
use App\Services\Ai\Tools\GetProjectReportRulesTool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

final class AiReportOrchestrator
{
    public function __construct(
        private readonly AgentClient $agent,
        private readonly AiToolRegistry $tools,
        private readonly AiProjectAuthorizer $authorizer,
        private readonly ApprovedPaSupportNeedMerger $approvedPaSupportNeeds,
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
        $context = $this->createDraftContext($user, $participantId, $fromDate, $untilDate, $reportType);

        return $this->runDraft($user, $context, $reportType, $fromDate, $untilDate, $userRequest);
    }

    public function createDraftContext(User $user, int $participantId, string $fromDate, string $untilDate, string $reportType = 'luv'): AiRunContext
    {
        $projectId = (int) $user->current_team_id;
        if ($projectId < 1) {
            throw new AuthorizationException('Fuer den KI-Lauf ist kein aktives Projekt autorisiert.');
        }

        $allTools = [
            GetProjectReportRulesTool::NAME,
            GetParticipantIdentitySummaryTool::NAME,
            GetParticipantLuvDataTool::NAME,
            GetAttendanceSummaryTool::NAME,
            GetDocumentationEntriesTool::NAME,
            GetParticipantDevelopmentDataTool::NAME,
            GetParticipantPotentialAnalysisSupportNeedsTool::NAME,
        ];
        $context = new AiRunContext((int) $user->getKey(), $projectId, $allTools, $participantId, $fromDate, $untilDate, $reportType);

        // This authorization must happen before the model is contacted. A model
        // returning a final response immediately must never bypass Laravel.
        $project = $this->authorizer->authorize($user, $context, GetProjectReportRulesTool::PERMISSION);
        $this->authorizeParticipant($user, $projectId, $participantId);

        $luvType = ProjektLuvTemplate::fromReportType($reportType);
        $sources = array_replace(
            ProjektLuvTemplate::DEFAULT_SOURCE_SETTINGS,
            $project->activeLuvTemplateFor($luvType)?->source_settings ?: [],
        );
        $allowedTools = [GetProjectReportRulesTool::NAME];
        if ($sources['identity']) {
            $allowedTools[] = GetParticipantIdentitySummaryTool::NAME;
        }
        if ($sources['previous_luvs']) {
            $allowedTools[] = GetParticipantLuvDataTool::NAME;
        }
        if ($sources['attendance']) {
            $allowedTools[] = GetAttendanceSummaryTool::NAME;
        }
        if ($sources['documentation']) {
            $allowedTools[] = GetDocumentationEntriesTool::NAME;
        }
        if ($sources['internships'] || $sources['education'] || $sources['consents']) {
            $allowedTools[] = GetParticipantDevelopmentDataTool::NAME;
        }
        if (($sources['potential_analysis'] ?? true) && $project->supportsLuvPotentialAnalysis()) {
            $allowedTools[] = GetParticipantPotentialAnalysisSupportNeedsTool::NAME;
        }

        $context = new AiRunContext((int) $user->getKey(), $projectId, $allowedTools, $participantId, $fromDate, $untilDate, $reportType, $sources);

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

        $response['report'] = $this->approvedPaSupportNeeds->merge($response['report'], $toolResults);

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
