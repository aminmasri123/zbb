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

        $reports = PotenzialanalyseBericht::query()
            ->where('personen_id', $context->participantId)
            ->whereIn('status', ['fertig', 'geprueft'])
            ->when($context->fromDate, fn ($query, $fromDate) => $query->whereDate('fertiggestellt_at', '>=', $fromDate))
            ->whereDate('fertiggestellt_at', '<=', $context->untilDate)
            ->whereHas('gruppe', fn ($query) => $query->where('projekt_id', $context->projectId))
            ->orderByDesc('fertiggestellt_at')
            ->orderByDesc('updated_at')
            ->get();

        $entries = [];
        foreach (PotenzialanalyseBericht::LUV_FOERDERBEDARF_BEREICHE as $key => $definition) {
            foreach ($reports as $report) {
                $supportNeeds = (array) $report->luv_foerderbedarfe;
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
                    'field_key' => $this->fieldKey($context->reportType, $key, $definition['field_key']),
                    'category_key' => $key,
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

                // Die Berichte sind absteigend sortiert. Pro Kompetenzbereich
                // genügt daher die neueste tatsächlich freigegebene Angabe.
                break;
            }
        }

        return [
            'source_id' => 'participant-potential-analysis-support-summary',
            'period' => ['from' => $context->fromDate, 'until' => $context->untilDate],
            'entries' => array_values($entries),
        ];
    }

    private function fieldKey(string $reportType, string $categoryKey, string $startFieldKey): string
    {
        return match ($reportType) {
            'interim' => "competence.{$categoryKey}.current_need",
            'final' => 'support.description',
            default => $startFieldKey,
        };
    }
}
