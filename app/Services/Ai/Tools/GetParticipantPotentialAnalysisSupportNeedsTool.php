<?php

namespace App\Services\Ai\Tools;

use App\Models\PotenzialanalyseBericht;
use App\Models\PotenzialanalyseKompetenzbewertung;
use App\Services\PotenzialanalyseProfileService;
use App\Models\User;
use App\Services\Ai\AiProjectAuthorizer;
use App\Services\Ai\AiRunContext;
use App\Services\Ai\Contracts\AiTool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Throwable;

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
            ->whereDate('fertiggestellt_at', '<=', $context->untilDate)
            ->whereHas('gruppe', fn ($query) => $query->where('projekt_id', $context->projectId))
            ->orderByDesc('fertiggestellt_at')
            ->orderByDesc('updated_at')
            ->get();

        $entries = [];
        foreach (PotenzialanalyseBericht::LUV_FOERDERBEDARF_BEREICHE as $key => $definition) {
            $approvedEntries = [];

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

                $approvedAt = $this->approvedAt($entry, $report);
                if (! $approvedAt
                    || $approvedAt->toDateString() < $context->fromDate
                    || $approvedAt->toDateString() > $context->untilDate) {
                    continue;
                }

                $approvedEntries[] = [
                    'approved_timestamp' => $approvedAt->getTimestamp(),
                    'entry' => [
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
                        'approved_at' => $approvedAt->toIso8601String(),
                    ],
                ];
            }

            $newestApprovedEntry = collect($approvedEntries)
                ->sortByDesc('approved_timestamp')
                ->first();
            if ($newestApprovedEntry) {
                $entries[$key] = $newestApprovedEntry['entry'];
            }
        }

        return [
            'source_id' => 'participant-potential-analysis-support-summary',
            'period' => ['from' => $context->fromDate, 'until' => $context->untilDate],
            'entries' => array_merge(array_values($entries), $this->assessments($reports, $context)),
        ];
    }

    private function assessments($reports, AiRunContext $context): array
    {
        $entries = [];
        foreach ($reports as $report) {
            if ($report->fertiggestellt_at->toDateString() < $context->fromDate) {
                continue;
            }
            $definitions = collect(app(PotenzialanalyseProfileService::class)->competenciesForGroup($report->gruppe))->keyBy('key');
            $ratings = PotenzialanalyseKompetenzbewertung::query()
                ->where('gruppe_id', $report->gruppe_id)
                ->where('personen_id', $context->participantId)
                ->where('typ', 'anleiter')
                ->whereBetween('bewertung', [1, 5])
                ->where('updated_at', '<=', $context->untilDate.' 23:59:59')
                ->orderBy('id')->get();
            foreach ($ratings as $rating) {
                $definition = $definitions->get($rating->merkmal);
                if (! $definition) {
                    continue;
                }
                $category = match ($definition['category_code'] ?? $definition['category'] ?? '') {
                    'PP', 'Personale Kompetenzen', 'Persönliche Kompetenzen' => 'personal',
                    'MP', 'Methodische Kompetenzen' => 'methodical',
                    'SP', 'Soziale Kompetenzen' => 'social',
                    'PR', 'Berufsübergreifende Kompetenzen', 'Praktische Kompetenzen' => 'practical',
                    default => null,
                };
                if (! $category || isset($entries[$rating->merkmal])) {
                    continue;
                }
                // Keep individual strengths and difficulties; never average them
                // into an invented global assessment or derive a support decision.
                $text = 'Potenzialanalyse vom '.$report->fertiggestellt_at->format('d.m.Y').': '
                    .$definition['label'].' – dokumentierte Einschätzung '.$rating->bewertung.' von 5.';
                if (filled($rating->bemerkung)) {
                    $text .= ' '.trim($rating->bemerkung);
                }
                $entries[$rating->merkmal] = [
                    'source_id' => "potential-analysis-assessment-{$report->id}-{$rating->id}",
                    'field_key' => match ($context->reportType) {
                        'interim' => 'development.notes',
                        'final' => 'support.description',
                        default => $category === 'practical' ? 'competence.notes' : "competence.{$category}.assessment",
                    },
                    'category_key' => $category,
                    'category' => $definition['category'],
                    'decision' => 'assessment',
                    'observation' => mb_substr($text, 0, 3800),
                    'rating' => $rating->bewertung,
                    'report_status' => $report->status,
                    'completed_at' => $report->fertiggestellt_at->toIso8601String(),
                ];
            }
        }

        return array_values($entries);
    }

    private function fieldKey(string $reportType, string $categoryKey, string $startFieldKey): string
    {
        return match ($reportType) {
            'interim' => "competence.{$categoryKey}.current_need",
            'final' => 'support.description',
            default => $startFieldKey,
        };
    }

    /** @param array<string, mixed> $entry */
    private function approvedAt(array $entry, PotenzialanalyseBericht $report): ?Carbon
    {
        $approvedAt = trim((string) ($entry['freigegeben_am'] ?? ''));

        if ($approvedAt !== '') {
            try {
                return Carbon::parse($approvedAt);
            } catch (Throwable) {
                // Ältere Datensätze können noch keinen normierten Zeitwert besitzen.
            }
        }

        return $report->fertiggestellt_at
            ? Carbon::instance($report->fertiggestellt_at)
            : null;
    }
}
