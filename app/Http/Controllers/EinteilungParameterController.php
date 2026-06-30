<?php

namespace App\Http\Controllers;

use App\Models\Anwesenheitsstatuten;
use App\Models\Bereichsauswahl;
use App\Models\BereichsauswahlSetting;
use App\Models\EinteilungBereiche;
use App\Models\EinteilungSetting;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Partner;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\Tage;
use App\Models\Zeiten;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EinteilungParameterController extends Controller
{
    private const DEFAULT_RUNDEN_ANZAHL = 3;
    private const DEFAULT_KAPAZITAET = 15;
    private const MIN_RUNDEN = 2;
    private const MAX_RUNDEN = 5;
    private const MIN_AUSWAHL = 2;
    private const MAX_AUSWAHL = 4;

    public function index($partnerId, $schuljahr, $teil)
    {
        return Inertia::render(
            'Teilnehmer/Einteilung/Index',
            $this->pagePayload((int) $partnerId, (string) $schuljahr, (string) $teil)
        );
    }

    public function updateParameter(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'schuljahr' => ['required', 'string'],
            'teil' => ['required', 'string'],
            'runden_anzahl' => ['required', 'integer', 'min:' . self::MIN_RUNDEN, 'max:' . self::MAX_RUNDEN],
            'auswahl_anzahl' => ['required', 'integer', 'min:' . self::MIN_AUSWAHL, 'max:' . self::MAX_AUSWAHL],
            'standard_kapazitaet' => ['required', 'integer', 'min:0', 'max:999'],
            'kapazitaeten' => ['nullable', 'array'],
            'kapazitaeten.*' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $partnerId = (int) $validated['partner_id'];
        $schuljahr = (string) $validated['schuljahr'];
        $teil = (string) $validated['teil'];
        $projekt = $this->currentProjekt();
        $bereiche = $this->projektBereiche($projekt);
        $rundenAnzahl = $this->normalizeRundenAnzahl((int) $validated['runden_anzahl']);
        $auswahlAnzahl = $this->normalizeAuswahlAnzahl((int) $validated['auswahl_anzahl']);
        $standardKapazitaet = (int) $validated['standard_kapazitaet'];
        $kapazitaeten = collect($validated['kapazitaeten'] ?? [])
            ->mapWithKeys(fn ($value, $key) => [(int) $key => (int) $value]);

        DB::transaction(function () use (
            $projekt,
            $bereiche,
            $partnerId,
            $schuljahr,
            $teil,
            $rundenAnzahl,
            $auswahlAnzahl,
            $standardKapazitaet,
            $kapazitaeten
        ) {
            $setting = $this->einteilungSettingFor($projekt->id, $partnerId, $schuljahr, $teil, $projekt);
            $setting->update([
                'runden_anzahl' => $rundenAnzahl,
                'standard_kapazitaet' => $standardKapazitaet,
                'user_update' => Auth::id(),
            ]);

            foreach ($bereiche as $bereich) {
                $setting->kapazitaeten()->updateOrCreate(
                    ['bereich_id' => $bereich->id],
                    ['plaetze' => $kapazitaeten->get((int) $bereich->id, $standardKapazitaet)]
                );
            }

            $wahlSetting = $this->bereichsauswahlSettingFor($projekt->id, $partnerId, $schuljahr, $teil, $projekt);
            $wahlSetting->update([
                'auswahl_anzahl' => $auswahlAnzahl,
                'user_update' => Auth::id(),
            ]);

            $this->clearBereichsauswahlFields($partnerId, $schuljahr, $teil, $auswahlAnzahl);
            $this->removeEinteilungenAboveRound($partnerId, $schuljahr, $teil, $rundenAnzahl);
        });

        return $this->payloadResponse('Einteilungs-Parameter wurden gespeichert.', $partnerId, $schuljahr, $teil);
    }

    public function switchRunden(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'schuljahr' => ['required', 'string'],
            'teil' => ['required', 'string'],
            'quelle_runde' => ['required', 'integer'],
            'ziel_runde' => ['required', 'integer'],
        ]);

        $partnerId = (int) $validated['partner_id'];
        $schuljahr = (string) $validated['schuljahr'];
        $teil = (string) $validated['teil'];
        $quelleRunde = (int) $validated['quelle_runde'];
        $zielRunde = (int) $validated['ziel_runde'];

        if ($quelleRunde === $zielRunde) {
            throw ValidationException::withMessages([
                'ziel_runde' => 'Bitte zwei unterschiedliche Runden auswaehlen.',
            ]);
        }

        $projekt = $this->currentProjekt();
        $setting = $this->einteilungSettingFor($projekt->id, $partnerId, $schuljahr, $teil, $projekt);
        $runden = $this->rundenArray($setting->runden_anzahl);

        if (!in_array($quelleRunde, $runden, true) || !in_array($zielRunde, $runden, true)) {
            throw ValidationException::withMessages([
                'quelle_runde' => 'Diese Runde ist in den aktuellen Parametern nicht aktiv.',
            ]);
        }

        $schueler = $this->schuelerQuery($partnerId, $schuljahr, $teil)
            ->with(['person', 'einteilungen'])
            ->get();

        if ($schueler->isEmpty()) {
            throw ValidationException::withMessages([
                'teilnehmer' => 'Derzeit sind keine Teilnehmer in der gewaehlten Schule eingetragen.',
            ]);
        }

        $geaendert = 0;

        DB::transaction(function () use (
            $schueler,
            $partnerId,
            $schuljahr,
            $teil,
            $quelleRunde,
            $zielRunde,
            &$geaendert
        ) {
            foreach ($schueler as $item) {
                $quelleEintrag = $item->einteilungen->firstWhere('runde', $quelleRunde);
                $zielEintrag = $item->einteilungen->firstWhere('runde', $zielRunde);
                $quelleBereichId = $quelleEintrag ? (int) $quelleEintrag->bereich_id : null;
                $zielBereichId = $zielEintrag ? (int) $zielEintrag->bereich_id : null;

                if (!$quelleBereichId && !$zielBereichId) {
                    continue;
                }

                $this->syncGeneratedGroupMembership(
                    $item,
                    $partnerId,
                    $schuljahr,
                    $teil,
                    $quelleRunde,
                    $quelleBereichId,
                    $zielBereichId
                );
                $this->syncGeneratedGroupMembership(
                    $item,
                    $partnerId,
                    $schuljahr,
                    $teil,
                    $zielRunde,
                    $zielBereichId,
                    $quelleBereichId
                );

                if ($quelleEintrag && $zielEintrag) {
                    $quelleEintrag->update(['bereich_id' => $zielBereichId]);
                    $zielEintrag->update(['bereich_id' => $quelleBereichId]);
                } elseif ($quelleEintrag) {
                    $quelleEintrag->update(['runde' => $zielRunde]);
                } elseif ($zielEintrag) {
                    $zielEintrag->update(['runde' => $quelleRunde]);
                }

                $geaendert++;
            }
        });

        return $this->payloadResponse(
            "Runde {$quelleRunde} und Runde {$zielRunde} wurden getauscht ({$geaendert} Teilnehmer).",
            $partnerId,
            $schuljahr,
            $teil
        );
    }

    public function createManual(Request $request)
    {
        $base = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'schuljahr' => ['required', 'string'],
            'teil' => ['required', 'string'],
            'schueler_id' => ['required', 'integer', 'exists:personen_ist_schuelers,id'],
        ]);

        $projekt = $this->currentProjekt();
        $setting = $this->einteilungSettingFor(
            $projekt->id,
            (int) $base['partner_id'],
            (string) $base['schuljahr'],
            (string) $base['teil'],
            $projekt
        );
        $runden = $this->rundenArray($setting->runden_anzahl);
        $roundData = $this->validateRoundFields($request, $runden, true);
        $validated = array_merge($base, $roundData);

        $this->validateDistinctBereiche($validated, true, $runden);
        $this->assertBereicheInProjekt($this->rundeValues($validated, $runden));

        $schueler = $this->schuelerInContext(
            (int) $validated['schueler_id'],
            (int) $validated['partner_id'],
            (string) $validated['schuljahr'],
            (string) $validated['teil']
        );

        if ($schueler->einteilungen()->exists()) {
            throw ValidationException::withMessages([
                'schueler_id' => 'Der Teilnehmer ist bereits eingeteilt.',
            ]);
        }

        $rundeValues = $this->rundeValues($validated, $runden);
        $this->assertKapazitaetenFuerEingabe(
            (int) $validated['partner_id'],
            (string) $validated['schuljahr'],
            (string) $validated['teil'],
            $rundeValues
        );

        DB::transaction(function () use ($schueler, $validated, $runden, $rundeValues) {
            foreach ($runden as $runde) {
                $schueler->einteilungen()->create([
                    'bereich_id' => $validated['runde_' . $runde],
                    'runde' => $runde,
                ]);

                $this->syncGeneratedGroupMembership(
                    $schueler,
                    (int) $validated['partner_id'],
                    (string) $validated['schuljahr'],
                    (string) $validated['teil'],
                    $runde,
                    null,
                    $rundeValues[$runde] ?? null
                );
            }
        });

        return $this->payloadResponse(
            'Einteilung wurde angelegt.',
            (int) $validated['partner_id'],
            (string) $validated['schuljahr'],
            (string) $validated['teil']
        );
    }

    public function einteilen(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'schuljahr' => ['required', 'string'],
            'teil' => ['required', 'string'],
        ]);

        $partnerId = (int) $validated['partner_id'];
        $schuljahr = (string) $validated['schuljahr'];
        $teil = (string) $validated['teil'];
        $projekt = $this->currentProjekt();
        $setting = $this->einteilungSettingFor($projekt->id, $partnerId, $schuljahr, $teil, $projekt);
        $wahlSetting = $this->bereichsauswahlSettingFor($projekt->id, $partnerId, $schuljahr, $teil, $projekt);
        $bereiche = $this->projektBereiche($projekt);
        $bereichIds = $bereiche->pluck('id')->map(fn ($id) => (int) $id)->values();
        $runden = $this->rundenArray($setting->runden_anzahl);
        $kapazitaeten = $this->kapazitaetenFor($setting, $bereiche);

        $positiveBereiche = collect($kapazitaeten)->filter(fn ($plaetze) => (int) $plaetze > 0);
        if ($positiveBereiche->count() < count($runden)) {
            throw ValidationException::withMessages([
                'kapazitaeten' => 'Fuer ' . count($runden) . ' Runden werden mindestens ' . count($runden) . ' Bereiche mit freien Plaetzen benoetigt.',
            ]);
        }

        $schueler = $this->schuelerQuery($partnerId, $schuljahr, $teil)
            ->with(['person', 'bereichsauswahl'])
            ->get()
            ->sortBy(fn ($item) => sprintf(
                '%s|%s|%s',
                $item->klasse ?? '',
                $item->person?->nachname ?? '',
                $item->person?->vorname ?? ''
            ))
            ->values();

        if ($schueler->isEmpty()) {
            throw ValidationException::withMessages([
                'teilnehmer' => 'Derzeit sind keine Teilnehmer in der gewaehlten Schule eingetragen.',
            ]);
        }

        if ($schueler->count() > array_sum($kapazitaeten)) {
            throw ValidationException::withMessages([
                'teilnehmer' => 'Die Teilnehmerzahl ist hoeher als die Summe der freien Plaetze pro Runde.',
            ]);
        }

        $teilnehmerOhneAuswahl = [];

        DB::transaction(function () use (
            $schueler,
            $bereichIds,
            $runden,
            $kapazitaeten,
            $wahlSetting,
            $partnerId,
            $schuljahr,
            $teil,
            &$teilnehmerOhneAuswahl
        ) {
            $alteEinteilungen = EinteilungBereiche::where('teilnehmende_type', PersonenIstSchueler::class)
                ->whereIn('teilnehmende_id', $schueler->pluck('id'))
                ->get()
                ->groupBy('teilnehmende_id')
                ->map(fn ($items) => $items->pluck('bereich_id', 'runde')->map(fn ($id) => (int) $id)->all());

            EinteilungBereiche::where('teilnehmende_type', PersonenIstSchueler::class)
                ->whereIn('teilnehmende_id', $schueler->pluck('id'))
                ->delete();

            $belegung = [];
            foreach ($runden as $runde) {
                foreach ($bereichIds as $bereichId) {
                    $belegung[$runde][$bereichId] = 0;
                }
            }

            foreach ($schueler as $item) {
                [$gewaehlteBereiche, $fallbackBereiche, $hatAuswahl] = $this->prioritaetenFuerSchueler(
                    $item,
                    $bereichIds,
                    (int) $wahlSetting->auswahl_anzahl
                );

                if (!$hatAuswahl) {
                    $teilnehmerOhneAuswahl[] = $this->teilnehmerName($item);
                }

                $verwendet = [];
                $neueEinteilung = [];

                foreach ($runden as $runde) {
                    $bereichId = $this->waehleBereich($gewaehlteBereiche, $belegung[$runde], $kapazitaeten, $verwendet)
                        ?? $this->waehleBereich($fallbackBereiche, $belegung[$runde], $kapazitaeten, $verwendet);

                    if (!$bereichId) {
                        throw ValidationException::withMessages([
                            'kapazitaeten' => 'Die Kapazitaeten reichen nicht aus, um alle Teilnehmer eindeutig auf die Runden zu verteilen.',
                        ]);
                    }

                    $item->einteilungen()->create([
                        'bereich_id' => $bereichId,
                        'runde' => $runde,
                    ]);

                    $belegung[$runde][$bereichId]++;
                    $verwendet[] = $bereichId;
                    $neueEinteilung[$runde] = $bereichId;
                }

                $alteFuerSchueler = $alteEinteilungen->get($item->id, []);
                foreach ($runden as $runde) {
                    $alt = $alteFuerSchueler[$runde] ?? null;
                    $neu = $neueEinteilung[$runde] ?? null;

                    if ((int) $alt !== (int) $neu) {
                        $this->syncGeneratedGroupMembership($item, $partnerId, $schuljahr, $teil, $runde, $alt, $neu);
                    }
                }
            }
        });

        $message = 'Teilnehmer wurden erfolgreich eingeteilt.';
        if (!empty($teilnehmerOhneAuswahl)) {
            $message .= ' Ohne Bereichsauswahl wurden zufaellig ausgeglichen: ' . implode(', ', $teilnehmerOhneAuswahl);
        }

        return response()->json([
            'message' => $message,
            'teilnehmerOhneAuswahl' => $teilnehmerOhneAuswahl,
            'payload' => $this->pagePayload($partnerId, $schuljahr, $teil),
        ]);
    }

    public function update(Request $request)
    {
        $base = $request->validate([
            'schueler_id' => ['required', 'integer', 'exists:personen_ist_schuelers,id'],
            'seite' => ['nullable', 'string'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'schuljahr' => ['nullable', 'string'],
            'teil' => ['nullable', 'string'],
        ]);

        $hasContext = !empty($base['partner_id']) && !empty($base['schuljahr']) && !empty($base['teil']);
        $projekt = $this->currentProjekt();
        $runden = $hasContext
            ? $this->rundenArray($this->einteilungSettingFor(
                $projekt->id,
                (int) $base['partner_id'],
                (string) $base['schuljahr'],
                (string) $base['teil'],
                $projekt
            )->runden_anzahl)
            : $this->rundenArray(self::MAX_RUNDEN);

        $roundData = $this->validateRoundFields($request, $runden, false);
        $validated = array_merge($base, $roundData);

        $this->validateDistinctBereiche($validated, false, $runden);
        $this->assertBereicheInProjekt($this->rundeValues($validated, $runden));

        $schueler = $hasContext
            ? $this->schuelerInContext(
                (int) $validated['schueler_id'],
                (int) $validated['partner_id'],
                (string) $validated['schuljahr'],
                (string) $validated['teil']
            )
            : PersonenIstSchueler::findOrFail($validated['schueler_id']);

        $rundeValues = $this->rundeValues($validated, $runden);
        if ($hasContext) {
            $this->assertKapazitaetenFuerEingabe(
                (int) $validated['partner_id'],
                (string) $validated['schuljahr'],
                (string) $validated['teil'],
                $rundeValues,
                $schueler->id
            );
        }

        $alteEinteilungen = $schueler->einteilungen()
            ->whereIn('runde', $runden)
            ->pluck('bereich_id', 'runde')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::transaction(function () use ($schueler, $validated, $runden, $rundeValues, $alteEinteilungen, $hasContext) {
            foreach ($runden as $runde) {
                $bereichId = $rundeValues[$runde] ?? null;
                $alterBereichId = $alteEinteilungen[$runde] ?? null;

                if ($hasContext && (int) $alterBereichId !== (int) $bereichId) {
                    $this->syncGeneratedGroupMembership(
                        $schueler,
                        (int) $validated['partner_id'],
                        (string) $validated['schuljahr'],
                        (string) $validated['teil'],
                        $runde,
                        $alterBereichId,
                        $bereichId
                    );
                }

                $eintrag = $schueler->einteilungen()->where('runde', $runde)->first();
                if ($bereichId) {
                    if ($eintrag) {
                        $eintrag->update(['bereich_id' => $bereichId]);
                    } else {
                        $schueler->einteilungen()->create([
                            'bereich_id' => $bereichId,
                            'runde' => $runde,
                        ]);
                    }
                } elseif ($eintrag) {
                    $eintrag->delete();
                }
            }
        });

        return response()->json([
            'message' => 'Einteilung erfolgreich aktualisiert.',
            'schueler_id' => $schueler->id,
            'einteilung_ids' => $schueler->einteilungen()->pluck('bereich_id', 'runde')->toArray(),
            'payload' => $hasContext
                ? $this->pagePayload((int) $validated['partner_id'], (string) $validated['schuljahr'], (string) $validated['teil'])
                : null,
        ]);
    }

    public function destroyContext(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'schuljahr' => ['required', 'string'],
            'teil' => ['required', 'string'],
        ]);

        $partnerId = (int) $validated['partner_id'];
        $schuljahr = (string) $validated['schuljahr'];
        $teil = (string) $validated['teil'];
        $schueler = $this->schuelerQuery($partnerId, $schuljahr, $teil)
            ->with(['person', 'einteilungen'])
            ->get();

        $deleted = 0;
        DB::transaction(function () use ($schueler, $partnerId, $schuljahr, $teil, &$deleted) {
            foreach ($schueler as $item) {
                foreach ($item->einteilungen as $einteilung) {
                    $this->syncGeneratedGroupMembership(
                        $item,
                        $partnerId,
                        $schuljahr,
                        $teil,
                        (int) $einteilung->runde,
                        (int) $einteilung->bereich_id,
                        null
                    );
                    $einteilung->delete();
                    $deleted++;
                }
            }
        });

        return $this->payloadResponse(
            $deleted > 0 ? 'Einteilungen wurden geloescht.' : 'Es waren keine Einteilungen zum Loeschen vorhanden.',
            $partnerId,
            $schuljahr,
            $teil
        );
    }

    public function gruppenGenerieren(Request $request)
    {
        $base = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'schuljahr' => ['required', 'string'],
            'teil' => ['required', 'string'],
        ]);

        $partnerId = (int) $base['partner_id'];
        $schuljahr = (string) $base['schuljahr'];
        $teil = (string) $base['teil'];
        $projekt = $this->currentProjekt();
        $setting = $this->einteilungSettingFor($projekt->id, $partnerId, $schuljahr, $teil, $projekt);
        $runden = $this->rundenArray($setting->runden_anzahl);

        $rules = [
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'schuljahr' => ['required', 'string'],
            'teil' => ['required', 'string'],
            'startzeit' => ['required', 'date_format:H:i'],
            'endzeit' => ['required', 'date_format:H:i', 'after:startzeit'],
            'raum_id' => ['nullable', 'integer', 'exists:raeumes,id'],
            'betreuer_id' => ['nullable', 'integer', 'exists:personens,id'],
            'bereiche' => ['nullable', 'array'],
            'bereiche.*' => ['integer', 'exists:bereiches,id'],
        ];

        foreach ($runden as $runde) {
            $rules['runde' . $runde . 'von'] = ['required', 'date'];
            $rules['runde' . $runde . 'bis'] = ['required', 'date', 'after_or_equal:runde' . $runde . 'von'];
        }

        $validated = $request->validate($rules);
        $projektBereiche = $this->projektBereiche($projekt);
        $selectedBereiche = collect($validated['bereiche'] ?? [])->map(fn ($id) => (int) $id)->values();
        $bereiche = $selectedBereiche->isEmpty()
            ? $projektBereiche
            : $projektBereiche->whereIn('id', $selectedBereiche)->values();

        if ($bereiche->isEmpty()) {
            throw ValidationException::withMessages([
                'bereiche' => 'Bitte mindestens einen Bereich auswaehlen.',
            ]);
        }

        $raumId = $validated['raum_id'] ?? $projekt->raeume->first()?->id;
        $betreuerId = $validated['betreuer_id'] ?? Auth::user()->person_id ?? $projekt->mitarbeiter->first()?->id;

        if (!$raumId) {
            throw ValidationException::withMessages(['raum_id' => 'Bitte einen Raum auswaehlen.']);
        }

        if (!$betreuerId) {
            throw ValidationException::withMessages(['betreuer_id' => 'Bitte einen Betreuer auswaehlen.']);
        }

        $status = $this->defaultAnwesenheitsstatus();
        $schueler = $this->schuelerQuery($partnerId, $schuljahr, $teil)->get();
        $schuelerIds = $schueler->pluck('id');
        $schuelerNachId = $schueler->keyBy('id');
        $zeitGeplant = Zeiten::firstOrCreate([
            'startzeit' => $validated['startzeit'],
            'endzeit' => $validated['endzeit'],
        ]);
        $zeitTatsaechlich = Zeiten::firstOrCreate([
            'startzeit' => $validated['startzeit'],
            'endzeit' => $validated['endzeit'],
        ]);

        $gruppenAnzahl = 0;
        $pivotAnzahl = 0;

        DB::transaction(function () use (
            $validated,
            $projekt,
            $bereiche,
            $schuelerIds,
            $schuelerNachId,
            $raumId,
            $betreuerId,
            $zeitGeplant,
            $zeitTatsaechlich,
            $status,
            $runden,
            &$gruppenAnzahl,
            &$pivotAnzahl
        ) {
            foreach ($runden as $runde) {
                $start = Carbon::parse($validated['runde' . $runde . 'von'])->startOfDay();
                $end = Carbon::parse($validated['runde' . $runde . 'bis'])->startOfDay();
                $tageIds = $this->tageIds($start, $end);

                foreach ($bereiche as $bereich) {
                    $gruppe = Gruppe::updateOrCreate(
                        [
                            'projekt_id' => $projekt->id,
                            'bereich_id' => $bereich->id,
                            'anfangsdatum' => $start->toDateString(),
                            'enddatum' => $end->toDateString(),
                            'bemerkung' => $this->gruppenBemerkung((int) $validated['partner_id'], (string) $validated['schuljahr'], (string) $validated['teil'], $runde),
                        ],
                        [
                            'personen_id' => $betreuerId,
                            'raum_id' => $raumId,
                            'startzeit' => $validated['startzeit'],
                            'endzeit' => $validated['endzeit'],
                        ]
                    );
                    $gruppenAnzahl++;

                    $einteilungen = EinteilungBereiche::where('teilnehmende_type', PersonenIstSchueler::class)
                        ->whereIn('teilnehmende_id', $schuelerIds)
                        ->where('bereich_id', $bereich->id)
                        ->where('runde', $runde)
                        ->get();

                    foreach ($einteilungen as $einteilung) {
                        $schueler = $schuelerNachId->get($einteilung->teilnehmende_id);
                        if (!$schueler?->person_id) {
                            continue;
                        }

                        foreach ($tageIds as $tagId) {
                            $pivot = GruppeHasPersonen::firstOrCreate(
                                [
                                    'personen_id' => $schueler->person_id,
                                    'gruppe_id' => $gruppe->id,
                                    'tage_id' => $tagId,
                                ],
                                [
                                    'user_id' => $betreuerId,
                                    'zeitgeplant_id' => $zeitGeplant->id,
                                    'zeittatsaechlich_id' => $zeitTatsaechlich->id,
                                    'anwesenheitsstatuten_id' => $status->id,
                                    'bemerkung' => null,
                                ]
                            );

                            if ($pivot->wasRecentlyCreated) {
                                $pivotAnzahl++;
                            }
                        }
                    }
                }
            }
        });

        return $this->payloadResponse(
            "Gruppen wurden generiert ({$gruppenAnzahl} Gruppen, {$pivotAnzahl} neue Anwesenheitseintraege).",
            $partnerId,
            $schuljahr,
            $teil
        );
    }

    public function exportExcel(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'schuljahr' => ['required', 'string'],
            'teil' => ['required', 'string'],
            'eintritt' => ['required', 'date'],
            'austritt' => ['required', 'date', 'after_or_equal:eintritt'],
        ]);

        $partnerId = (int) $validated['partner_id'];
        $schuljahr = (string) $validated['schuljahr'];
        $teil = (string) $validated['teil'];
        $projekt = $this->currentProjekt();
        $setting = $this->einteilungSettingFor($projekt->id, $partnerId, $schuljahr, $teil, $projekt);
        $runden = $this->rundenArray($setting->runden_anzahl);
        $partner = Partner::findOrFail($partnerId);
        $bereiche = $this->projektBereiche($projekt);
        $schueler = $this->schuelerQuery($partnerId, $schuljahr, $teil)
            ->with(['person', 'einteilungen.bereich'])
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Einteilung');
        $sheet->setCellValue('A1', 'Einteilung');
        $sheet->setCellValue('A2', 'Schule');
        $sheet->setCellValue('B2', $partner->name);
        $sheet->setCellValue('A3', 'Schuljahr');
        $sheet->setCellValue('B3', $schuljahr);
        $sheet->setCellValue('A4', 'Teil');
        $sheet->setCellValue('B4', $teil);
        $sheet->setCellValue('A5', 'Zeitraum');
        $sheet->setCellValue('B5', Carbon::parse($validated['eintritt'])->format('d.m.Y') . ' - ' . Carbon::parse($validated['austritt'])->format('d.m.Y'));

        $headers = ['Runde', 'Bereich', 'Nachname', 'Vorname', 'Klasse', 'Geschlecht'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 7], $header);
        }

        $row = 8;
        foreach ($runden as $runde) {
            foreach ($bereiche as $bereich) {
                $items = $schueler
                    ->filter(fn ($item) => (int) ($item->einteilungen->firstWhere('runde', $runde)?->bereich_id) === (int) $bereich->id)
                    ->sortBy(fn ($item) => sprintf('%s|%s|%s', $item->klasse ?? '', $item->person?->nachname ?? '', $item->person?->vorname ?? ''))
                    ->values();

                foreach ($items as $item) {
                    $sheet->setCellValue([1, $row], $runde);
                    $sheet->setCellValue([2, $row], $bereich->name);
                    $sheet->setCellValue([3, $row], $item->person?->nachname);
                    $sheet->setCellValue([4, $row], $item->person?->vorname);
                    $sheet->setCellValue([5, $row], $item->klasse);
                    $sheet->setCellValue([6, $row], $item->person?->geschlecht);
                    $row++;
                }
            }
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $lastRow = max(8, $row - 1);
        $sheet->getStyle('A7:' . $lastColumn . '7')->getFont()->setBold(true);
        $sheet->getStyle('A7:' . $lastColumn . '7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF3F7');
        $sheet->getStyle('A7:' . $lastColumn . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9DEE5');
        $sheet->getStyle('A7:' . $lastColumn . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet(
            $spreadsheet,
            'Einteilung_' . $this->safeName($partner->name) . '_' . $this->safeName($schuljahr) . '_Teil_' . $this->safeName($teil) . '.xlsx'
        );
    }

    private function pagePayload(int $partnerId, string $schuljahr, string $teil): array
    {
        $projekt = $this->currentProjekt();
        $partner = Partner::findOrFail($partnerId);
        $alleBereiche = $this->projektBereiche($projekt);
        $setting = $this->einteilungSettingFor($projekt->id, $partnerId, $schuljahr, $teil, $projekt);
        $runden = $this->rundenArray($setting->runden_anzahl);

        $schueler = $this->schuelerQuery($partnerId, $schuljahr, $teil)
            ->with(['person', 'bereichsauswahl', 'einteilungen.bereich'])
            ->get()
            ->sortBy(fn ($item) => sprintf(
                '%s|%s|%s',
                $item->klasse ?? '',
                $item->person?->nachname ?? '',
                $item->person?->vorname ?? ''
            ))
            ->values();

        $results = [];
        foreach ($alleBereiche as $bereich) {
            $results[$bereich->name] = array_fill_keys($runden, []);
        }

        foreach ($schueler as $item) {
            $formatted = $this->formatSchueler($item, $runden);
            foreach ($item->einteilungen as $einteilung) {
                $bereich = $einteilung->bereich;
                $runde = (int) $einteilung->runde;
                if (!$bereich || !isset($results[$bereich->name]) || !in_array($runde, $runden, true)) {
                    continue;
                }

                $results[$bereich->name][$runde][] = $formatted;
            }
        }

        $updatedAt = $schueler
            ->flatMap(fn ($item) => $item->einteilungen)
            ->max('updated_at');

        $projektPartnerIds = $projekt->partners->pluck('id')->filter()->values();
        $betreuer = Auth::user()->can('projekt.mitarbeiter.view.all')
            ? $projekt->mitarbeiter
            : collect([Auth::user()->person])->filter();

        return [
            'results' => $results,
            'alle_bereiche' => $alleBereiche->values(),
            'updated_at' => $updatedAt?->toIso8601String(),
            'partner' => [
                'id' => $partner->id,
                'name' => $partner->name,
            ],
            'schuljahr' => $schuljahr,
            'teil' => $teil,
            'klassen' => $schueler->pluck('klasse')->filter()->unique()->sort()->values(),
            'anzahlBereiche' => $alleBereiche->count(),
            'runden' => $runden,
            'parameter' => $this->parameterPayload($setting, $projekt, $partnerId, $schuljahr, $teil),
            'teilnehmerOptions' => $schueler->map(fn ($item) => [
                'id' => $item->id,
                'name' => trim(($item->person?->nachname ?? '') . ', ' . ($item->person?->vorname ?? ''), ' ,'),
                'klasse' => $item->klasse,
                'eingeteilt' => $item->einteilungen->isNotEmpty(),
            ])->values(),
            'raeume' => $projekt->raeume->map(fn ($raum) => [
                'id' => $raum->id,
                'name' => $raum->name,
            ])->values(),
            'betreuer' => $betreuer->map(fn ($person) => [
                'id' => $person->id,
                'name' => trim(($person->nachname ?? '') . ', ' . ($person->vorname ?? ''), ' ,'),
            ])->values(),
            'stats' => [
                'schulen' => $projektPartnerIds->count() ?: 1,
                'gruppen' => Gruppe::where('projekt_id', $projekt->id)->count(),
                'teilnehmer' => $projektPartnerIds->isNotEmpty()
                    ? PersonenIstSchueler::whereIn('schule_id', $projektPartnerIds)->count()
                    : $schueler->count(),
                'bereiche' => $alleBereiche->count(),
            ],
        ];
    }

    private function parameterPayload(EinteilungSetting $setting, Projekt $projekt, int $partnerId, string $schuljahr, string $teil): array
    {
        $bereiche = $this->projektBereiche($projekt);
        $wahlSetting = $this->bereichsauswahlSettingFor($projekt->id, $partnerId, $schuljahr, $teil, $projekt);

        return [
            'id' => $setting->id,
            'runden_anzahl' => (int) $setting->runden_anzahl,
            'auswahl_anzahl' => (int) $wahlSetting->auswahl_anzahl,
            'standard_kapazitaet' => (int) $setting->standard_kapazitaet,
            'kapazitaeten' => $this->kapazitaetenFor($setting, $bereiche),
        ];
    }

    private function formatSchueler(PersonenIstSchueler $schueler, array $runden): array
    {
        $alleRunden = $schueler->einteilungen->pluck('bereich_id', 'runde');
        $einteilungIds = [];
        foreach ($runden as $runde) {
            $einteilungIds[$runde] = $alleRunden[$runde] ?? null;
        }

        return [
            'id' => $schueler->id,
            'person_id' => $schueler->person_id,
            'vorname' => $schueler->person?->vorname ?? '',
            'nachname' => $schueler->person?->nachname ?? '',
            'geschlecht' => $schueler->person?->geschlecht ?? '',
            'klasse' => $schueler->klasse,
            'einteilung_ids' => $einteilungIds,
        ];
    }

    private function currentProjekt(): Projekt
    {
        $projektId = Auth::user()?->current_team_id;
        if (!$projektId) {
            throw ValidationException::withMessages([
                'projekt' => 'Bitte waehlen Sie ein Projekt aus.',
            ]);
        }

        return Projekt::with(['bereiche', 'raeume', 'mitarbeiter', 'partners'])->findOrFail($projektId);
    }

    private function projektBereiche(?Projekt $projekt = null): Collection
    {
        $projekt = $projekt ?: $this->currentProjekt();

        return $projekt->bereiche
            ->filter(fn ($bereich) => $bereich->name !== 'Potenzialanalyse')
            ->sortBy('name')
            ->values();
    }

    private function schuelerQuery(int $partnerId, string $schuljahr, string $teil)
    {
        return PersonenIstSchueler::query()
            ->where('schule_id', $partnerId)
            ->where('schuljahr', $schuljahr)
            ->where('teil', $teil);
    }

    private function schuelerInContext(int $schuelerId, int $partnerId, string $schuljahr, string $teil): PersonenIstSchueler
    {
        return $this->schuelerQuery($partnerId, $schuljahr, $teil)
            ->where('id', $schuelerId)
            ->firstOrFail();
    }

    private function einteilungSettingFor(int $projektId, int $partnerId, string $schuljahr, string $teil, ?Projekt $projekt = null): EinteilungSetting
    {
        $setting = EinteilungSetting::firstOrCreate(
            [
                'projekt_id' => $projektId,
                'partner_id' => $partnerId,
                'schuljahr' => $schuljahr,
                'teil' => $teil,
            ],
            [
                'runden_anzahl' => self::DEFAULT_RUNDEN_ANZAHL,
                'standard_kapazitaet' => self::DEFAULT_KAPAZITAET,
                'user_create' => Auth::id(),
            ]
        );

        $this->ensureKapazitaeten($setting, $this->projektBereiche($projekt));

        return $setting->fresh('kapazitaeten');
    }

    private function bereichsauswahlSettingFor(int $projektId, int $partnerId, string $schuljahr, string $teil, ?Projekt $projekt = null): BereichsauswahlSetting
    {
        $setting = BereichsauswahlSetting::firstOrCreate(
            [
                'projekt_id' => $projektId,
                'partner_id' => $partnerId,
                'schuljahr' => $schuljahr,
                'teil' => $teil,
            ],
            [
                'auswahl_anzahl' => $this->defaultAuswahlAnzahl($projekt),
                'public_token' => $this->publicToken(),
                'zugang_aktiv' => true,
                'user_create' => Auth::id(),
            ]
        );

        if (!$setting->public_token) {
            $setting->update(['public_token' => $this->publicToken()]);
        }

        return $setting;
    }

    private function ensureKapazitaeten(EinteilungSetting $setting, Collection $bereiche): void
    {
        $existing = $setting->kapazitaeten()->pluck('bereich_id')->map(fn ($id) => (int) $id);

        foreach ($bereiche as $bereich) {
            if ($existing->contains((int) $bereich->id)) {
                continue;
            }

            $setting->kapazitaeten()->create([
                'bereich_id' => $bereich->id,
                'plaetze' => $setting->standard_kapazitaet ?: self::DEFAULT_KAPAZITAET,
            ]);
        }
    }

    private function kapazitaetenFor(EinteilungSetting $setting, Collection $bereiche): array
    {
        $this->ensureKapazitaeten($setting, $bereiche);
        $setting->load('kapazitaeten');

        return $bereiche
            ->mapWithKeys(function ($bereich) use ($setting) {
                $kapazitaet = $setting->kapazitaeten->firstWhere('bereich_id', $bereich->id);

                return [(int) $bereich->id => (int) ($kapazitaet?->plaetze ?? $setting->standard_kapazitaet ?? self::DEFAULT_KAPAZITAET)];
            })
            ->all();
    }

    private function normalizeRundenAnzahl(int $value): int
    {
        return min(self::MAX_RUNDEN, max(self::MIN_RUNDEN, $value));
    }

    private function normalizeAuswahlAnzahl(int $value): int
    {
        return min(self::MAX_AUSWAHL, max(self::MIN_AUSWAHL, $value));
    }

    private function rundenArray(int $rundenAnzahl): array
    {
        return range(1, $this->normalizeRundenAnzahl($rundenAnzahl));
    }

    private function defaultAuswahlAnzahl(?Projekt $projekt): int
    {
        $count = $projekt ? $this->projektBereiche($projekt)->count() : self::MAX_AUSWAHL;

        return $this->normalizeAuswahlAnzahl($count > 0 ? $count : self::MAX_AUSWAHL);
    }

    private function publicToken(): string
    {
        do {
            $token = Str::random(40);
        } while (BereichsauswahlSetting::where('public_token', $token)->exists());

        return $token;
    }

    private function validateRoundFields(Request $request, array $runden, bool $required): array
    {
        $rules = [];
        foreach ($runden as $runde) {
            $rules['runde_' . $runde] = [$required ? 'required' : 'nullable', 'integer', 'exists:bereiches,id'];
        }

        return $request->validate($rules);
    }

    private function rundeValues(array $data, array $runden): array
    {
        $values = [];
        foreach ($runden as $runde) {
            $value = $data['runde_' . $runde] ?? null;
            if ($value !== null && $value !== '') {
                $values[$runde] = (int) $value;
            }
        }

        return $values;
    }

    private function validateDistinctBereiche(array $data, bool $required, array $runden): void
    {
        $werte = $this->rundeValues($data, $runden);

        if ($required && count($werte) !== count($runden)) {
            throw ValidationException::withMessages([
                'runde_1' => 'Bitte alle ' . count($runden) . ' Runden auswaehlen.',
            ]);
        }

        if (count(array_unique(array_values($werte))) !== count($werte)) {
            throw ValidationException::withMessages([
                'runde_1' => 'Ein Teilnehmer darf einen Bereich nicht mehrfach besuchen.',
            ]);
        }
    }

    private function assertBereicheInProjekt(array $bereichIds): void
    {
        $allowed = $this->projektBereiche()->pluck('id')->map(fn ($id) => (int) $id);
        $invalid = collect($bereichIds)->map(fn ($id) => (int) $id)->diff($allowed);

        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages([
                'runde_1' => 'Der Bereich gehoert nicht zum aktuellen Projekt.',
            ]);
        }
    }

    private function assertKapazitaetenFuerEingabe(
        int $partnerId,
        string $schuljahr,
        string $teil,
        array $rundeValues,
        ?int $ignoreSchuelerId = null
    ): void {
        $projekt = $this->currentProjekt();
        $setting = $this->einteilungSettingFor($projekt->id, $partnerId, $schuljahr, $teil, $projekt);
        $kapazitaeten = $this->kapazitaetenFor($setting, $this->projektBereiche($projekt));
        $schuelerIds = $this->schuelerQuery($partnerId, $schuljahr, $teil)->pluck('id');

        foreach ($rundeValues as $runde => $bereichId) {
            $kapazitaet = (int) ($kapazitaeten[(int) $bereichId] ?? 0);
            if ($kapazitaet <= 0) {
                throw ValidationException::withMessages([
                    'runde_' . $runde => 'Dieser Bereich hat in den Parametern keine freien Plaetze.',
                ]);
            }

            $query = EinteilungBereiche::where('teilnehmende_type', PersonenIstSchueler::class)
                ->whereIn('teilnehmende_id', $schuelerIds)
                ->where('runde', $runde)
                ->where('bereich_id', $bereichId);

            if ($ignoreSchuelerId) {
                $query->where('teilnehmende_id', '!=', $ignoreSchuelerId);
            }

            if ($query->count() >= $kapazitaet) {
                throw ValidationException::withMessages([
                    'runde_' . $runde => 'Die Kapazitaet fuer diesen Bereich ist in Runde ' . $runde . ' bereits erreicht.',
                ]);
            }
        }
    }

    private function prioritaetenFuerSchueler(PersonenIstSchueler $schueler, Collection $bereichIds, int $auswahlAnzahl): array
    {
        $auswahl = $schueler->bereichsauswahl;
        $gewaehlteBereiche = collect([
            $auswahl?->bereich_id1,
            $auswahl?->bereich_id2,
            $auswahl?->bereich_id3,
            $auswahl?->bereich_id4,
        ])
            ->take($this->normalizeAuswahlAnzahl($auswahlAnzahl))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $bereichIds->contains($id))
            ->unique()
            ->values();

        $fallback = $bereichIds->diff($gewaehlteBereiche)->shuffle()->values();

        return [
            $gewaehlteBereiche,
            $fallback,
            $gewaehlteBereiche->isNotEmpty(),
        ];
    }

    private function waehleBereich(Collection $prioritaeten, array $belegung, array $kapazitaeten, array $verwendet): ?int
    {
        $candidates = $prioritaeten
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(function ($bereichId) use ($belegung, $kapazitaeten, $verwendet) {
                if (in_array($bereichId, $verwendet, true)) {
                    return false;
                }

                return (int) ($belegung[$bereichId] ?? 0) < (int) ($kapazitaeten[$bereichId] ?? 0);
            })
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->map(fn ($bereichId) => [
                'id' => $bereichId,
                'ratio' => ($belegung[$bereichId] ?? 0) / max(1, (int) ($kapazitaeten[$bereichId] ?? 1)),
                'load' => $belegung[$bereichId] ?? 0,
                'random' => random_int(0, PHP_INT_MAX),
            ])
            ->sort(function ($a, $b) {
                if ($a['ratio'] !== $b['ratio']) {
                    return $a['ratio'] <=> $b['ratio'];
                }

                if ($a['load'] !== $b['load']) {
                    return $a['load'] <=> $b['load'];
                }

                return $a['random'] <=> $b['random'];
            })
            ->first()['id'];
    }

    private function clearBereichsauswahlFields(int $partnerId, string $schuljahr, string $teil, int $auswahlAnzahl): void
    {
        $teilnehmerIds = $this->schuelerQuery($partnerId, $schuljahr, $teil)->pluck('id');
        $clearFields = collect([3, 4])
            ->filter(fn ($field) => $field > $auswahlAnzahl)
            ->mapWithKeys(fn ($field) => ['bereich_id' . $field => null])
            ->all();

        if ($clearFields) {
            Bereichsauswahl::whereIn('teilnehmer_id', $teilnehmerIds)->update($clearFields);
        }
    }

    private function removeEinteilungenAboveRound(int $partnerId, string $schuljahr, string $teil, int $rundenAnzahl): void
    {
        $schueler = $this->schuelerQuery($partnerId, $schuljahr, $teil)
            ->with(['person', 'einteilungen' => fn ($query) => $query->where('runde', '>', $rundenAnzahl)])
            ->get();

        foreach ($schueler as $item) {
            foreach ($item->einteilungen as $einteilung) {
                $this->syncGeneratedGroupMembership(
                    $item,
                    $partnerId,
                    $schuljahr,
                    $teil,
                    (int) $einteilung->runde,
                    (int) $einteilung->bereich_id,
                    null
                );
                $einteilung->delete();
            }
        }
    }

    private function syncGeneratedGroupMembership(
        PersonenIstSchueler $schueler,
        int $partnerId,
        string $schuljahr,
        string $teil,
        int $runde,
        ?int $alterBereichId,
        ?int $neuerBereichId
    ): void {
        if (!$schueler->person_id || (int) $alterBereichId === (int) $neuerBereichId) {
            return;
        }

        $defaultStatus = $this->defaultAnwesenheitsstatus();

        if ($alterBereichId) {
            $alteGruppe = $this->generatedGroup($partnerId, $schuljahr, $teil, $runde, (int) $alterBereichId);
            if ($alteGruppe) {
                $entries = GruppeHasPersonen::where('gruppe_id', $alteGruppe->id)
                    ->where('personen_id', $schueler->person_id)
                    ->get();

                if ($this->hasEvaluatedEntries($entries, (int) $defaultStatus->id)) {
                    throw ValidationException::withMessages([
                        'gruppe' => 'Der Teilnehmer kann in Runde ' . $runde . ' nicht verschoben werden, weil die alte Gruppe bereits ausgewertet oder bearbeitet wurde.',
                    ]);
                }

                if ($entries->isNotEmpty()) {
                    GruppeHasPersonen::whereIn('id', $entries->pluck('id'))->delete();
                }
            }
        }

        if ($neuerBereichId) {
            $neueGruppe = $this->generatedGroup($partnerId, $schuljahr, $teil, $runde, (int) $neuerBereichId);
            if ($neueGruppe) {
                $this->addSchuelerToGeneratedGroup($schueler, $neueGruppe, $defaultStatus);
            }
        }
    }

    private function generatedGroup(int $partnerId, string $schuljahr, string $teil, int $runde, int $bereichId): ?Gruppe
    {
        $projekt = $this->currentProjekt();

        return Gruppe::where('projekt_id', $projekt->id)
            ->where('bereich_id', $bereichId)
            ->where('bemerkung', $this->gruppenBemerkung($partnerId, $schuljahr, $teil, $runde))
            ->first();
    }

    private function addSchuelerToGeneratedGroup(PersonenIstSchueler $schueler, Gruppe $gruppe, Anwesenheitsstatuten $status): void
    {
        if (!$gruppe->anfangsdatum || !$gruppe->enddatum || !$gruppe->startzeit || !$gruppe->endzeit) {
            return;
        }

        $zeitGeplant = Zeiten::firstOrCreate([
            'startzeit' => $gruppe->startzeit,
            'endzeit' => $gruppe->endzeit,
        ]);
        $zeitTatsaechlich = Zeiten::firstOrCreate([
            'startzeit' => $gruppe->startzeit,
            'endzeit' => $gruppe->endzeit,
        ]);
        $tageIds = $this->tageIds(Carbon::parse($gruppe->anfangsdatum), Carbon::parse($gruppe->enddatum));

        foreach ($tageIds as $tagId) {
            GruppeHasPersonen::firstOrCreate(
                [
                    'personen_id' => $schueler->person_id,
                    'gruppe_id' => $gruppe->id,
                    'tage_id' => $tagId,
                ],
                [
                    'user_id' => $gruppe->personen_id,
                    'zeitgeplant_id' => $zeitGeplant->id,
                    'zeittatsaechlich_id' => $zeitTatsaechlich->id,
                    'anwesenheitsstatuten_id' => $status->id,
                    'bemerkung' => null,
                ]
            );
        }
    }

    private function hasEvaluatedEntries(Collection $entries, int $defaultStatusId): bool
    {
        return $entries->contains(function ($entry) use ($defaultStatusId) {
            if (filled($entry->bemerkung)) {
                return true;
            }

            if ((int) $entry->anwesenheitsstatuten_id !== $defaultStatusId) {
                return true;
            }

            return $entry->zeittatsaechlich_id
                && $entry->zeitgeplant_id
                && (int) $entry->zeittatsaechlich_id !== (int) $entry->zeitgeplant_id;
        });
    }

    private function defaultAnwesenheitsstatus(): Anwesenheitsstatuten
    {
        $status = Anwesenheitsstatuten::where('status', 'unentschuldigt')->first()
            ?? Anwesenheitsstatuten::first();

        if (!$status) {
            throw ValidationException::withMessages([
                'anwesenheit' => 'Es wurde kein Anwesenheitsstatus gefunden.',
            ]);
        }

        return $status;
    }

    private function teilnehmerName(PersonenIstSchueler $item): string
    {
        return trim(($item->person?->vorname ?? '') . ' ' . ($item->person?->nachname ?? '')) ?: 'Teilnehmer #' . $item->id;
    }

    private function tageIds(Carbon $start, Carbon $end): array
    {
        $ids = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $tag = Tage::firstOrCreate([
                'datum' => $date->toDateString(),
            ], [
                'wochentag' => $date->locale('de')->dayName,
                'feiertag_typ' => 'kein_feiertag',
            ]);
            $ids[] = $tag->id;
        }

        return $ids;
    }

    private function gruppenBemerkung(int $partnerId, string $schuljahr, string $teil, int $runde): string
    {
        return "BOP Einteilung Schule {$partnerId} Schuljahr {$schuljahr} Teil {$teil} Runde {$runde}";
    }

    private function payloadResponse(string $message, int $partnerId, string $schuljahr, string $teil)
    {
        return response()->json([
            'message' => $message,
            'payload' => $this->pagePayload($partnerId, $schuljahr, $teil),
        ]);
    }

    private function safeName(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', trim($value));
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename)
    {
        $path = storage_path('app/tmp/' . Str::uuid() . '_' . $filename);
        File::ensureDirectoryExists(dirname($path));
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }
}
