<?php

namespace App\Services;

use App\Models\Gruppe;
use App\Models\PotenzialanalyseProfil;
use App\Models\PotenzialanalyseUebung;
use App\Models\Projekt;
use Illuminate\Support\Facades\DB;

class PotenzialanalyseProfileService
{
    public const HAMET_EPLUS_KEY = 'hamet_eplus';

    public const CATEGORIES = [
        'persoenlich' => ['label' => 'Persönliche Kompetenzen', 'code' => 'PP'],
        'praktisch' => ['label' => 'Praktische Kompetenzen', 'code' => 'PR'],
        'methodisch' => ['label' => 'Methodische Kompetenzen', 'code' => 'MP'],
        'sozial' => ['label' => 'Soziale Kompetenzen', 'code' => 'SP'],
    ];

    /**
     * The profile is deliberately additive. Projects without a profile continue
     * to use the unchanged BOP competency catalogue.
     */
    public function createHametEPlusProfile(Projekt $projekt, ?string $name = null): PotenzialanalyseProfil
    {
        return DB::transaction(function () use ($projekt, $name) {
            $version = ((int) PotenzialanalyseProfil::query()
                ->where('projekt_id', $projekt->id)
                ->where('key', self::HAMET_EPLUS_KEY)
                ->max('version')) + 1;

            PotenzialanalyseProfil::query()
                ->where('projekt_id', $projekt->id)
                ->update(['aktiv' => false]);

            $profil = PotenzialanalyseProfil::query()->create([
                'projekt_id' => $projekt->id,
                'key' => self::HAMET_EPLUS_KEY,
                'name' => $name ?: 'hamet e+',
                'version' => $version,
                'status' => 'entwurf',
                'aktiv' => true,
                'bericht_config' => [
                    'prinzip' => 'bop',
                    'kompetenz_kategorien' => array_values(self::CATEGORIES),
                    'selbst_fremd_vergleich' => true,
                    'staerkenprofil' => true,
                    'darstellung' => [
                        'titel' => 'Auswertung der Potenzialanalyse',
                        'untertitel' => 'hamet e+',
                        'uebungsergebnisse_anzeigen' => true,
                        'selbsteinschaetzung_anzeigen' => true,
                        'staerkenprofil_anzeigen' => true,
                    ],
                ],
                'veroeffentlicht_at' => null,
            ]);

            foreach ($this->hametCompetencies() as $kompetenz) {
                $profil->kompetenzen()->create($kompetenz);
            }

            $exercises = [];
            foreach ($this->hametExercises() as $exercise) {
                $exercises[$exercise['key']] = PotenzialanalyseUebung::query()->create([
                    'projekt_id' => $projekt->id,
                    'profil_id' => $profil->id,
                    'name' => $exercise['name'],
                    'tag' => null,
                    'beschreibung' => $exercise['beschreibung'],
                    'hoechstwert' => null,
                    'auswertbar' => false,
                    'ergebnis_typ' => 'punkte',
                    'berechnungsregel' => $exercise['berechnungsregel'],
                    'fehler_abzug' => 1,
                    'berechnungs_config' => [
                        'fachlich_zu_pruefen' => true,
                        'rohwerte' => $exercise['rohwerte'],
                    ],
                    'mindestwert' => 0,
                    'sort_order' => $exercise['sort_order'],
                    'aktiv' => true,
                ]);
            }

            foreach ($this->hametWeights() as $competencyKey => $weights) {
                foreach ($weights as $exerciseKey => $weight) {
                    $exercises[$exerciseKey]->kompetenzZuordnungen()->create([
                        'merkmal' => $competencyKey,
                        'gewichtung' => $weight,
                        'aktiv' => true,
                    ]);
                }
            }

            $projekt->update(['potenzialanalyse_profil_id' => $profil->id]);

            return $profil->load(['kompetenzen', 'uebungen.kompetenzZuordnungen']);
        });
    }

