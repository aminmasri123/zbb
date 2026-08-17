<?php

namespace App\Http\Controllers;

use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Personen;
use App\Models\PotenzialanalyseBericht;
use App\Models\PotenzialanalyseBeurteilung;
use App\Models\PotenzialanalyseKompetenzbewertung;
use App\Models\PotenzialanalyseKriterium;
use App\Models\PotenzialanalyseSelbsteinschaetzung;
use App\Models\PotenzialanalyseUebung;
use App\Models\PotenzialanalyseUebungKompetenz;
use App\Models\PotenzialanalyseUebungErgebnis;
use App\Models\Projekt;
use App\Services\Bop\PotenzialanalyseReportService;
use App\Services\PotenzialanalyseScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;

class PotenzialanalyseController extends Controller
{
    private const PA_MERKMALE = [
        'feinmotorik',
        'grobmotorik',
        'wahrnehmung_symmetrie',
        'analyse_problemloesefaehigkeit',
        'arbeitsplanung',
        'motivation_leistungsbereitschaft',
        'durchhaltevermoegen',
        'sorgfalt',
        'kommunikation',
        'teamfaehigkeit',
        'umgangsformen',
    ];

    public function __construct(
        private readonly PotenzialanalyseScoringService $scoring,
    ) {
    }

    public function storeUebung(Request $request, Projekt $projekt)
    {
        $this->authorizeProjectConfig($projekt);
        $this->ensureProjektUsesPotenzialanalyse($projekt);

        $validated = $this->validateUebung($request, $projekt);

        DB::transaction(function () use ($validated, $projekt) {
            $kompetenzen = $validated['kompetenzen'];
            unset($validated['kompetenzen']);

            $uebung = PotenzialanalyseUebung::create([
                ...$validated,
                'projekt_id' => $projekt->id,
            ]);
            $this->syncUebungKompetenzen($uebung, $kompetenzen);
        });

        return response()->json([
            'message' => 'Übung wurde angelegt.',
            'uebungen' => $this->projektUebungen($projekt),
        ], 201);
    }

    public function updateUebung(Request $request, PotenzialanalyseUebung $uebung)
    {
        $uebung->load('projekt');
        $this->authorizeProjectConfig($uebung->projekt);

        $validated = $this->validateUebung($request, $uebung->projekt);

        DB::transaction(function () use ($validated, $uebung) {
            $kompetenzen = $validated['kompetenzen'];
            unset($validated['kompetenzen']);
            $uebung->update($validated);
            $this->syncUebungKompetenzen($uebung, $kompetenzen);
        });

        return response()->json([
            'message' => 'Übung wurde aktualisiert.',
            'uebungen' => $this->projektUebungen($uebung->projekt),
        ]);
    }

    public function updateGewichtungsmatrix(Request $request, Projekt $projekt)
    {
        $this->authorizeProjectConfig($projekt);
        $this->ensureProjektUsesPotenzialanalyse($projekt);

        $validated = $request->validate([
            'uebungen' => ['required', 'array'],
            'uebungen.*.id' => ['required', 'integer', 'distinct'],
            'uebungen.*.kompetenzen' => ['present', 'array'],
            'uebungen.*.kompetenzen.*.merkmal' => ['required', Rule::in(self::PA_MERKMALE)],
            'uebungen.*.kompetenzen.*.gewichtung' => ['required', 'numeric', 'gt:0', 'max:100'],
        ]);

        $uebungen = $projekt->potenzialanalyseUebungen()->get()->keyBy('id');
        $gesendeteIds = collect($validated['uebungen'])->pluck('id')->map(fn ($id) => (int) $id);

        if ($gesendeteIds->count() !== $uebungen->count()
            || $gesendeteIds->diff($uebungen->keys())->isNotEmpty()
            || $uebungen->keys()->diff($gesendeteIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'uebungen' => 'Die Gewichtungsmatrix enthält nicht alle Übungen dieses Projekts.',
            ]);
        }

        foreach ($validated['uebungen'] as $index => $zeile) {
            $merkmale = collect($zeile['kompetenzen'])->pluck('merkmal');
            if ($merkmale->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages([
                    "uebungen.{$index}.kompetenzen" => 'Eine Kompetenz darf pro Übung nur einmal vorkommen.',
                ]);
            }
        }

        $summen = array_fill_keys(self::PA_MERKMALE, 0.0);
        foreach ($validated['uebungen'] as $zeile) {
            if (! $uebungen->get((int) $zeile['id'])->aktiv) {
                continue;
            }

            foreach ($zeile['kompetenzen'] as $zuordnung) {
                $summen[$zuordnung['merkmal']] += (float) $zuordnung['gewichtung'];
            }
        }

