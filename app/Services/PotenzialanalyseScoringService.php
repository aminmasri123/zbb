<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PotenzialanalyseScoringService
{
    public const COMPETENCIES = [
        ['key' => 'feinmotorik', 'label' => 'Feinmotorik', 'category' => 'Berufsübergreifende Kompetenzen'],
        ['key' => 'grobmotorik', 'label' => 'Grobmotorik', 'category' => 'Berufsübergreifende Kompetenzen'],
        ['key' => 'wahrnehmung_symmetrie', 'label' => 'Wahrnehmung und Symmetrie', 'category' => 'Berufsübergreifende Kompetenzen'],
        ['key' => 'analyse_problemloesefaehigkeit', 'label' => 'Analyse- und Problemlösefähigkeit', 'category' => 'Methodische Kompetenzen'],
        ['key' => 'arbeitsplanung', 'label' => 'Arbeitsplanung', 'category' => 'Methodische Kompetenzen'],
        ['key' => 'motivation_leistungsbereitschaft', 'label' => 'Motivation und Leistungsbereitschaft', 'category' => 'Personale Kompetenzen'],
        ['key' => 'durchhaltevermoegen', 'label' => 'Durchhaltevermögen', 'category' => 'Personale Kompetenzen'],
        ['key' => 'sorgfalt', 'label' => 'Sorgfalt und Genauigkeit', 'category' => 'Personale Kompetenzen'],
        ['key' => 'kommunikation', 'label' => 'Kommunikation', 'category' => 'Soziale Kompetenzen'],
        ['key' => 'teamfaehigkeit', 'label' => 'Teamfähigkeit', 'category' => 'Soziale Kompetenzen'],
        ['key' => 'umgangsformen', 'label' => 'Umgangsformen', 'category' => 'Soziale Kompetenzen'],
    ];

    public const REPORT_STYLES = [
        ['value' => 'staerkenorientiert', 'label' => 'Stärkenorientiert'],
        ['value' => 'ausfuehrlich', 'label' => 'Ausführlich'],
        ['value' => 'kompakt', 'label' => 'Kompakt'],
        ['value' => 'sachlich', 'label' => 'Sachlich und wertschätzend'],
    ];

    public function defaultConfig(): array
    {
        return [
            'thresholds' => [
                'rating_2_from' => 20,
                'rating_3_from' => 40,
                'rating_4_from' => 60,
                'rating_5_from' => 80,
            ],
            'source_weights' => [
                'exercises' => 60,
                'coach' => 30,
                'self' => 10,
            ],
            'report_style' => 'staerkenorientiert',
        ];
    }

    public function normalizeConfig(?array $config): array
    {
        $defaults = $this->defaultConfig();
        $config = $config ?? [];

        return [
            'thresholds' => array_merge($defaults['thresholds'], $config['thresholds'] ?? []),
            'source_weights' => array_merge($defaults['source_weights'], $config['source_weights'] ?? []),
            'report_style' => $config['report_style'] ?? $defaults['report_style'],
        ];
    }

    public function scoreExercises(Collection $exercises, array $results, ?array $config = null): array
    {
        $config = $this->normalizeConfig($config);
        $competencies = collect(self::COMPETENCIES)->keyBy('key');
        $buckets = [];

        foreach ($exercises as $exercise) {
            if (data_get($exercise, 'auswertbar', true) === false) {
                continue;
            }

            $exerciseId = (int) data_get($exercise, 'id');
            $entry = $results[$exerciseId] ?? $results[(string) $exerciseId] ?? null;
            $value = is_array($entry) ? ($entry['punkte'] ?? null) : data_get($entry, 'punkte');

            if ($value === null || $value === '') {
                continue;
            }

            $minimum = (float) (data_get($exercise, 'mindestwert') ?? 0);
            $type = (string) (data_get($exercise, 'ergebnis_typ') ?: 'punkte');
            $maximum = $type === 'prozent'
                ? 100.0
                : (float) (data_get($exercise, 'hoechstwert') ?? 0);

            if ($maximum <= $minimum) {
                continue;
            }

            $percentage = max(0, min(100, (((float) $value - $minimum) / ($maximum - $minimum)) * 100));
            $mappings = data_get($exercise, 'kompetenzZuordnungen')
                ?? data_get($exercise, 'kompetenz_zuordnungen')
                ?? data_get($exercise, 'kompetenzen')
                ?? [];

            foreach ($mappings as $mapping) {
                $key = (string) data_get($mapping, 'merkmal');
                $active = data_get($mapping, 'aktiv', true);
                $weight = (float) (data_get($mapping, 'gewichtung') ?? 0);

                if (! $active || $weight <= 0 || ! $competencies->has($key)) {
                    continue;
                }

                $buckets[$key] ??= ['weighted_sum' => 0.0, 'weight_sum' => 0.0, 'contributions' => []];
                $buckets[$key]['weighted_sum'] += $percentage * $weight;
                $buckets[$key]['weight_sum'] += $weight;
                $buckets[$key]['contributions'][] = [
                    'exercise_id' => $exerciseId,
                    'exercise' => (string) data_get($exercise, 'name', 'Übung'),
                    'value' => (float) $value,
                    'minimum' => $minimum,
                    'maximum' => $maximum,
                    'percentage' => round($percentage, 1),
                    'weight' => round($weight, 2),
                ];
            }
        }

        return collect(self::COMPETENCIES)->mapWithKeys(function (array $competency) use ($buckets, $config) {
            $bucket = $buckets[$competency['key']] ?? null;
            if (! $bucket || $bucket['weight_sum'] <= 0) {
                return [$competency['key'] => $competency + [
                    'percentage' => null,
                    'rating' => null,
                    'total_weight' => 0,
                    'contributions' => [],
                    'explanation' => 'Noch keine auswertbaren Übungsergebnisse vorhanden.',
                ]];
            }

            $percentage = $bucket['weighted_sum'] / $bucket['weight_sum'];
            $rating = $this->ratingFromPercentage($percentage, $config['thresholds']);
            $names = collect($bucket['contributions'])->pluck('exercise')->unique()->values()->all();

            return [$competency['key'] => $competency + [
                'percentage' => round($percentage, 1),
                'rating' => $rating,
                'total_weight' => round($bucket['weight_sum'], 2),
                'contributions' => $bucket['contributions'],
                'explanation' => sprintf(
                    '%s %% aus %s.',
                    number_format($percentage, 1, ',', '.'),
                    $this->joinWords($names),
                ),
            ]];
        })->all();
    }

    public function combinedScores(array $exerciseScores, array $coach, array $self, ?array $config = null): array
    {
        $config = $this->normalizeConfig($config);
        $weights = $config['source_weights'];

        return collect(self::COMPETENCIES)->mapWithKeys(function (array $competency) use ($exerciseScores, $coach, $self, $weights, $config) {
            $key = $competency['key'];
            $sources = [];
            $exercisePercentage = $exerciseScores[$key]['percentage'] ?? null;

            if ($exercisePercentage !== null && (float) $weights['exercises'] > 0) {
                $sources[] = ['source' => 'Übungen', 'percentage' => (float) $exercisePercentage, 'weight' => (float) $weights['exercises']];
            }

            foreach ([['Anleiter', $coach, 'coach'], ['Selbsteinschätzung', $self, 'self']] as [$label, $entries, $weightKey]) {
                $rating = data_get($entries, "$key.bewertung");
                if ($rating !== null && $rating !== '' && (float) $weights[$weightKey] > 0) {
                    $sources[] = [
                        'source' => $label,
                        'percentage' => $this->ratingToPercentage((int) $rating),
                        'weight' => (float) $weights[$weightKey],
                    ];
                }
            }

            $weightSum = collect($sources)->sum('weight');
            $percentage = $weightSum > 0
                ? collect($sources)->sum(fn (array $source) => $source['percentage'] * $source['weight']) / $weightSum
                : null;

            return [$key => $competency + [
                'percentage' => $percentage !== null ? round($percentage, 1) : null,
                'rating' => $percentage !== null ? $this->ratingFromPercentage($percentage, $config['thresholds']) : null,
                'sources' => $sources,
            ]];
        })->all();
    }

    public function generateReport(
        object|array $participant,
        array $combinedScores,
        array $exerciseScores,
        array $coach,
        array $self,
        array $reportFields,
        string $style = 'staerkenorientiert',
    ): array {
        $style = collect(self::REPORT_STYLES)->pluck('value')->contains($style) ? $style : 'staerkenorientiert';
        $firstName = (string) data_get($participant, 'vorname', 'Teilnehmer/in');
        $rated = collect($combinedScores)->filter(fn (array $item) => $item['rating'] !== null);
        $strengths = $rated->filter(fn (array $item) => $item['rating'] >= 4)->sortByDesc('percentage')->values();
        $developing = $rated->filter(fn (array $item) => $item['rating'] <= 2)->sortBy('percentage')->values();
        $solid = $rated->filter(fn (array $item) => $item['rating'] === 3)->sortByDesc('percentage')->values();

        $paragraphs = ["Liebe/r {$firstName},"];
        $manualStrengths = trim((string) ($reportFields['staerken'] ?? ''));
        $manualDevelopment = trim((string) ($reportFields['entwicklungsfelder'] ?? ''));
        $manualRecommendation = trim((string) ($reportFields['empfehlung'] ?? ''));

        $strengthLabels = $strengths->pluck('label')->take($style === 'kompakt' ? 2 : 4)->all();
        if ($manualStrengths !== '') {
            $paragraphs[] = $this->sentence($manualStrengths);
        }
        if ($strengthLabels !== []) {
            $paragraphs[] = 'Besonders deutlich zeigen sich deine Stärken in ' . $this->joinWords($strengthLabels) . '.';
        } elseif ($solid->isNotEmpty()) {
            $paragraphs[] = 'Du zeigst eine solide Grundlage in ' . $this->joinWords($solid->pluck('label')->take(3)->all()) . '.';
        } else {
            $paragraphs[] = 'Die Potenzialanalyse zeigt erste wertvolle Ansätze, an die du bei deinen nächsten Lernschritten gut anknüpfen kannst.';
        }

        $exerciseNames = collect($exerciseScores)
            ->flatMap(fn (array $score) => $score['contributions'] ?? [])
            ->sortByDesc('percentage')
            ->pluck('exercise')
            ->unique()
            ->take($style === 'kompakt' ? 2 : 4)
            ->values()
            ->all();
        if ($exerciseNames !== []) {
            $paragraphs[] = 'Diese Einschätzung wird insbesondere durch deine Ergebnisse in ' . $this->joinWords($exerciseNames) . ' unterstützt.';
        }

        $agreements = collect(self::COMPETENCIES)->filter(function (array $competency) use ($coach, $self) {
            $coachRating = data_get($coach, $competency['key'] . '.bewertung');
            $selfRating = data_get($self, $competency['key'] . '.bewertung');
            return $coachRating !== null && $selfRating !== null && abs((int) $coachRating - (int) $selfRating) <= 1;
        })->pluck('label')->take(3)->all();
        if ($agreements !== []) {
            $paragraphs[] = 'Deine Selbsteinschätzung stimmt bei ' . $this->joinWords($agreements) . ' gut mit den Beobachtungen überein.';
        }

        if ($manualDevelopment !== '') {
            $paragraphs[] = $this->positiveDevelopmentSentence($manualDevelopment);
        } elseif ($developing->isNotEmpty()) {
            $paragraphs[] = 'Als nächste Entwicklungsschritte kannst du ' . $this->joinWords(
                $developing->pluck('label')->take($style === 'kompakt' ? 1 : 2)->map(fn ($label) => Str::lower($label))->all()
            ) . ' weiter stärken.';
        }

        if ($manualRecommendation !== '') {
            $paragraphs[] = $this->sentence($manualRecommendation);
        } elseif ($rated->isNotEmpty()) {
            $paragraphs[] = 'Nutze deine erkennbaren Stärken weiterhin bewusst und erprobe sie in unterschiedlichen Aufgaben und Teams.';
        }

        if ($style === 'ausfuehrlich') {
            $sourceSummary = $rated->take(5)->map(fn (array $item) => sprintf(
                '%s: %s %% (Stufe %s)',
                $item['label'],
                number_format((float) $item['percentage'], 1, ',', '.'),
                $item['rating'],
            ))->implode('; ');
            if ($sourceSummary !== '') {
                $paragraphs[] = 'Berechnungsgrundlage: ' . $sourceSummary . '.';
            }
        }

        $paragraphs[] = 'Wir wünschen dir viel Erfolg dabei, deine Fähigkeiten weiterzuentwickeln und deine Stärken gezielt einzusetzen.';

        return [
            'text' => implode("\n\n", array_values(array_filter($paragraphs))),
            'style' => $style,
            'strengths' => $strengths->pluck('label')->values()->all(),
            'development_steps' => $developing->pluck('label')->values()->all(),
            'scores' => $combinedScores,
        ];
    }

    private function ratingFromPercentage(float $percentage, array $thresholds): int
    {
        if ($percentage >= (float) $thresholds['rating_5_from']) return 5;
        if ($percentage >= (float) $thresholds['rating_4_from']) return 4;
        if ($percentage >= (float) $thresholds['rating_3_from']) return 3;
        if ($percentage >= (float) $thresholds['rating_2_from']) return 2;
        return 1;
    }

    private function ratingToPercentage(int $rating): float
    {
        return max(0, min(100, (($rating - 1) / 4) * 100));
    }

    private function joinWords(array $values): string
    {
        $values = array_values(array_filter(array_map('strval', $values)));
        if (count($values) <= 1) return $values[0] ?? '';
        if (count($values) === 2) return $values[0] . ' und ' . $values[1];
        return implode(', ', array_slice($values, 0, -1)) . ' und ' . end($values);
    }

    private function sentence(string $value): string
    {
        $value = trim($value);
        return preg_match('/[.!?]$/u', $value) ? $value : $value . '.';
    }

    private function positiveDevelopmentSentence(string $value): string
    {
        $value = rtrim(trim($value), '.!?');
        return 'Als nächsten Entwicklungsschritt kannst du ' . Str::lower($value) . ' weiter stärken.';
    }
}