    public function createEmptyProfile(Projekt $projekt, string $name): PotenzialanalyseProfil
    {
        return DB::transaction(function () use ($projekt, $name) {
            PotenzialanalyseProfil::query()
                ->where('projekt_id', $projekt->id)
                ->update(['aktiv' => false]);

            $key = 'projekt_'.$projekt->id;
            $version = ((int) PotenzialanalyseProfil::query()
                ->where('projekt_id', $projekt->id)
                ->where('key', $key)
                ->max('version')) + 1;

            $profil = PotenzialanalyseProfil::query()->create([
                'projekt_id' => $projekt->id,
                'key' => $key,
                'name' => $name,
                'version' => $version,
                'status' => 'entwurf',
                'aktiv' => true,
                'bericht_config' => [
                    'selbst_fremd_vergleich' => true,
                    'staerkenprofil' => true,
                    'darstellung' => [
                        'titel' => 'Auswertung der Potenzialanalyse',
                        'untertitel' => null,
                        'uebungsergebnisse_anzeigen' => true,
                        'selbsteinschaetzung_anzeigen' => true,
                        'staerkenprofil_anzeigen' => true,
                    ],
                ],
            ]);

            $projekt->update(['potenzialanalyse_profil_id' => $profil->id]);

            return $profil;
        });
    }

    public function createNewVersion(PotenzialanalyseProfil $source): PotenzialanalyseProfil
    {
        $source->loadMissing(['kompetenzen', 'uebungen.kompetenzZuordnungen', 'uebungen.kriterien']);

        return DB::transaction(function () use ($source) {
            PotenzialanalyseProfil::query()
                ->where('projekt_id', $source->projekt_id)
                ->update(['aktiv' => false]);

            $version = ((int) PotenzialanalyseProfil::query()
                ->where('projekt_id', $source->projekt_id)
                ->where('key', $source->key)
                ->max('version')) + 1;

            $copy = PotenzialanalyseProfil::query()->create([
                'projekt_id' => $source->projekt_id,
                'key' => $source->key,
                'name' => $source->name,
                'version' => $version,
                'status' => 'entwurf',
                'aktiv' => true,
                'bericht_config' => $source->bericht_config,
            ]);

            foreach ($source->kompetenzen as $competency) {
                $copy->kompetenzen()->create($competency->only([
                    'key', 'label', 'kategorie', 'kategorie_label', 'kategorie_code',
                    'beschreibung', 'selbsteinschaetzung_text',
                    'bewertungsbeschreibungen', 'sort_order', 'aktiv',
                ]));
            }

            foreach ($source->uebungen as $exercise) {
                $exerciseCopy = PotenzialanalyseUebung::query()->create([
                    ...$exercise->only([
                        'projekt_id', 'name', 'tag', 'beschreibung', 'hoechstwert',
                        'auswertbar', 'ergebnis_typ', 'berechnungsregel', 'fehler_abzug',
                        'berechnungs_config', 'mindestwert', 'sort_order', 'aktiv',
                    ]),
                    'profil_id' => $copy->id,
                ]);

                foreach ($exercise->kompetenzZuordnungen as $mapping) {
                    $exerciseCopy->kompetenzZuordnungen()->create(
                        $mapping->only(['merkmal', 'gewichtung', 'aktiv'])
                    );
                }

                foreach ($exercise->kriterien as $criterion) {
                    $exerciseCopy->kriterien()->create(
                        $criterion->only([
                            'name', 'beschreibung', 'skala_min', 'skala_max',
                            'sort_order', 'aktiv',
                        ])
                    );
                }
            }

            $source->projekt()->update(['potenzialanalyse_profil_id' => $copy->id]);

            return $copy->load(['kompetenzen', 'uebungen.kompetenzZuordnungen']);
        });
    }

    public function publish(PotenzialanalyseProfil $profil): PotenzialanalyseProfil
    {
        $profil->loadMissing(['kompetenzen', 'uebungen.kompetenzZuordnungen']);

        if ($profil->kompetenzen->where('aktiv', true)->isEmpty()) {
            throw new \DomainException('Das Profil benötigt mindestens eine aktive Kompetenz.');
        }

        $validKeys = $profil->kompetenzen->where('aktiv', true)->pluck('key');
        $weightSums = $profil->uebungen
            ->where('aktiv', true)
            ->flatMap->kompetenzZuordnungen
            ->where('aktiv', true)
            ->whereIn('merkmal', $validKeys)
            ->groupBy('merkmal')
            ->map(fn ($entries) => round((float) $entries->sum('gewichtung'), 2));

        $invalid = $weightSums->filter(fn (float $sum) => abs($sum - 100.0) > 0.01);
        if ($invalid->isNotEmpty()) {
            throw new \DomainException('Jede in Übungen verwendete Kompetenz muss insgesamt genau 100 % ergeben.');
        }

        $profil->update([
            'status' => 'veroeffentlicht',
            'aktiv' => true,
            'veroeffentlicht_at' => now(),
        ]);
        $profil->projekt()->update(['potenzialanalyse_profil_id' => $profil->id]);

        return $profil->fresh(['kompetenzen', 'uebungen.kompetenzZuordnungen']);
    }

