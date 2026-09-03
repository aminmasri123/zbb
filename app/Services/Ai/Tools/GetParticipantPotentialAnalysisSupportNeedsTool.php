<?php

namespace App\Services\Ai\Tools;

use App\Models\PotenzialanalyseBericht;
use App\Models\User;
use App\Services\Ai\AiProjectAuthorizer;
use App\Services\Ai\AiRunContext;
use App\Services\Ai\Contracts\AiTool;
use Illuminate\Auth\Access\AuthorizationException;

final class GetParticipantPotentialAnalysisSupportNeedsTool implements AiTool
{
    use AuthorizesParticipantTool;

    public const NAME = 'get_participant_potential_analysis_support_needs';

    public function __construct(private readonly AiProjectAuthorizer $authorizer) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function execute(User $user, AiRunContext $context, array $arguments): array
    {
        $this->assertNoArguments($arguments);
        $participation = $this->participation($user, $context);
        $project = $participation->projekt()->firstOrFail();

        if (! $project->supportsLuvPotentialAnalysis()) {
            throw new AuthorizationException('Die Potenzialanalyse ist fuer dieses LuV-Projekt nicht als Quelle freigegeben.');
        }

        $report = PotenzialanalyseBericht::query()
            ->where('personen_id', $context->participantId)
            ->whereIn('status', ['fertig', 'geprueft'])
            ->whereDate('fertiggestellt_at', '<=', $context->untilDate)
            ->whereHas('gruppe', fn ($query) => $query->where('projekt_id', $context->projectId))
            ->orderByDesc('fertiggestellt_at')
            ->orderByDesc('updated_at')
            ->first();

        $entries = [];
        if ($report) {
            $supportNeeds = (array) $report->luv_foerderbedarfe;

            foreach (PotenzialanalyseBericht::LUV_FOERDERBEDARF_BEREICHE as $key => $definition) {
                $entry = (array) ($supportNeeds[$key] ?? []);
                $status = (string) ($entry['status'] ?? 'unprueft');
                $approved = (bool) ($entry['freigegeben'] ?? false);
                $supportNeed = trim((string) ($entry['foerderbedarf'] ?? ''));

                if (! $approved || ! in_array($status, ['kein_foerderbedarf', 'foerderbedarf'], true)) {
                    continue;
                }
                if ($status === 'foerderbedarf' && $supportNeed === '') {
                    continue;
                }

                $entries[$key] = [
                    'source_id' => "potential-analysis-support-{$report->id}-{$key}",
                    'field_key' => $definition['field_key'],
                    'category' => $definition['label'],
                    'decision' => $status === 'foerderbedarf' ? 'support_need' : 'no_support_need',
                    'observation' => trim((string) ($entry['begruendung'] ?? '')) ?: null,
                    'support_need' => $status === 'foerderbedarf'
                        ? $supportNeed
                        : "Im Bereich {$definition['label']} wurde kein zusätzlicher Förderbedarf festgestellt.",
                    'report_status' => $report->status,
                    'completed_at' => $report->fertiggestellt_at?->toIso8601String(),
                    'approved_at' => $entry['freigegeben_am'] ?? null,
                ];
            }
        }

        return [
            'source_id' => 'participant-potential-analysis-support-summary',
            'period' => ['from' => $context->fromDate, 'until' => $context->untilDate],
            'entries' => array_values($entries),
        ];
    }
}
