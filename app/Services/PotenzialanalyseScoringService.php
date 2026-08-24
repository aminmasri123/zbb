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
                'exercises' => 100,
                'coach' => 0,
                'self' => 0,
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
            // Prozentwert und Stufe basieren ausschließlich auf den Übungen.
            // Gespeicherte Alt-Konfigurationen mit 60/30/10 dürfen das Ergebnis nicht mehr verändern.
            'source_weights' => $defaults['source_weights'],
            'report_style' => $config['report_style'] ?? $defaults['report_style'],
        ];
    }

    public function scoreExercises(
        Collection $exercises,
        array $results,
        ?array $config = null,
        ?array $competencyDefinitions = null
    ): array
    {
        $config = $this->normalizeConfig($config);
        $competencyDefinitions ??= self::COMPETENCIES;
        $competencies = collect($competencyDefinitions)->keyBy('key');
        $buckets = [];

        foreach ($exercises as $exercise) {
            if (data_get($exercise, 'auswertbar', true) === false) {
                continue;
            }

            $exerciseId = (int) data_get($exercise, 'id');
            $entry = $results[$exerciseId] ?? $results[(string) $exerciseId] ?? null;
            $value = is_array($entry)
                ? ($entry['berechnete_punkte'] ?? $entry['punkte'] ?? null)
                : (data_get($entry, 'berechnete_punkte') ?? data_get($entry, 'punkte'));

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

        return collect($competencyDefinitions)->mapWithKeys(function (array $competency) use ($buckets, $config) {
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

    public function combinedScores(
        array $exerciseScores,
        array $coach,
        array $self,
        ?array $config = null,
        ?array $competencyDefinitions = null
    ): array
    {
        $config = $this->normalizeConfig($config);
        $competencyDefinitions ??= self::COMPETENCIES;

        return collect($competencyDefinitions)->mapWithKeys(function (array $competency) use ($exerciseScores, $config) {
            $key = $competency['key'];
            $exercisePercentage = $exerciseScores[$key]['percentage'] ?? null;
            $percentage = $exercisePercentage !== null ? (float) $exercisePercentage : null;
            $sources = $percentage !== null
                ? [['source' => 'Übungen', 'percentage' => $percentage, 'weight' => 100.0]]
                : [];

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
        int $variation = 0,
        ?array $competencyDefinitions = null,
    ): array {
        $competencyDefinitions ??= self::COMPETENCIES;
        $style = collect(self::REPORT_STYLES)->pluck('value')->contains($style) ? $style : 'staerkenorientiert';
        $rated = collect($combinedScores)->filter(fn (array $item) => $item['rating'] !== null);
        $strengths = $rated->filter(fn (array $item) => $item['rating'] >= 4)->sortByDesc('percentage')->values();
        $developing = $rated->filter(fn (array $item) => $item['rating'] <= 2)->sortBy('percentage')->values();
        $solid = $rated->filter(fn (array $item) => $item['rating'] === 3)->sortByDesc('percentage')->values();

        $manualStrengths = trim((string) ($reportFields['staerken'] ?? ''));
        $manualRecommendation = trim((string) ($reportFields['empfehlung'] ?? ''));

        $firstName = trim((string) data_get($participant, 'vorname', ''));
        $gender = Str::lower(trim((string) data_get($participant, 'geschlecht', '')));
        $greeting = match ($gender) {
            'w', 'weiblich', 'frau', 'f', 'female' => 'Liebe ' . ($firstName !== '' ? $firstName : 'Teilnehmerin') . ',',
            'm', 'männlich', 'maennlich', 'mann', 'herr', 'male' => 'Lieber ' . ($firstName !== '' ? $firstName : 'Teilnehmer') . ',',
            default => $firstName !== '' ? 'Hallo ' . $firstName . ',' : 'Hallo,',
        };
        $focus = $strengths->first() ?? $solid->first() ?? $rated->first();
        $focusLabel = (string) ($focus['label'] ?? 'deinen persönlichen Fähigkeiten');
        $focusKey = (string) ($focus['key'] ?? '');

        $seedSource = implode('|', [
            (string) data_get($participant, 'id', ''),
            $firstName,
            json_encode($rated->pluck('percentage', 'key')->all()),
            $manualStrengths,
            $manualRecommendation,
        ]);
        $seed = (int) sprintf('%u', crc32($seedSource));
        $variation = $variation > 0 ? $variation : random_int(1, PHP_INT_MAX);
        $variantSeed = $seed + $variation;

        $competencySentences = [
            'feinmotorik' => [
                'du hast feinmotorische Aufgaben geschickt und kontrolliert bearbeitet.',
                'du hast bei feinmotorischen Anforderungen eine sichere und ruhige Arbeitsweise gezeigt.',
                'du konntest deine Hände gezielt einsetzen und hast feinmotorische Aufgaben sorgfältig ausgeführt.',
                'du hast bei handwerklichen Feinaufgaben Geschick und eine gute Kontrolle bewiesen.',
                'du bist feinmotorische Aufgaben konzentriert und mit einer sicheren Hand angegangen.',
                'du verfügst über ein gutes feinmotorisches Geschick und setzt dieses aufmerksam ein.',
            ],
            'grobmotorik' => [
                'du hast Bewegungsabläufe sicher und zielgerichtet umgesetzt.',
                'du konntest körperliche Bewegungen gut koordinieren und kontrolliert ausführen.',
                'du hast bei praktischen Aufgaben eine gute Bewegungskoordination gezeigt.',
                'du bist körperlich-praktische Anforderungen sicher und geschickt angegangen.',
                'du hast deine Bewegungen passend gesteuert und Aufgaben körperlich sicher umgesetzt.',
                'du bringst eine gute grobmotorische Sicherheit für praktische Tätigkeiten mit.',
            ],
            'wahrnehmung_symmetrie' => [
                'du hast Formen und Zusammenhänge aufmerksam erkannt und passend umgesetzt.',
                'du besitzt eine gute Wahrnehmung und konntest räumliche sowie symmetrische Strukturen sicher erfassen.',
                'du hast genau hingesehen und Formen, Abstände und Zusammenhänge zuverlässig erkannt.',
                'du konntest visuelle Informationen aufmerksam aufnehmen und richtig übertragen.',
                'du hast bei Aufgaben zur Wahrnehmung ein gutes Auge für Formen und Einzelheiten gezeigt.',
                'du erkennst räumliche Zusammenhänge gut und setzt deine Beobachtungen passend um.',
            ],
            'analyse_problemloesefaehigkeit' => [
                'du hast Aufgabenstellungen aufmerksam erfasst und passende Lösungswege gefunden.',
                'du konntest Probleme gut analysieren und bist überlegt zu einer Lösung gekommen.',
                'du hast Zusammenhänge schnell erkannt und sinnvolle Entscheidungen getroffen.',
                'du bist schwierige Aufgaben überlegt angegangen und hast eigenständig Lösungen entwickelt.',
                'du hast Informationen gut ausgewertet und daraus passende Schritte abgeleitet.',
                'du zeigst ein gutes Gespür dafür, Probleme zu verstehen und zielgerichtet zu lösen.',
            ],
            'arbeitsplanung' => [
                'du bist Aufgaben strukturiert angegangen und hast dein Ziel im Blick behalten.',
                'du hast deine Arbeitsschritte sinnvoll geplant und zuverlässig umgesetzt.',
                'du konntest Aufgaben gut ordnen und bist planvoll vorgegangen.',
                'du hast vorausschauend gearbeitet und deine einzelnen Schritte passend aufeinander abgestimmt.',
                'du bist organisiert vorgegangen und hast deine Aufgaben zielgerichtet bearbeitet.',
                'du hast gezeigt, dass du Arbeitsabläufe gut planen und selbstständig umsetzen kannst.',
            ],
            'motivation_leistungsbereitschaft' => [
                'du hast eine gute Motivation und Leistungsbereitschaft gezeigt.',
                'du warst leistungsbereit und zielstrebig und wolltest deine Aufgaben gut erledigen.',
                'du bist deine Aufgaben motiviert angegangen und hast dich engagiert eingebracht.',
                'du hast konzentriert gearbeitet und dabei eine hohe Einsatzbereitschaft gezeigt.',
                'du warst aufmerksam bei der Sache und hast dich mit viel Motivation eingesetzt.',
                'du hast deine Aufgaben mit Interesse, Energie und erkennbarem Leistungswillen bearbeitet.',
            ],
            'durchhaltevermoegen' => [
                'du hast auch bei anspruchsvollen Aufgaben konzentriert weitergearbeitet und nicht aufgegeben.',
                'du warst ausdauernd und hast deine Aufgaben konsequent zu Ende gebracht.',
                'du hast dich auch bei längeren Aufgaben nicht entmutigen lassen und bist am Ball geblieben.',
                'du hast Geduld und Ausdauer gezeigt und dein Ziel zuverlässig verfolgt.',
                'du bist auch bei Schwierigkeiten konzentriert geblieben und hast dich weiter angestrengt.',
                'du hast Aufgaben beharrlich bearbeitet und dabei ein gutes Durchhaltevermögen bewiesen.',
            ],
            'sorgfalt' => [
                'du hast gewissenhaft gearbeitet und zuverlässig auf wichtige Einzelheiten geachtet.',
                'du bist bei deinen Aufgaben genau vorgegangen und hast sorgfältig gearbeitet.',
                'du hast Wert auf ein ordentliches Ergebnis gelegt und deine Arbeit aufmerksam ausgeführt.',
                'du konntest konzentriert und präzise arbeiten und hast Details gut berücksichtigt.',
                'du hast deine Aufgaben verlässlich geprüft und mit großer Sorgfalt abgeschlossen.',
                'du arbeitest genau und möchtest deine Sache erkennbar gut machen.',
            ],
            'kommunikation' => [
                'du hast deine Gedanken verständlich eingebracht und anderen aufmerksam zugehört.',
                'du konntest dich klar ausdrücken und bist gut auf dein Gegenüber eingegangen.',
                'du hast offen kommuniziert und wichtige Informationen verständlich weitergegeben.',
                'du hast dich angemessen ausgedrückt und Gespräche aufmerksam mitgestaltet.',
                'du konntest deine Meinung verständlich vertreten und hast andere ausreden lassen.',
                'du hast im Austausch mit anderen freundlich und verständlich kommuniziert.',
            ],
            'teamfaehigkeit' => [
                'du hast verlässlich mit anderen zusammengearbeitet und dein Team gut unterstützt.',
                'du warst ein hilfsbereites Teammitglied und hast dich gut in die Gruppe eingebracht.',
                'du konntest gemeinsam mit anderen zielgerichtet arbeiten und Absprachen einhalten.',
                'du hast kooperativ gearbeitet und zu einem guten Ergebnis der Gruppe beigetragen.',
                'du bist auf andere eingegangen und hast gemeinsame Aufgaben verantwortungsvoll mitgetragen.',
                'du hast Teamgeist gezeigt und andere bei der gemeinsamen Arbeit unterstützt.',
            ],
            'umgangsformen' => [
                'du bist anderen respektvoll begegnet und freundlich sowie angemessen aufgetreten.',
                'du hast dich höflich und rücksichtsvoll verhalten und bist wertschätzend mit anderen umgegangen.',
                'du bist deinem Gegenüber freundlich begegnet und hast gute Umgangsformen gezeigt.',
                'du hast dich respektvoll in die Gruppe eingebracht und bist anderen aufmerksam begegnet.',
                'du warst höflich, verlässlich und hast zu einer angenehmen Zusammenarbeit beigetragen.',
                'du hast im Kontakt mit anderen ein freundliches und angemessenes Auftreten gezeigt.',
            ],
        ];
        $focusVariants = $competencySentences[$focusKey] ?? [
            'du hast in der Potenzialanalyse persönliche Stärken gezeigt, die dir auf deinem weiteren Weg helfen können.',
            'du hast dich während der Potenzialanalyse engagiert eingebracht und wertvolle Fähigkeiten gezeigt.',
            'du hast bei der Potenzialanalyse gute persönliche Voraussetzungen erkennen lassen.',
        ];
        $sentences = [$focusVariants[$variantSeed % count($focusVariants)]];

        if ($manualStrengths !== '') {
            preg_match_all('/\p{L}+/u', $manualStrengths, $words);
            if (count($words[0] ?? []) >= 5) {
                $sentences[] = $this->sentence($manualStrengths);
            } else {
                $manualLabel = rtrim($manualStrengths, '.!?');
                $manualVariants = [
                    'Auch ' . $manualLabel . ' gehört zu deinen persönlichen Stärken.',
                    'Darüber hinaus zählt ' . $manualLabel . ' zu deinen guten Fähigkeiten.',
                    'Eine weitere erkennbare Stärke von dir ist ' . $manualLabel . '.',
                    'Zusätzlich bringst du im Bereich ' . $manualLabel . ' gute Voraussetzungen mit.',
                ];
                $sentences[] = $manualVariants[intdiv($variantSeed, 6) % count($manualVariants)];
            }
        } else {
            $additionalLabels = $strengths
                ->pluck('label')
                ->filter(fn (string $label) => $label !== $focusLabel)
                ->take($style === 'kompakt' ? 1 : 2)
                ->values()
                ->all();
            if ($additionalLabels !== []) {
                $sentences[] = 'Auch ' . $this->joinWords($additionalLabels) . ' zählen zu deinen erkennbaren Stärken.';
            }
        }

        $agreements = collect($competencyDefinitions)->filter(function (array $competency) use ($coach, $self) {
            $coachRating = data_get($coach, $competency['key'] . '.bewertung');
            $selfRating = data_get($self, $competency['key'] . '.bewertung');
            return $coachRating !== null && $selfRating !== null && abs((int) $coachRating - (int) $selfRating) <= 1;
        })->pluck('label')->take(3)->all();
        if ($agreements !== []) {
            $agreementVariants = [
                'Deine eigene Einschätzung passt gut zu den Fähigkeiten, die du gezeigt hast.',
                'Du kennst deine persönlichen Stärken bereits gut und kannst darauf aufbauen.',
                'Deine Einschätzung deiner Fähigkeiten ergibt ein stimmiges Gesamtbild.',
                'Auch du selbst hast diese Fähigkeiten als persönliche Stärken erkannt.',
            ];
            $sentences[] = $agreementVariants[intdiv($variantSeed, 24) % count($agreementVariants)];
        }

        if ($manualRecommendation !== '') {
            $sentences[] = $this->sentence($manualRecommendation);
        } elseif ($rated->isNotEmpty()) {
            $closingVariants = [
                'Diese Fähigkeiten sind eine gute Grundlage für deine berufliche Orientierung.',
                'Mit diesen Stärken bringst du wertvolle Voraussetzungen für deinen weiteren Berufsweg mit.',
                'Deine Stärken können dir bei zukünftigen schulischen und beruflichen Aufgaben helfen.',
                'Auf diese Fähigkeiten kannst du bei deiner weiteren beruflichen Orientierung gut aufbauen.',
                'Bewahre dir diese Stärken und setze sie auf deinem weiteren Weg selbstbewusst ein.',
                'Diese Eigenschaften werden dir in der Schule und im zukünftigen Berufsleben nützlich sein.',
                'Damit besitzt du Fähigkeiten, die dich auf deinem schulischen und beruflichen Weg unterstützen.',
                'Diese Eigenschaften sind für deinen weiteren Schul- und Berufsweg besonders wertvoll.',
            ];
            $sentences[] = $closingVariants[intdiv($variantSeed, 96) % count($closingVariants)];
        }

        $wishVariants = [
            'Wir wünschen dir alles Gute für deine Zukunft.',
            'Für deinen weiteren Berufsweg wünschen wir dir alles Gute und viel Erfolg.',
            'Wir wünschen dir für deine weitere Zukunft alles Gute und viel Erfolg.',
            'Für deine berufliche Zukunft wünschen wir dir viel Erfolg und alles Gute.',
            'Wir wünschen dir auf deinem weiteren Schul- und Berufsweg alles Gute.',
            'Für deinen weiteren Weg wünschen wir dir viel Erfolg und alles Gute.',
        ];
        $wish = $wishVariants[intdiv($variantSeed, 768) % count($wishVariants)];
        $body = '';
        foreach (array_values(array_filter($sentences)) as $sentence) {
            $candidate = trim($body . ' ' . $sentence);
            if (mb_strlen($greeting . "\n\n" . $candidate . "\n\n" . $wish) <= 500) {
                $body = $candidate;
            }
        }

        return [
            'text' => $greeting . "\n\n" . $body . "\n\n" . $wish,
            'style' => $style,
            'variation' => $variation,
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