    public function competenciesForProject(?Projekt $projekt): array
    {
        if (! $projekt?->potenzialanalyse_profil_id) {
            return PotenzialanalyseScoringService::COMPETENCIES;
        }

        $profil = $projekt->relationLoaded('potenzialanalyseProfil')
            ? $projekt->potenzialanalyseProfil
            : $projekt->potenzialanalyseProfil()->with('kompetenzen')->first();

        return $this->profileCompetencies($profil);
    }

    public function competenciesForGroup(Gruppe $gruppe): array
    {
        $profil = $this->profileForGroup($gruppe);
        if (! $profil) {
            return PotenzialanalyseScoringService::COMPETENCIES;
        }

        return $this->profileCompetencies($profil);
    }

    public function profileForGroup(Gruppe $gruppe): ?PotenzialanalyseProfil
    {
        $profileId = $gruppe->potenzialanalyse_profil_id ?: $gruppe->projekt?->potenzialanalyse_profil_id;

        return $profileId
            ? PotenzialanalyseProfil::query()->with('kompetenzen')->find($profileId)
            : null;
    }

    public function profilePayload(?PotenzialanalyseProfil $profil): ?array
    {
        if (! $profil) {
            return null;
        }

        $profil->loadMissing('kompetenzen');

        return [
            'id' => $profil->id,
            'key' => $profil->key,
            'name' => $profil->name,
            'version' => $profil->version,
            'status' => $profil->status,
            'aktiv' => $profil->aktiv,
            'bericht_config' => $profil->bericht_config,
            'kompetenzen' => $this->profileCompetencies($profil),
        ];
    }

    private function profileCompetencies(?PotenzialanalyseProfil $profil): array
    {
        if (! $profil) {
            return PotenzialanalyseScoringService::COMPETENCIES;
        }

        $profil->loadMissing('kompetenzen');

        return $profil->kompetenzen
            ->where('aktiv', true)
            ->values()
            ->map(fn ($kompetenz) => [
                'key' => $kompetenz->key,
                'label' => $kompetenz->label,
                'category' => $kompetenz->kategorie_label,
                'category_key' => $kompetenz->kategorie,
                'category_code' => $kompetenz->kategorie_code,
                'description' => $kompetenz->beschreibung,
                'self_assessment_text' => $kompetenz->selbsteinschaetzung_text,
                'rating_descriptions' => $kompetenz->bewertungsbeschreibungen ?? [],
            ])
            ->all();
    }