        $ungueltigeSummen = collect($summen)
            ->filter(fn (float $summe) => $summe > 0 && abs($summe - 100.0) > 0.01);

        if ($ungueltigeSummen->isNotEmpty()) {
            throw ValidationException::withMessages([
                'gewichtungsmatrix' => 'Jede verwendete Kompetenz muss insgesamt genau 100 % ergeben.',
            ]);
        }

        DB::transaction(function () use ($validated, $uebungen) {
            foreach ($validated['uebungen'] as $zeile) {
                $this->syncUebungKompetenzen(
                    $uebungen->get((int) $zeile['id']),
                    collect($zeile['kompetenzen'])
                        ->map(fn (array $zuordnung) => [...$zuordnung, 'aktiv' => true])
                        ->all(),
                );
            }
        });

        return response()->json([
            'message' => 'Gewichtungsmatrix wurde gespeichert.',
            'uebungen' => $this->projektUebungen($projekt),
        ]);
    }

    public function destroyUebung(PotenzialanalyseUebung $uebung)
    {
        $uebung->load('projekt');
        $this->authorizeProjectConfig($uebung->projekt);
        $projekt = $uebung->projekt;

        $uebung->delete();

        return response()->json([
            'message' => 'Übung wurde gelöscht.',
            'uebungen' => $this->projektUebungen($projekt),
        ]);
    }

    public function storeKriterium(Request $request, PotenzialanalyseUebung $uebung)
    {
        $uebung->load('projekt');
        $this->authorizeProjectConfig($uebung->projekt);

        PotenzialanalyseKriterium::create([
            ...$this->validateKriterium($request),
            'uebung_id' => $uebung->id,
        ]);

        return response()->json([
            'message' => 'Kriterium wurde angelegt.',
            'uebungen' => $this->projektUebungen($uebung->projekt),
        ], 201);
    }

    public function updateKriterium(Request $request, PotenzialanalyseKriterium $kriterium)
    {
        $kriterium->load('uebung.projekt');
        $this->authorizeProjectConfig($kriterium->uebung->projekt);

        $kriterium->update($this->validateKriterium($request));

        return response()->json([
            'message' => 'Kriterium wurde aktualisiert.',
            'uebungen' => $this->projektUebungen($kriterium->uebung->projekt),
        ]);
    }

    public function destroyKriterium(PotenzialanalyseKriterium $kriterium)
    {
        $kriterium->load('uebung.projekt');
        $this->authorizeProjectConfig($kriterium->uebung->projekt);
        $projekt = $kriterium->uebung->projekt;

        $kriterium->delete();

        return response()->json([
            'message' => 'Kriterium wurde gelöscht.',
            'uebungen' => $this->projektUebungen($projekt),
        ]);
    }

    public function updateAuswertungConfig(Request $request, Projekt $projekt)
    {
        $this->authorizeProjectConfig($projekt);
        $this->ensureProjektUsesPotenzialanalyse($projekt);

        $validated = $request->validate([
            'thresholds' => ['required', 'array'],
            'thresholds.rating_2_from' => ['required', 'numeric', 'min:0', 'max:100'],
            'thresholds.rating_3_from' => ['required', 'numeric', 'min:0', 'max:100'],
            'thresholds.rating_4_from' => ['required', 'numeric', 'min:0', 'max:100'],
            'thresholds.rating_5_from' => ['required', 'numeric', 'min:0', 'max:100'],
            'source_weights' => ['required', 'array'],
            'source_weights.exercises' => ['required', 'numeric', 'min:0', 'max:100'],
            'source_weights.coach' => ['required', 'numeric', 'min:0', 'max:100'],
            'source_weights.self' => ['required', 'numeric', 'min:0', 'max:100'],
            'report_style' => ['required', Rule::in(collect(PotenzialanalyseScoringService::REPORT_STYLES)->pluck('value')->all())],
        ]);

        $thresholds = array_values($validated['thresholds']);
        if ($thresholds !== collect($thresholds)->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'thresholds' => 'Die Grenzen der Bewertungsstufen müssen aufsteigend sein.',
            ]);
        }

        if (array_sum($validated['source_weights']) <= 0) {
            throw ValidationException::withMessages([
                'source_weights' => 'Mindestens eine Datenquelle muss gewichtet werden.',
            ]);
        }

        $config = $this->scoring->normalizeConfig($validated);
        $projekt->update(['potenzialanalyse_auswertung_config' => $config]);

        return response()->json([
            'message' => 'Auswertungseinstellungen wurden gespeichert.',
            'config' => $config,
        ]);
    }

    public function generateSuggestions(Request $request, Gruppe $gruppe, Personen $personen)
    {
        $gruppe->loadMissing('projekt');
        abort_unless($this->canUseGroup(auth()->user(), $gruppe), 403);
        $this->ensureProjektUsesPotenzialanalyse($gruppe->projekt);
        $this->ensureTeilnehmerInGroup($gruppe, $personen);

        $validated = $request->validate([
            'uebungen' => ['nullable', 'array'],
            'uebungen.*.punkte' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'selbsteinschaetzung' => ['nullable', 'array'],
            'selbsteinschaetzung.*.bewertung' => ['nullable', 'integer', 'min:1', 'max:5'],
            'selbsteinschaetzung.*.bemerkung' => ['nullable', 'string', 'max:5000'],
            'kompetenzen' => ['nullable', 'array'],
            'kompetenzen.*.bewertung' => ['nullable', 'integer', 'min:1', 'max:5'],
            'kompetenzen.*.bemerkung' => ['nullable', 'string', 'max:5000'],
            'bericht' => ['nullable', 'array'],
            'bericht.staerken' => ['nullable', 'string'],
            'bericht.entwicklungsfelder' => ['nullable', 'string'],
            'bericht.empfehlung' => ['nullable', 'string'],
            'style' => ['nullable', Rule::in(collect(PotenzialanalyseScoringService::REPORT_STYLES)->pluck('value')->all())],
            'variation' => ['nullable', 'integer', 'min:1'],
        ]);

        $config = $this->scoring->normalizeConfig($gruppe->projekt->potenzialanalyse_auswertung_config);
        $exercises = PotenzialanalyseUebung::query()
            ->where('projekt_id', $gruppe->projekt_id)
            ->where('aktiv', true)
            ->with('kompetenzZuordnungen')
            ->get();
        $exerciseScores = $this->scoring->scoreExercises($exercises, $validated['uebungen'] ?? [], $config);
        $combinedScores = $this->scoring->combinedScores(
            $exerciseScores,
            $validated['kompetenzen'] ?? [],
            $validated['selbsteinschaetzung'] ?? [],
            $config,
        );
        $style = $validated['style'] ?? $config['report_style'];
        $report = $this->scoring->generateReport(
            $personen,
            $combinedScores,
            $exerciseScores,
            $validated['kompetenzen'] ?? [],
            $validated['selbsteinschaetzung'] ?? [],
            $validated['bericht'] ?? [],
            $style,
            (int) ($validated['variation'] ?? 0),
        );

        return response()->json([
            'exercise_scores' => $exerciseScores,
            'combined_scores' => $combinedScores,
            'report' => $report,
            'config' => $config,
        ]);
    }

    public function updateTeilnehmer(Request $request, Gruppe $gruppe, Personen $personen)
    {
        $gruppe->loadMissing('projekt');
        abort_unless($this->canUseGroup(auth()->user(), $gruppe), 403);
        $this->ensureProjektUsesPotenzialanalyse($gruppe->projekt);
        $this->ensureTeilnehmerInGroup($gruppe, $personen);

        $validated = $request->validate([
            'uebungen' => ['nullable', 'array'],
            'uebungen.*.punkte' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'uebungen.*.zeit_min' => ['nullable', 'integer', 'min:0', 'max:999'],
            'uebungen.*.zeit_sec' => ['nullable', 'integer', 'min:0', 'max:59'],
            'uebungen.*.zeit' => ['nullable', 'integer', 'min:0', 'max:59999'],
            'selbsteinschaetzung' => ['nullable', 'array'],
            'selbsteinschaetzung.*.bewertung' => ['nullable', 'integer', 'min:1', 'max:5'],
            'selbsteinschaetzung.*.bemerkung' => ['nullable', 'string', 'max:5000'],
            'kompetenzen' => ['nullable', 'array'],
            'kompetenzen.*.bewertung' => ['nullable', 'integer', 'min:1', 'max:5'],
            'kompetenzen.*.bemerkung' => ['nullable', 'string', 'max:5000'],
            'merkmale_snapshot' => ['nullable', 'array'],
            'merkmale_snapshot.selbsteinschaetzung' => ['nullable', 'array'],
            'merkmale_snapshot.selbsteinschaetzung.*.bewertung' => ['nullable', 'integer', 'min:1', 'max:5'],
            'merkmale_snapshot.selbsteinschaetzung.*.bemerkung' => ['nullable', 'string', 'max:5000'],
            'merkmale_snapshot.kompetenzen' => ['nullable', 'array'],
            'merkmale_snapshot.kompetenzen.*.bewertung' => ['nullable', 'integer', 'min:1', 'max:5'],
            'merkmale_snapshot.kompetenzen.*.bemerkung' => ['nullable', 'string', 'max:5000'],
            'beurteilungen' => ['nullable', 'array'],
            'beurteilungen.*.bewertung' => ['nullable', 'integer', 'min:1', 'max:5'],
            'beurteilungen.*.bemerkung' => ['nullable', 'string', 'max:5000'],
            'selbsteinschaetzungen' => ['nullable', 'array'],
            'selbsteinschaetzungen.*.bewertung' => ['nullable', 'integer', 'min:1', 'max:5'],
            'selbsteinschaetzungen.*.bemerkung' => ['nullable', 'string', 'max:5000'],
            'bericht' => ['nullable', 'array'],
            'bericht.status' => ['nullable', Rule::in(['entwurf', 'in_bearbeitung', 'fertig', 'geprueft'])],
            'bericht.staerken' => ['nullable', 'string'],
            'bericht.entwicklungsfelder' => ['nullable', 'string'],
            'bericht.empfehlung' => ['nullable', 'string'],
            'bericht.bericht_text' => ['nullable', 'string'],
            'bericht.generator_stil' => ['nullable', Rule::in(collect(PotenzialanalyseScoringService::REPORT_STYLES)->pluck('value')->all())],
            'bericht.generator_snapshot' => ['nullable', 'array'],
        ]);

        $kriteriumIds = $this->projektKriteriumIds((int) $gruppe->projekt_id);
        $uebungen = $this->projektUebungenMap((int) $gruppe->projekt_id);
        $selbsteinschaetzung = $this->normalizeMerkmalEntries(
            $request->input('selbsteinschaetzung', []),
            $request->input('merkmale_snapshot.selbsteinschaetzung', [])
        );
        $kompetenzen = $this->normalizeMerkmalEntries(
            $request->input('kompetenzen', []),
            $request->input('merkmale_snapshot.kompetenzen', [])
        );

        DB::transaction(function () use ($gruppe, $personen, $validated, $kriteriumIds, $uebungen, $selbsteinschaetzung, $kompetenzen) {
            $this->syncUebungErgebnisse(
                $validated['uebungen'] ?? [],
                $gruppe,
                $personen,
                $uebungen
            );

            $this->syncKompetenzbewertungen(
                'selbst',
                $selbsteinschaetzung,
                $gruppe,
                $personen
            );

            $this->syncKompetenzbewertungen(
                'anleiter',
                $kompetenzen,
                $gruppe,
                $personen
            );

            $this->syncBewertungen(
                PotenzialanalyseBeurteilung::class,
                $validated['beurteilungen'] ?? [],
                $gruppe,
                $personen,
                $kriteriumIds,
                false
            );

            $this->syncBewertungen(
                PotenzialanalyseSelbsteinschaetzung::class,
                $validated['selbsteinschaetzungen'] ?? [],
                $gruppe,
                $personen,
                $kriteriumIds,
                true
            );

            $this->syncBericht($validated['bericht'] ?? [], $gruppe, $personen);
        });

        return response()->json([
            'message' => 'Potenzialanalyse wurde gespeichert.',
            'teilnehmer' => $this->teilnehmerPayload($gruppe, $personen->id),
        ]);
    }

    public function destroyTeilnehmerDaten(Gruppe $gruppe, Personen $personen)
    {
        $gruppe->loadMissing('projekt');
        abort_unless($this->canUseGroup(auth()->user(), $gruppe), 403);
        $this->ensureProjektUsesPotenzialanalyse($gruppe->projekt);
        $this->ensureTeilnehmerInGroup($gruppe, $personen);

        $deleted = DB::transaction(function () use ($gruppe, $personen): int {
            $filters = [
                'gruppe_id' => $gruppe->id,
                'personen_id' => $personen->id,
            ];

            return PotenzialanalyseBericht::query()->where($filters)->delete()
                + PotenzialanalyseKompetenzbewertung::query()->where($filters)->delete()
                + PotenzialanalyseUebungErgebnis::query()->where($filters)->delete()
                + PotenzialanalyseBeurteilung::query()->where($filters)->delete()
                + PotenzialanalyseSelbsteinschaetzung::query()->where($filters)->delete();
        });

        return response()->json([
            'message' => $deleted > 0
                ? 'Potenzialanalyse-Daten wurden gelöscht.'
                : 'Es waren keine Potenzialanalyse-Daten vorhanden.',
            'teilnehmer' => $this->teilnehmerPayload($gruppe, $personen->id),
        ]);
    }

    public function downloadTeilnehmerBericht(
        Gruppe $gruppe,
        Personen $personen,
        PotenzialanalyseReportService $reports
    ) {
        $gruppe->loadMissing('projekt');
        abort_unless($this->canUseGroup(auth()->user(), $gruppe), 403);
        $this->ensureProjektUsesPotenzialanalyse($gruppe->projekt);
        $this->ensureTeilnehmerInGroup($gruppe, $personen);

        $filename = $reports->fileName($personen, 'pdf', $gruppe);

        return response($reports->renderPdf($gruppe, $personen), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
                iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename) ?: 'Bericht-PA.pdf'
            ),
        ]);
    }

    public function downloadGruppenBerichte(
        Gruppe $gruppe,
        PotenzialanalyseReportService $reports
    ) {
        $gruppe->loadMissing('projekt');
        abort_unless($this->canUseGroup(auth()->user(), $gruppe), 403);
        $this->ensureProjektUsesPotenzialanalyse($gruppe->projekt);

        $archive = $reports->createGroupPdf($gruppe);

        return response()->download($archive['path'], $archive['name'])->deleteFileAfterSend(true);
    }

    private function validateUebung(Request $request, Projekt $projekt): array
    {
        $maxTag = max(1, (int) ($projekt->potenzialanalyse_tage ?: 60));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'tag' => ['nullable', 'integer', 'min:1', 'max:' . $maxTag],
            'beschreibung' => ['nullable', 'string'],
            'hoechstwert' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'auswertbar' => ['nullable', 'boolean'],
            'ergebnis_typ' => ['nullable', Rule::in(['punkte', 'prozent', 'skala'])],
            'mindestwert' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'kompetenzen' => ['nullable', 'array'],
            'kompetenzen.*.merkmal' => ['required', Rule::in(self::PA_MERKMALE), 'distinct'],
            'kompetenzen.*.gewichtung' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'kompetenzen.*.aktiv' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'aktiv' => ['nullable', 'boolean'],
        ]) + [
            'hoechstwert' => null,
            'auswertbar' => false,
            'ergebnis_typ' => 'punkte',
            'mindestwert' => 0,
            'kompetenzen' => [],
            'sort_order' => 0,
            'aktiv' => true,
        ];

        $maximum = $validated['ergebnis_typ'] === 'prozent' ? 100 : $validated['hoechstwert'];
        if ($validated['auswertbar'] && ($maximum === null || (float) $maximum <= (float) $validated['mindestwert'])) {
            throw ValidationException::withMessages([
                'hoechstwert' => 'Der Höchstwert muss für auswertbare Übungen größer als der Mindestwert sein.',
            ]);
        }

        if ($validated['ergebnis_typ'] === 'prozent') {
            $validated['hoechstwert'] = 100;
        }

        return $validated;
    }

    private function syncUebungKompetenzen(PotenzialanalyseUebung $uebung, array $entries): void
    {
        $uebung->kompetenzZuordnungen()->delete();

        foreach ($entries as $entry) {
            PotenzialanalyseUebungKompetenz::create([
                'uebung_id' => $uebung->id,
                'merkmal' => $entry['merkmal'],
                'gewichtung' => $entry['gewichtung'],
                'aktiv' => $entry['aktiv'] ?? true,
            ]);
        }
    }

    private function validateKriterium(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'beschreibung' => ['nullable', 'string'],
            'skala_min' => ['nullable', 'integer', 'min:1', 'max:10'],
            'skala_max' => ['nullable', 'integer', 'min:1', 'max:10'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'aktiv' => ['nullable', 'boolean'],
        ]) + [
            'skala_min' => 1,
            'skala_max' => 5,
            'sort_order' => 0,
            'aktiv' => true,
        ];

        if ((int) $validated['skala_max'] < (int) $validated['skala_min']) {
            throw ValidationException::withMessages([
                'skala_max' => 'Die maximale Skala darf nicht kleiner als die minimale Skala sein.',
            ]);
        }

        return $validated;
    }

    private function syncUebungErgebnisse(
        array $entries,
        Gruppe $gruppe,
        Personen $personen,
        Collection $uebungen
    ): void {
        $gesendeteUebungIds = collect(array_keys($entries))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $uebungen->has($id))
            ->values();

        $fehlendeErgebnisse = PotenzialanalyseUebungErgebnis::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $personen->id);

        if ($gesendeteUebungIds->isEmpty()) {
            $fehlendeErgebnisse->delete();
        } else {
            $fehlendeErgebnisse->whereNotIn('uebung_id', $gesendeteUebungIds)->delete();
        }

        foreach ($entries as $uebungId => $entry) {
            $uebungId = (int) $uebungId;
            $uebung = $uebungen->get($uebungId);

            if (! $uebung) {
                continue;
            }

            $punkte = $entry['punkte'] ?? null;
            $punkte = $punkte === '' ? null : $punkte;

            if ($punkte !== null) {
                $punkte = (int) $punkte;

                if ($uebung->hoechstwert !== null && $punkte > (float) $uebung->hoechstwert) {
                    throw ValidationException::withMessages([
                        "uebungen.$uebungId.punkte" => "Die Punkte für {$uebung->name} dürfen maximal {$uebung->hoechstwert} betragen.",
                    ]);
                }


                if ($punkte < (float) ($uebung->mindestwert ?? 0)) {
                    throw ValidationException::withMessages([
                        "uebungen.$uebungId.punkte" => "Der Wert für {$uebung->name} muss mindestens {$uebung->mindestwert} betragen.",
                    ]);
                }
            }

            $zeit = $this->normalizeUebungZeit($entry);

            if ($punkte === null && $zeit === 0) {
                PotenzialanalyseUebungErgebnis::query()
                    ->where('gruppe_id', $gruppe->id)
                    ->where('personen_id', $personen->id)
                    ->where('uebung_id', $uebungId)
                    ->delete();
                continue;
            }

            PotenzialanalyseUebungErgebnis::updateOrCreate(
                [
                    'gruppe_id' => $gruppe->id,
                    'personen_id' => $personen->id,
                    'uebung_id' => $uebungId,
                ],
                [
                    'user_id' => auth()->id(),
                    'punkte' => $punkte,
                    'zeit' => $zeit,
                ]
            );
        }
    }

    private function syncKompetenzbewertungen(
        string $typ,
        array $entries,
        Gruppe $gruppe,
        Personen $personen
    ): void {
        $vorhandeneMerkmale = array_values(array_intersect(array_keys($entries), self::PA_MERKMALE));
        $fehlendeBewertungen = PotenzialanalyseKompetenzbewertung::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $personen->id)
            ->where('typ', $typ);

        if ($vorhandeneMerkmale === []) {
            $fehlendeBewertungen->delete();
        } else {
            $fehlendeBewertungen->whereNotIn('merkmal', $vorhandeneMerkmale)->delete();
        }

        foreach ($entries as $merkmal => $entry) {
            if (! in_array($merkmal, self::PA_MERKMALE, true)) {
                continue;
            }

            $bewertung = $entry['bewertung'] ?? null;
            $bewertung = $bewertung === '' ? null : $bewertung;
            $bemerkung = $entry['bemerkung'] ?? null;

            if ($bewertung === null && blank($bemerkung)) {
                PotenzialanalyseKompetenzbewertung::query()
                    ->where('gruppe_id', $gruppe->id)
                    ->where('personen_id', $personen->id)
                    ->where('typ', $typ)
                    ->where('merkmal', $merkmal)
                    ->delete();
                continue;
            }

            PotenzialanalyseKompetenzbewertung::updateOrCreate(
                [
                    'gruppe_id' => $gruppe->id,
                    'personen_id' => $personen->id,
                    'typ' => $typ,
                    'merkmal' => $merkmal,
                ],
                [
                    'user_id' => auth()->id(),
                    'bewertung' => $bewertung !== null ? (int) $bewertung : null,
                    'bemerkung' => $bemerkung,
                ]
            );
        }
    }

    private function normalizeMerkmalEntries(mixed $entries, mixed $fallback = []): array
    {
        $normalized = $this->normalizeMerkmalEntrySet($entries);

        if ($normalized !== []) {
            return $normalized;
        }

        return $this->normalizeMerkmalEntrySet($fallback);
    }

    private function normalizeMerkmalEntrySet(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $merkmal => $entry) {
            if (! in_array($merkmal, self::PA_MERKMALE, true) || ! is_array($entry)) {
                continue;
            }

            $bewertung = $entry['bewertung'] ?? null;
            $bewertung = $bewertung === '' ? null : $bewertung;
            $bemerkung = $entry['bemerkung'] ?? null;

            if ($bewertung !== null) {
                $bewertung = (int) $bewertung;

                if ($bewertung < 1 || $bewertung > 5) {
                    throw ValidationException::withMessages([
                        "{$merkmal}.bewertung" => 'Die Bewertung muss zwischen 1 und 5 liegen.',
                    ]);
                }
            }

            if ($bewertung === null && blank($bemerkung)) {
                continue;
            }

            $normalized[$merkmal] = [
                'bewertung' => $bewertung,
                'bemerkung' => $bemerkung,
            ];
        }

        return $normalized;
    }

    private function normalizeUebungZeit(array $entry): int
    {
        if (array_key_exists('zeit', $entry) && $entry['zeit'] !== null && $entry['zeit'] !== '') {
            return max(0, (int) $entry['zeit']);
        }

        $minuten = max(0, (int) ($entry['zeit_min'] ?? 0));
        $sekunden = max(0, (int) ($entry['zeit_sec'] ?? 0));

        return ($minuten * 60) + $sekunden;
    }

    private function syncBewertungen(
        string $modelClass,
        array $entries,
        Gruppe $gruppe,
        Personen $personen,
        Collection $kriteriumIds,
        bool $submitted
    ): void {
        $gesendeteKriteriumIds = collect(array_keys($entries))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $kriteriumIds->contains($id))
            ->values();

        $fehlendeBewertungen = $modelClass::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $personen->id);

        if ($gesendeteKriteriumIds->isEmpty()) {
            $fehlendeBewertungen->delete();
        } else {
            $fehlendeBewertungen->whereNotIn('kriterium_id', $gesendeteKriteriumIds)->delete();
        }

        foreach ($entries as $kriteriumId => $entry) {
            $kriteriumId = (int) $kriteriumId;
            if (! $kriteriumIds->contains($kriteriumId)) {
                continue;
            }

            $bewertung = $entry['bewertung'] ?? null;
            $bemerkung = $entry['bemerkung'] ?? null;

            if (($bewertung === null || $bewertung === '') && blank($bemerkung)) {
                $modelClass::query()
                    ->where('gruppe_id', $gruppe->id)
                    ->where('personen_id', $personen->id)
                    ->where('kriterium_id', $kriteriumId)
                    ->delete();
                continue;
            }

            $payload = [
                'bewertung' => $bewertung,
                'bemerkung' => $bemerkung,
                'user_id' => auth()->id(),
            ];

            if ($submitted) {
                $payload['submitted_at'] = now();
            }

            $modelClass::updateOrCreate(
                [
                    'gruppe_id' => $gruppe->id,
                    'personen_id' => $personen->id,
                    'kriterium_id' => $kriteriumId,
                ],
                $payload
            );
        }
    }

    private function syncBericht(array $berichtData, Gruppe $gruppe, Personen $personen): void
    {
        $status = $berichtData['status'] ?? 'entwurf';
        $hatInhalt = collect(['staerken', 'entwicklungsfelder', 'empfehlung', 'bericht_text'])
            ->contains(fn ($feld) => filled($berichtData[$feld] ?? null))
            || ! empty($berichtData['generator_snapshot'])
            || in_array($status, ['in_bearbeitung', 'fertig', 'geprueft'], true);

        if (! $hatInhalt) {
            PotenzialanalyseBericht::query()
                ->where('gruppe_id', $gruppe->id)
                ->where('personen_id', $personen->id)
                ->delete();
            return;
        }

        $existing = PotenzialanalyseBericht::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $personen->id)
            ->first();

        PotenzialanalyseBericht::updateOrCreate(
            [
                'gruppe_id' => $gruppe->id,
                'personen_id' => $personen->id,
            ],
            [
                'user_id' => auth()->id(),
                'status' => $status,
                'staerken' => $berichtData['staerken'] ?? null,
                'entwicklungsfelder' => $berichtData['entwicklungsfelder'] ?? null,
                'empfehlung' => $berichtData['empfehlung'] ?? null,
                'bericht_text' => $berichtData['bericht_text'] ?? null,
                'generator_stil' => $berichtData['generator_stil'] ?? null,
                'generator_snapshot' => $berichtData['generator_snapshot'] ?? null,
                'fertiggestellt_at' => in_array($status, ['fertig', 'geprueft'], true)
                    ? ($existing?->fertiggestellt_at ?? now())
                    : null,
            ]
        );
    }

    private function projektUebungen(Projekt $projekt): Collection
    {
        return $projekt->fresh()
            ->potenzialanalyseUebungen()
            ->with(['kriterien', 'kompetenzZuordnungen'])
            ->get();
    }

    private function projektKriteriumIds(int $projektId): Collection
    {
        return PotenzialanalyseKriterium::query()
            ->whereHas('uebung', fn ($query) => $query->where('projekt_id', $projektId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    private function projektUebungenMap(int $projektId): Collection
    {
        return PotenzialanalyseUebung::query()
            ->where('projekt_id', $projektId)
            ->with('kompetenzZuordnungen')
            ->get()
            ->keyBy('id');
    }

    private function teilnehmerPayload(Gruppe $gruppe, int $personenId): array
    {
        return [
            'uebungen' => $this->uebungErgebnissePayload($gruppe, $personenId),
            'selbsteinschaetzung' => $this->kompetenzbewertungenPayload($gruppe, $personenId, 'selbst'),
            'kompetenzen' => $this->kompetenzbewertungenPayload($gruppe, $personenId, 'anleiter'),
            'beurteilungen' => PotenzialanalyseBeurteilung::query()
                ->where('gruppe_id', $gruppe->id)
                ->where('personen_id', $personenId)
                ->get()
                ->keyBy('kriterium_id')
                ->map(fn ($entry) => [
                    'bewertung' => $entry->bewertung,
                    'bemerkung' => $entry->bemerkung,
                ])
                ->all(),
            'selbsteinschaetzungen' => PotenzialanalyseSelbsteinschaetzung::query()
                ->where('gruppe_id', $gruppe->id)
                ->where('personen_id', $personenId)
                ->get()
                ->keyBy('kriterium_id')
                ->map(fn ($entry) => [
                    'bewertung' => $entry->bewertung,
                    'bemerkung' => $entry->bemerkung,
                ])
                ->all(),
            'bericht' => PotenzialanalyseBericht::query()
                ->where('gruppe_id', $gruppe->id)
                ->where('personen_id', $personenId)
                ->first()?->only([
                    'status',
                    'staerken',
                    'entwicklungsfelder',
                    'empfehlung',
                    'bericht_text',
                    'generator_stil',
                    'generator_snapshot',
                    'fertiggestellt_at',
                ]) ?? [
                    'status' => 'entwurf',
                    'staerken' => null,
                    'entwicklungsfelder' => null,
                    'empfehlung' => null,
                    'bericht_text' => null,
                    'generator_stil' => null,
                    'generator_snapshot' => null,
                    'fertiggestellt_at' => null,
            ],
        ];
    }

    private function uebungErgebnissePayload(Gruppe $gruppe, int $personenId): array
    {
        return PotenzialanalyseUebungErgebnis::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $personenId)
            ->get()
            ->keyBy('uebung_id')
            ->map(fn ($entry) => $this->formatUebungErgebnis($entry))
            ->all();
    }

    private function kompetenzbewertungenPayload(Gruppe $gruppe, int $personenId, string $typ): array
    {
        return PotenzialanalyseKompetenzbewertung::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $personenId)
            ->where('typ', $typ)
            ->get()
            ->keyBy('merkmal')
            ->map(fn ($entry) => [
                'bewertung' => $entry->bewertung,
                'bemerkung' => $entry->bemerkung,
            ])
            ->all();
    }

    private function formatUebungErgebnis(PotenzialanalyseUebungErgebnis $entry): array
    {
        $zeit = (int) ($entry->zeit ?? 0);

        return [
            'punkte' => $entry->punkte,
            'zeit' => $zeit,
            'zeit_min' => intdiv($zeit, 60),
            'zeit_sec' => $zeit % 60,
        ];
    }

    private function authorizeProjectConfig(?Projekt $projekt): void
    {
        abort_unless($projekt, 404);

        $user = auth()->user();
        abort_unless($user?->can('potenzialanalyse.manage'), 403);
    }

    private function ensureProjektUsesPotenzialanalyse(?Projekt $projekt): void
    {
        if (! $projekt?->potenzialanalyse_aktiv) {
            throw ValidationException::withMessages([
                'potenzialanalyse_aktiv' => 'Dieses Projekt nutzt keine Potenzialanalyse.',
            ]);
        }
    }

    private function ensureTeilnehmerInGroup(Gruppe $gruppe, Personen $personen): void
    {
        $exists = GruppeHasPersonen::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $personen->id)
            ->exists();

        abort_unless($exists, 404);
    }

    private function canUseGroup($user, ?Gruppe $gruppe): bool
    {
        if (!$user || !$gruppe) {
            return false;
        }

        if ($user->can('gruppe.view.all') || $user->can('projekt.mitarbeiter.view.all')) {
            return true;
        }

        return (int) $gruppe->personen_id === (int) $this->userPersonId($user);
    }

    private function userPersonId($user): ?int
    {
        return $user?->person_id ?? $user?->person?->id;
    }
}