    private function hametCompetencies(): array
    {
        $definitions = [
            ['motivation', 'Motivation', 'persoenlich', 'Ich gehe Aufgaben motiviert an und möchte ein gutes Ergebnis erreichen.'],
            ['geduld', 'Geduld', 'persoenlich', 'Ich kann auch bei längeren oder schwierigen Aufgaben ruhig weiterarbeiten.'],
            ['ausdauer_persistenz', 'Ausdauer/Persistenz', 'persoenlich', 'Ich bleibe an einer Aufgabe dran, auch wenn Schwierigkeiten auftreten.'],
            ['kreativitaet', 'Kreativität', 'persoenlich', 'Ich entwickle eigene Ideen und alternative Lösungswege.'],
            ['selbstreflexionsfaehigkeit', 'Selbstreflexionsfähigkeit', 'persoenlich', 'Ich kann meine Arbeitsweise und mein Ergebnis realistisch einschätzen.'],
            ['gewissenhaftigkeit', 'Gewissenhaftigkeit', 'persoenlich', 'Ich arbeite sorgfältig und kontrolliere mein Ergebnis.'],
            ['grobmotorik', 'Grobmotorik', 'praktisch', 'Ich kann kraftvolle und größere Bewegungsabläufe sicher steuern.'],
            ['feinmotorik', 'Feinmotorik', 'praktisch', 'Ich kann Werkzeuge und kleine Gegenstände sicher und genau führen.'],
            ['raeumliches_vorstellungsvermoegen', 'Räumliches Vorstellungsvermögen', 'praktisch', 'Ich kann räumliche Formen und Anordnungen erkennen und umsetzen.'],
            ['strukturierte_vorgehensweise', 'Strukturierte Vorgehensweise', 'methodisch', 'Ich plane meine Arbeitsschritte und führe sie in einer sinnvollen Reihenfolge aus.'],
            ['analyse_problemloesefaehigkeit', 'Analyse- und Problemlösefähigkeit', 'methodisch', 'Ich erkenne Probleme und entwickle passende Lösungswege.'],
            ['aufgabenverstaendnis', 'Aufgabenverständnis', 'methodisch', 'Ich verstehe Aufgabenstellungen und kann die Anforderungen richtig umsetzen.'],
            ['teamfaehigkeit', 'Teamfähigkeit', 'sozial', 'Ich arbeite konstruktiv mit anderen an einem gemeinsamen Ziel.'],
            ['kommunikationsfaehigkeit', 'Kommunikationsfähigkeit', 'sozial', 'Ich bringe meine Gedanken verständlich ein und höre anderen zu.'],
            ['aufmerksamkeit_achtsamkeit', 'Aufmerksamkeit und Achtsamkeit', 'sozial', 'Ich nehme andere, Materialien und die gemeinsame Situation aufmerksam wahr.'],
            ['sprachkompetenz', 'Sprachkompetenz', 'sozial', 'Ich verstehe mündliche Aufgaben und kann meine Gedanken verständlich formulieren.'],
        ];

        $categoryOrder = ['persoenlich' => 0, 'praktisch' => 100, 'methodisch' => 200, 'sozial' => 300];
        $genericRatings = [
            'Kaum erkennbar; umfassende Unterstützung ist erforderlich.',
            'Teilweise erkennbar; häufige Unterstützung ist erforderlich.',
            'Überwiegend angemessen; gelegentliche Unterstützung ist erforderlich.',
            'Sicher, selbstständig und wiederholt erkennbar.',
            'Besonders sicher, selbstständig und auch in anspruchsvollen Situationen erkennbar.',
        ];

        $withinCategory = [];
        return collect($definitions)->map(function (array $definition) use (&$withinCategory, $categoryOrder, $genericRatings) {
            [$key, $label, $category, $selfText] = $definition;
            $withinCategory[$category] = ($withinCategory[$category] ?? 0) + 1;

            return [
                'key' => $key,
                'label' => $label,
                'kategorie' => $category,
                'kategorie_label' => self::CATEGORIES[$category]['label'],
                'kategorie_code' => self::CATEGORIES[$category]['code'],
                'beschreibung' => null,
                'selbsteinschaetzung_text' => $selfText,
                'bewertungsbeschreibungen' => $genericRatings,
                'sort_order' => $categoryOrder[$category] + $withinCategory[$category],
                'aktiv' => true,
            ];
        })->all();
    }

    private function hametExercises(): array
    {
        return [
            ['key' => 'schrauben', 'name' => 'Schrauben', 'berechnungsregel' => 'zeit', 'rohwerte' => ['zeit'], 'sort_order' => 10, 'beschreibung' => 'Hauptsächlich Zeit; Norm- oder Grenzwerte müssen fachlich hinterlegt werden.'],
            ['key' => 'mutternbrett', 'name' => 'Mutternbrett', 'berechnungsregel' => 'zeit', 'rohwerte' => ['zeit'], 'sort_order' => 20, 'beschreibung' => 'Hauptsächlich Zeit; Norm- oder Grenzwerte müssen fachlich hinterlegt werden.'],
            ['key' => 'pinsel', 'name' => 'Pinsel', 'berechnungsregel' => 'fehler_abzug', 'rohwerte' => ['fehler', 'qualitaet'], 'sort_order' => 30, 'beschreibung' => 'Qualitätsübung: Ergebnispunkte = Maximalpunkte minus Fehler × Abzug. Die Zeit ist nicht relevant.'],
            ['key' => 'schere', 'name' => 'Schere', 'berechnungsregel' => 'fehler_abzug', 'rohwerte' => ['fehler', 'qualitaet'], 'sort_order' => 40, 'beschreibung' => 'Qualitätsübung: Ergebnispunkte = Maximalpunkte minus Fehler × Abzug. Die Zeit ist nicht relevant.'],
            ['key' => 'spiegelbilder', 'name' => 'Spiegelbilder', 'berechnungsregel' => 'fehler_abzug', 'rohwerte' => ['fehler', 'qualitaet'], 'sort_order' => 50, 'beschreibung' => 'Qualitätsübung: Ergebnispunkte = Maximalpunkte minus Fehler × Abzug. Die Zeit ist nicht relevant.'],
            ['key' => 'fisch', 'name' => 'Fisch', 'berechnungsregel' => 'fehler_abzug', 'rohwerte' => ['fehler', 'qualitaet'], 'sort_order' => 60, 'beschreibung' => 'Qualitätsübung: Bewertet werden ausschließlich Qualität beziehungsweise Fehler.'],
            ['key' => 'masse', 'name' => 'Maße', 'berechnungsregel' => 'fehler_abzug', 'rohwerte' => ['fehler', 'qualitaet'], 'sort_order' => 70, 'beschreibung' => 'Qualitätsübung: Ergebnispunkte = Maximalpunkte minus Fehler × Abzug. Die Zeit ist nicht relevant.'],
            ['key' => 'wohngemeinschaft', 'name' => 'Wohngemeinschaft', 'berechnungsregel' => 'beobachtung', 'rohwerte' => ['beobachtung'], 'sort_order' => 80, 'beschreibung' => 'Soziale Gruppenübung; individuelle Beobachtung auf der Skala 1–5.'],
            ['key' => 'turmbau', 'name' => 'Turmbau', 'berechnungsregel' => 'beobachtung', 'rohwerte' => ['gruppenergebnis', 'beobachtung'], 'sort_order' => 90, 'beschreibung' => 'Soziale und methodische Gruppenübung; Gruppenergebnis ersetzt keine individuelle Beobachtung.'],
            ['key' => 'ei_fall', 'name' => 'Ei-Fall', 'berechnungsregel' => 'beobachtung', 'rohwerte' => ['gruppenergebnis', 'beobachtung'], 'sort_order' => 100, 'beschreibung' => 'Soziale, kreative und methodische Gruppenübung.'],
        ];
    }

    private function hametWeights(): array
    {
        return [
            'motivation' => ['schrauben' => 25, 'mutternbrett' => 25, 'fisch' => 25, 'masse' => 25],
            'geduld' => ['schrauben' => 25, 'mutternbrett' => 25, 'fisch' => 25, 'masse' => 25],
            'ausdauer_persistenz' => ['schrauben' => 30, 'mutternbrett' => 30, 'fisch' => 20, 'masse' => 20],
            'kreativitaet' => ['turmbau' => 50, 'ei_fall' => 50],
            'selbstreflexionsfaehigkeit' => ['schrauben' => 15, 'mutternbrett' => 15, 'pinsel' => 20, 'schere' => 20, 'fisch' => 30],
            'gewissenhaftigkeit' => ['pinsel' => 30, 'schere' => 30, 'masse' => 40],
            'grobmotorik' => ['fisch' => 100],
            'feinmotorik' => ['schrauben' => 15, 'mutternbrett' => 15, 'pinsel' => 15, 'schere' => 15, 'spiegelbilder' => 20, 'masse' => 20],
            'raeumliches_vorstellungsvermoegen' => ['spiegelbilder' => 100],
            'strukturierte_vorgehensweise' => ['schrauben' => 10, 'mutternbrett' => 10, 'spiegelbilder' => 15, 'fisch' => 15, 'masse' => 15, 'turmbau' => 20, 'ei_fall' => 15],
            'analyse_problemloesefaehigkeit' => ['spiegelbilder' => 20, 'fisch' => 30, 'turmbau' => 25, 'ei_fall' => 25],
            'aufgabenverstaendnis' => ['spiegelbilder' => 25, 'masse' => 25, 'wohngemeinschaft' => 15, 'turmbau' => 20, 'ei_fall' => 15],
            'teamfaehigkeit' => ['wohngemeinschaft' => 40, 'turmbau' => 30, 'ei_fall' => 30],
            'kommunikationsfaehigkeit' => ['wohngemeinschaft' => 40, 'turmbau' => 30, 'ei_fall' => 30],
            'aufmerksamkeit_achtsamkeit' => ['wohngemeinschaft' => 30, 'turmbau' => 35, 'ei_fall' => 35],
            'sprachkompetenz' => ['wohngemeinschaft' => 40, 'turmbau' => 30, 'ei_fall' => 30],
        ];
    }
}
