<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Tage;
use Inertia\Inertia;
use App\Models\Gruppe;
use App\Models\Zeiten;
use App\Models\Personen;
use App\Models\Standort;
use Illuminate\Http\Request;
use App\Models\GruppeHasPersonen;
use App\Http\Controllers\Controller;
use App\Models\Anwesenheitsstatuten;
use App\Models\PotenzialanalyseBericht;
use App\Models\PotenzialanalyseBeurteilung;
use App\Models\PotenzialanalyseKompetenzbewertung;
use App\Models\PotenzialanalyseSelbsteinschaetzung;
use App\Models\PotenzialanalyseUebungErgebnis;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GruppeHasTeilnehmerController extends Controller
{
    public function __construct(private readonly ActiveProjectContext $activeProjectContext)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }





    public function store(Request $request)
    {
        $request->merge([
            'startzeit' => $this->normalizeTime($request->input('startzeit')),
            'endzeit' => $this->normalizeTime($request->input('endzeit')),
        ]);

        $validated = $request->validate([
            'gruppe_id'    => 'required|exists:gruppes,id',
            'teilnehmer'   => 'required|array|min:1',
            'teilnehmer.*' => 'integer|exists:personens,id',
            'startzeit'    => 'required|date_format:H:i',
            'endzeit'      => 'required|date_format:H:i',
            'startdatum'   => 'required|date',
            'enddatum'     => 'required|date',
        ]);

        $ids = array_map('intval', $validated['teilnehmer']);
        $gruppe = Gruppe::findOrFail($validated['gruppe_id']);
        abort_unless($this->canUseGroup(auth()->user(), $gruppe), 403);

        $projekt = $gruppe->projekt()->firstOrFail();
        $validParticipantIds = DB::table('projekt_has_personens')
            ->join('personens', 'personens.id', '=', 'projekt_has_personens.personen_id')
            ->where('projekt_has_personens.projekt_id', $projekt->id)
            ->where('personens.typ', 'teilnehmer')
            ->whereIn('personens.id', $ids)
            ->pluck('personens.id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($validParticipantIds->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'teilnehmer' => 'Alle Teilnehmer müssen dem aktiven Projekt zugewiesen sein.',
            ]);
        }

        $maxParticipants = $projekt->rule('max_group_participants');
        if ($maxParticipants !== null) {
            $existingIds = GruppeHasPersonen::query()
                ->where('gruppe_id', $gruppe->id)
                ->pluck('personen_id')
                ->map(fn ($id) => (int) $id)
                ->unique();

            if ($existingIds->merge($ids)->unique()->count() > (int) $maxParticipants) {
                throw ValidationException::withMessages([
                    'teilnehmer' => "Die maximale Gruppengröße von {$maxParticipants} Teilnehmern wird überschritten.",
                ]);
            }
        }

        $anwesenheitsstatuten = Anwesenheitsstatuten::where(
            'status',
            $projekt->rule('attendance_default_status', 'unentschuldigt')
        )->firstOrFail();


        // IDs, die bereits existieren
        // ⏰ geplante & tatsächliche Zeiten anlegen
        $zeitGeplant = Zeiten::firstOrCreate([
            'startzeit' => $validated['startzeit'],
            'endzeit'   => $validated['endzeit'],
        ]);

        $zeitTatsaechlich = Zeiten::firstOrCreate([
            'startzeit' => $validated['startzeit'],
            'endzeit'   => $validated['endzeit'],
        ]);

        // 📅 Alle Tage zwischen Start- und Enddatum ermitteln
        $start = Carbon::parse($validated['startdatum']);
        $end = Carbon::parse($validated['enddatum']);

        $tageIDs = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($projekt->rule('attendance_skip_weekends', false) && $date->isWeekend()) {
                continue;
            }
            // Feiertage / Wochenenden kannst du hier optional überspringen
            $tag = Tage::firstOrCreate([
                'datum' => $date->format('Y-m-d'),
            ], [
                'wochentag' => $date->locale('de')->dayName,
                'feiertag_typ' => 'kein_feiertag',
            ]);

            $tageIDs[] = $tag->id;
        }

        // 🔥 Für jeden Teilnehmer & Tag Eintrag erstellen
        $createdTeilnehmerIds = [];

        foreach ($ids as $teilnehmerId) {
            foreach ($tageIDs as $tagId) {
                $pivot = GruppeHasPersonen::firstOrCreate(
                    [
                        'gruppe_id' => $gruppe->id,
                        'personen_id' => $teilnehmerId,
                        'tage_id' => $tagId,
                    ],
                    [
                        'user_id' => $gruppe->personen_id,
                        'zeitgeplant_id' => $zeitGeplant->id,
                        'zeittatsaechlich_id' => $zeitTatsaechlich->id,
                        'anwesenheitsstatuten_id' => $anwesenheitsstatuten->id,
                        'bemerkung' => null,
                    ]
                );

                if ($pivot->wasRecentlyCreated) {
                    $createdTeilnehmerIds[$teilnehmerId] = true;
                }
            }
        }

        $new = array_keys($createdTeilnehmerIds);
        $already = array_values(array_diff($ids, $new));

        // ✅ Rückgabe
        $addedTeilnehmer = Personen::whereIn('id', $new)->get(['id', 'vorname', 'nachname', 'geschlecht']);
        $alreadyTeilnehmer = Personen::whereIn('id', $already)->get(['id', 'vorname', 'nachname', 'geschlecht']);

        return response()->json([
            'success' => true,
            'message' => count($new) > 0
                ? 'Teilnehmer mit Zeiten und Tagen erfolgreich hinzugefügt.'
                : 'Keine neuen Teilnehmer hinzugefügt.',
            'added'   => $addedTeilnehmer,
            'already' => $alreadyTeilnehmer,
        ], 200);

    }






    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {

        $gruppe = Gruppe::with([
            'bereich',
            'raum',
            'klassenbuecher.typ',
            'projekt.dokumente.bereiche',
            'projekt.dokumentKategorien.dokumente.bereiche',
            'projekt.potenzialanalyseUebungen.kriterien',

        ])->findOrFail($id);

        $user = auth()->user();
        abort_unless($this->canUseGroup($user, $gruppe), 403);
        $canReadAttendance = collect([
            'anwesenheit.index',
            'anwesenheit.manage',
            'anwesenheit.destroy',
        ])->contains(fn (string $permission) => $user->can($permission));

        $gruppenTeilnehmer = GruppeHasPersonen::query()
            ->where('gruppe_id', $gruppe->id)
            ->with([
                'teilnehmer.schueler',
                'zeitgeplant',
                'zeittatsaechlich',
                'status',
                'tag',
                'user',
            ])
            ->get()
            ->map(function (GruppeHasPersonen $eintrag) use ($canReadAttendance) {
                if (! $eintrag->teilnehmer) {
                    return null;
                }

                $teilnehmer = clone $eintrag->teilnehmer;
                $pivot = clone $eintrag;
                $pivot->unsetRelation('teilnehmer');
                if (! $canReadAttendance) {
                    $pivot->makeHidden([
                        'anwesenheitsstatuten_id',
                        'bemerkung',
                        'tage_id',
                        'zeitgeplant_id',
                        'zeittatsaechlich_id',
                        'status',
                        'tag',
                        'zeitgeplant',
                        'zeittatsaechlich',
                    ]);
                    $pivot->unsetRelations();
                }
                $teilnehmer->setRelation('pivot', $pivot);

                return $teilnehmer;
            })
            ->filter()
            ->values();

        $gruppe->setRelation('teilnehmer', $gruppenTeilnehmer);

        if ($gruppe->projekt) {
            $direkteDokumente = $gruppe->projekt->dokumente;
            $kategorieDokumente = $gruppe->projekt->dokumentKategorien
                ->flatMap(fn ($kategorie) => $kategorie->dokumente);

            $gruppe->projekt->setRelation(
                'dokumente',
                $direkteDokumente
                    ->concat($kategorieDokumente)
                    ->filter(fn ($dokument) => $this->dokumentSichtbarFuerGruppe($dokument, $gruppe))
                    ->unique('id')
                    ->values()
            );
        }

        $anwesenheitsstatuten = $canReadAttendance ? Anwesenheitsstatuten::all() : collect();

        $projektId = $gruppe->projekt_id ?? $user->current_team_id;
        $standortId = $gruppe->standort_id;

        $teilnehmer = Personen::Teilnehmer()
            ->whereHas('projekte', function ($query) use ($projektId, $standortId) {
                $query->where('projekts.id', $projektId);
                if ($standortId) {
                    $query->where('projekt_has_personens.standort_id', $standortId);
                }
            })
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get(['id', 'vorname', 'nachname', 'geschlecht']);
        return Inertia::render('Gruppe/GruppeHasTeilnehmer/Index', [
            'gruppe' => $gruppe,
            'teilnehmer' => $teilnehmer,
            'anwesenheitsstatuten' => $anwesenheitsstatuten,
            'bopLegacyExporte' => $this->bopLegacyExporte($gruppe),
            'potenzialanalyse' => $this->potenzialanalysePayload($gruppe),
        ]);

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
         try {
            $gruppeHasPersonen = GruppeHasPersonen::findOrFail($id);
            abort_unless($this->canUseGroup(auth()->user(), $gruppeHasPersonen->gruppe), 403);

            $gruppeHasPersonen->delete();

            return response()->json(['success' => true, 'message' => 'Anwesenheit erfolgreich gelöscht!']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Anwesenheit nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }

    public function destroyTeilnehmer(Gruppe $gruppe, Personen $personen)
    {
        abort_unless($this->canUseGroup(auth()->user(), $gruppe), 403);

        $deleted = GruppeHasPersonen::where('gruppe_id', $gruppe->id)
            ->where('personen_id', $personen->id)
            ->delete();

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
            'message' => $deleted > 0
                ? 'Teilnehmer wurde aus der Gruppe entfernt.'
                : 'Teilnehmer war in dieser Gruppe nicht vorhanden.',
        ]);
    }

    private function canUseGroup($user, ?Gruppe $gruppe): bool
    {
        if (!$user || !$gruppe) {
            return false;
        }

        $activeProject = $this->activeProjectContext->currentAvailableFor($user);
        if (!$activeProject || (int) $gruppe->projekt_id !== (int) $activeProject->id) {
            return false;
        }

        if ($user->can('gruppe.view.all') || $user->can('projekt.mitarbeiter.view.all')) {
            return true;
        }

        return (int) $gruppe->personen_id === (int) $this->userPersonId($user);
    }

    private function normalizeTime($value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $value = trim((string) $value);

        if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches)) {
            return $value;
        }

        return str_pad($matches[1], 2, '0', STR_PAD_LEFT) . ':' . $matches[2];
    }

    private function userPersonId($user): ?int
    {
        return $user?->person_id ?? $user?->person?->id;
    }

    private function dokumentSichtbarFuerGruppe($dokument, Gruppe $gruppe): bool
    {
        if ($dokument->aktiv === false) {
            return false;
        }

        if (($dokument->einsatzbereich ?? 'gruppe') !== 'gruppe') {
            return false;
        }

        $bereichIds = $dokument->bereiche?->pluck('id') ?? collect();

        return $bereichIds->isEmpty() || $bereichIds->contains((int) $gruppe->bereich_id);
    }

    private function potenzialanalysePayload(Gruppe $gruppe): array
    {
        $projekt = $gruppe->projekt;

        if (! $projekt?->potenzialanalyse_aktiv) {
            return [
                'aktiv' => false,
                'tage' => null,
                'uebungen' => [],
                'teilnehmer' => [],
            ];
        }

        $uebungen = $projekt->potenzialanalyseUebungen
            ->filter(fn ($uebung) => $uebung->aktiv)
            ->map(function ($uebung) {
                return [
                    'id' => $uebung->id,
                    'name' => $uebung->name,
                    'tag' => $uebung->tag,
                    'beschreibung' => $uebung->beschreibung,
                    'hoechstwert' => $uebung->hoechstwert,
                    'auswertbar' => $uebung->auswertbar,
                    'sort_order' => $uebung->sort_order,
                    'kriterien' => $uebung->kriterien
                        ->filter(fn ($kriterium) => $kriterium->aktiv)
                        ->values()
                        ->map(fn ($kriterium) => [
                            'id' => $kriterium->id,
                            'name' => $kriterium->name,
                            'beschreibung' => $kriterium->beschreibung,
                            'skala_min' => $kriterium->skala_min,
                            'skala_max' => $kriterium->skala_max,
                            'sort_order' => $kriterium->sort_order,
                        ])
                        ->all(),
                ];
            })
            ->values();

        $personenIds = $gruppe->teilnehmer
            ->pluck('id')
            ->unique()
            ->values();

        if ($personenIds->isEmpty()) {
            return [
                'aktiv' => true,
                'tage' => $projekt->potenzialanalyse_tage,
                'uebungen' => $uebungen,
                'teilnehmer' => [],
            ];
        }

        $beurteilungen = PotenzialanalyseBeurteilung::query()
            ->where('gruppe_id', $gruppe->id)
            ->whereIn('personen_id', $personenIds)
            ->get()
            ->groupBy('personen_id');

        $selbsteinschaetzungen = PotenzialanalyseSelbsteinschaetzung::query()
            ->where('gruppe_id', $gruppe->id)
            ->whereIn('personen_id', $personenIds)
            ->get()
            ->groupBy('personen_id');

        $uebungErgebnisse = PotenzialanalyseUebungErgebnis::query()
            ->where('gruppe_id', $gruppe->id)
            ->whereIn('personen_id', $personenIds)
            ->get()
            ->groupBy('personen_id');

        $kompetenzbewertungen = PotenzialanalyseKompetenzbewertung::query()
            ->where('gruppe_id', $gruppe->id)
            ->whereIn('personen_id', $personenIds)
            ->get()
            ->groupBy('personen_id');

        $berichte = PotenzialanalyseBericht::query()
            ->where('gruppe_id', $gruppe->id)
            ->whereIn('personen_id', $personenIds)
            ->get()
            ->keyBy('personen_id');

        $teilnehmer = $personenIds
            ->mapWithKeys(fn ($personenId) => [(int) $personenId => [
                'uebungen' => ($uebungErgebnisse->get($personenId) ?? collect())
                    ->keyBy('uebung_id')
                    ->map(fn ($entry) => $this->formatPotenzialanalyseUebungErgebnis($entry))
                    ->all(),
                'selbsteinschaetzung' => ($kompetenzbewertungen->get($personenId) ?? collect())
                    ->where('typ', 'selbst')
                    ->keyBy('merkmal')
                    ->map(fn ($entry) => [
                        'bewertung' => $entry->bewertung,
                        'bemerkung' => $entry->bemerkung,
                    ])
                    ->all(),
                'kompetenzen' => ($kompetenzbewertungen->get($personenId) ?? collect())
                    ->where('typ', 'anleiter')
                    ->keyBy('merkmal')
                    ->map(fn ($entry) => [
                        'bewertung' => $entry->bewertung,
                        'bemerkung' => $entry->bemerkung,
                    ])
                    ->all(),
                'beurteilungen' => ($beurteilungen->get($personenId) ?? collect())
                    ->keyBy('kriterium_id')
                    ->map(fn ($entry) => [
                        'bewertung' => $entry->bewertung,
                        'bemerkung' => $entry->bemerkung,
                    ])
                    ->all(),
                'selbsteinschaetzungen' => ($selbsteinschaetzungen->get($personenId) ?? collect())
                    ->keyBy('kriterium_id')
                    ->map(fn ($entry) => [
                        'bewertung' => $entry->bewertung,
                        'bemerkung' => $entry->bemerkung,
                    ])
                    ->all(),
                'bericht' => $berichte->get($personenId)?->only([
                    'status',
                    'staerken',
                    'entwicklungsfelder',
                    'empfehlung',
                    'bericht_text',
                    'fertiggestellt_at',
                ]) ?? [
                    'status' => 'entwurf',
                    'staerken' => null,
                    'entwicklungsfelder' => null,
                    'empfehlung' => null,
                    'bericht_text' => null,
                    'fertiggestellt_at' => null,
                ],
            ]])
            ->all();

        return [
            'aktiv' => true,
            'tage' => $projekt->potenzialanalyse_tage,
            'uebungen' => $uebungen,
            'teilnehmer' => $teilnehmer,
        ];
    }

    private function formatPotenzialanalyseUebungErgebnis(PotenzialanalyseUebungErgebnis $entry): array
    {
        $zeit = (int) ($entry->zeit ?? 0);

        return [
            'punkte' => $entry->punkte,
            'zeit' => $zeit,
            'zeit_min' => intdiv($zeit, 60),
            'zeit_sec' => $zeit % 60,
        ];
    }

    private function bopLegacyExporte(Gruppe $gruppe): array
    {
        if (!$this->istBopProjekt($gruppe)) {
            return [];
        }

        $context = $this->bopGruppenContext($gruppe);
        if (!$context) {
            return [];
        }

        return $this->istPotenzialanalyseGruppe($gruppe)
            ? $this->potenzialanalyseExporte($gruppe, $context)
            : $this->poboExporte($gruppe, $context);
    }

    private function istBopProjekt(Gruppe $gruppe): bool
    {
        return str_contains(strtolower((string) $gruppe->projekt?->name), 'bop');
    }

    private function istPotenzialanalyseGruppe(Gruppe $gruppe): bool
    {
        return strtolower((string) $gruppe->bereich?->name) === 'potenzialanalyse';
    }

    private function bopGruppenContext(Gruppe $gruppe): ?array
    {
        $context = $this->contextAusGruppenBemerkung($gruppe);
        $schuelerContext = $this->contextAusGruppenTeilnehmern($gruppe, $context);

        $context = array_filter([
            'partner_id' => $context['partner_id'] ?? $schuelerContext['partner_id'] ?? null,
            'schuljahr' => $context['schuljahr'] ?? $schuelerContext['schuljahr'] ?? null,
            'teil' => $context['teil'] ?? $schuelerContext['teil'] ?? null,
            'runde' => $context['runde'] ?? null,
            'klasse' => $schuelerContext['klasse'] ?? null,
        ], fn ($value) => filled($value));

        if (empty($context['partner_id']) || empty($context['schuljahr']) || empty($context['teil'])) {
            return null;
        }

        return $context;
    }

    private function contextAusGruppenBemerkung(Gruppe $gruppe): array
    {
        $bemerkung = (string) $gruppe->bemerkung;

        if (!preg_match('/BOP Einteilung Schule\s+(\d+)\s+Schuljahr\s+(.+?)\s+Teil\s+(.+?)\s+Runde\s+(\d+)/u', $bemerkung, $matches)) {
            return [];
        }

        return [
            'partner_id' => (int) $matches[1],
            'schuljahr' => trim($matches[2]),
            'teil' => trim($matches[3]),
            'runde' => (int) $matches[4],
        ];
    }

    private function contextAusGruppenTeilnehmern(Gruppe $gruppe, array $knownContext = []): array
    {
        $schueler = $gruppe->teilnehmer
            ->flatMap(fn ($person) => $person->schueler ?? collect())
            ->filter(function ($item) use ($knownContext) {
                if (!$item->schule_id || !$item->schuljahr || !$item->teil) {
                    return false;
                }

                if (!empty($knownContext['partner_id']) && (int) $item->schule_id !== (int) $knownContext['partner_id']) {
                    return false;
                }

                if (!empty($knownContext['schuljahr']) && (string) $item->schuljahr !== (string) $knownContext['schuljahr']) {
                    return false;
                }

                if (!empty($knownContext['teil']) && (string) $item->teil !== (string) $knownContext['teil']) {
                    return false;
                }

                return true;
            });

        if ($schueler->isEmpty()) {
            return [];
        }

        $gruppe = $schueler
            ->groupBy(fn ($item) => $item->schule_id . '|' . $item->schuljahr . '|' . $item->teil)
            ->sortByDesc(fn ($items) => $items->count())
            ->first();

        $first = $gruppe?->first();
        if (!$first) {
            return [];
        }

        $klasse = $gruppe->pluck('klasse')->filter()->countBy()->sortDesc()->keys()->first();

        return [
            'partner_id' => (int) $first->schule_id,
            'schuljahr' => (string) $first->schuljahr,
            'teil' => (string) $first->teil,
            'klasse' => $klasse,
        ];
    }

    private function potenzialanalyseExporte(Gruppe $gruppe, array $context): array
    {
        $items = [
            [
                'id' => 'bop-pa-zertifikat-gruppe',
                'name' => 'Zertifikat PA Gruppe',
                'format' => 'DOCX',
                'typ' => 'Potenzialanalyse',
                'method' => 'get',
                'url' => route('gruppe.bop.export.zertifikat-pa', $gruppe->id),
            ],
            [
                'id' => 'bop-pa-teilnahmebescheinigung-gruppe',
                'name' => 'Teilnahmebescheinigung PA',
                'format' => 'DOCX',
                'typ' => 'Potenzialanalyse',
                'method' => 'get',
                'url' => route('gruppe.bop.export.teilnahme-pa', $gruppe->id),
            ],
            [
                'id' => 'bop-pa-auswertungsbogen-gruppe',
                'name' => 'Auswertungsbogen PA Gruppe',
                'format' => 'DOCX',
                'typ' => 'Potenzialanalyse',
                'method' => 'get',
                'url' => route('gruppe.bop.export.auswertungsbogen-pa', $gruppe->id),
            ],
            [
                'id' => 'bop-pa-auswertungsbogen',
                'name' => 'Auswertungsbogen PA Schule',
                'format' => 'PDF',
                'typ' => 'Potenzialanalyse',
                'method' => 'get',
                'url' => route('export.auswertungsbogenPA.schule.pdf', [
                    'partnerId' => $context['partner_id'],
                    'schuljahr' => $context['schuljahr'],
                    'teil' => $context['teil'],
                ]),
            ],
            [
                'id' => 'bop-pa-berichte-ordner',
                'name' => 'PA Berichte in Ordner generieren',
                'format' => 'ORDNER',
                'typ' => 'Potenzialanalyse',
                'method' => 'get',
                'url' => route('export.auswertungPA.schule.pdf.tofolder', [
                    'schulId' => $context['partner_id'],
                    'schuljahr' => $context['schuljahr'],
                    'teil' => $context['teil'],
                ]),
            ],
        ];

        if (!empty($context['klasse']) && auth()->user()?->can('anwesenheit.abrechnung')) {
            array_unshift($items, [
                'id' => 'bop-pa-anwesenheitsliste',
                'name' => 'Anwesenheitsliste PA',
                'format' => 'DOCX',
                'typ' => 'Potenzialanalyse',
                'method' => 'post',
                'url' => route('anwesenheitsliste.PA.export.word'),
                'fileName' => 'Anwesenheitsliste_PA_' . $this->safeExportName((string) $context['klasse']) . '.docx',
                'payload' => [
                    'startDate' => $gruppe->anfangsdatum,
                    'endDate' => $gruppe->enddatum,
                    'schuleId' => $context['partner_id'],
                    'schuljahr' => $context['schuljahr'],
                    'teil' => $context['teil'],
                    'klasse' => $context['klasse'],
                ],
            ]);
        }

        return array_values(array_filter($items, function (array $item): bool {
            if ($item['id'] === 'bop-pa-auswertungsbogen') {
                return auth()->user()?->can('dokumente.schule.export') ?? false;
            }

            if ($item['id'] === 'bop-pa-berichte-ordner') {
                return auth()->user()?->can('dokumente.ansprechpartner.manage') ?? false;
            }

            return true;
        }));
    }

    private function poboExporte(Gruppe $gruppe, array $context): array
    {
        $partnerId = $context['partner_id'];
        $schuljahr = $context['schuljahr'];
        $teil = $context['teil'];
        $termin = $gruppe->anfangsdatum ?: now()->toDateString();

        $items = [
            [
                'id' => 'bop-gruppe-namensschilder',
                'name' => 'Namensschilder',
                'format' => 'DOCX',
                'typ' => 'BOP Gruppe',
                'method' => 'get',
                'url' => route('gruppe.bop.export.namensschilder', $gruppe->id),
            ],
            [
                'id' => 'bop-gruppe-anwesenheitsliste',
                'name' => 'Anwesenheitsliste',
                'format' => 'XLSX',
                'typ' => 'BOP Gruppe',
                'method' => 'get',
                'url' => route('gruppe.bop.export.anwesenheitsliste', $gruppe->id),
            ],
            [
                'id' => 'bop-gruppe-hausordnung',
                'name' => 'Hausordnung Gruppe',
                'format' => 'DOCX',
                'typ' => 'BOP Gruppe',
                'method' => 'get',
                'url' => route('gruppe.bop.export.hausordnung', $gruppe->id),
            ],
            [
                'id' => 'bop-gruppe-berufsfelderprobung',
                'name' => 'Berufsfelderprobung Gruppe',
                'format' => 'DOCX',
                'typ' => 'BOP Gruppe',
                'method' => 'get',
                'url' => route('gruppe.bop.export.berufsfelderprobung', $gruppe->id),
            ],
            [
                'id' => 'bop-gruppe-auswertungsbogen',
                'name' => 'Auswertungsbogen BOP Gruppe',
                'format' => 'DOCX',
                'typ' => 'BOP Gruppe',
                'method' => 'get',
                'url' => route('gruppe.bop.export.auswertungsbogen-bop', $gruppe->id),
            ],
            [
                'id' => 'bop-gruppe-toilettennutzungsliste',
                'name' => 'Toilettennutzungsliste',
                'format' => 'DOCX',
                'typ' => 'BOP Gruppe',
                'method' => 'get',
                'url' => route('gruppe.bop.export.toilettennutzungsliste', $gruppe->id),
            ],
            [
                'id' => 'bop-gruppe-zertifikat-pobo',
                'name' => 'Zertifikat POBO Gruppe',
                'format' => 'DOCX',
                'typ' => 'BOP Gruppe',
                'method' => 'get',
                'url' => route('gruppe.bop.export.zertifikat-pobo', $gruppe->id),
            ],
            [
                'id' => 'bop-gruppe-teilnahme-pobo',
                'name' => 'Teilnahmebescheinigung POBO',
                'format' => 'DOCX',
                'typ' => 'BOP Gruppe',
                'method' => 'get',
                'url' => route('gruppe.bop.export.teilnahme-pobo', $gruppe->id),
            ],
            [
                'id' => 'bop-anwesenheitsdaten',
                'name' => 'Anwesenheitsdaten',
                'format' => 'SEITE',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('index-anpassung-anwesenheitsdaten', [
                    'schulId' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                ]),
            ],
            [
                'id' => 'bop-bereichsauswahl',
                'name' => 'Bereichsauswahl',
                'format' => 'SEITE',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('bereichsauswahl.index', [
                    'partnerId' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                ]),
            ],
            [
                'id' => 'bop-einteilung',
                'name' => 'Einteilung',
                'format' => 'SEITE',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('einteilung.show', [
                    'partnerId' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                ]),
            ],
            [
                'id' => 'bop-ordner-anlegen',
                'name' => 'Ordner anlegen',
                'format' => 'ORDNER',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('alleTeilnehmer.folder.create', [
                    'idSchule' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                ]),
            ],
            [
                'id' => 'bop-hausordnung',
                'name' => 'Hausordnung',
                'format' => 'PDF',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('hausordnung.export.schule.pdf', [
                    'partnerId' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                    'sortBy' => 'nachname',
                    'termin' => $termin,
                ]),
            ],
            [
                'id' => 'bop-anwesenheitsliste-tag1',
                'name' => 'Anwesenheitsliste BO Tag1',
                'format' => 'XLSX',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('anwesenheitsliste.BoTag1.export', [
                    'partnerID' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                    'klasse' => 'exportAlleKlassen',
                    'termin' => $termin,
                ]),
            ],
            [
                'id' => 'bop-teilnehmerliste',
                'name' => 'Teilnehmerliste',
                'format' => 'XLSX',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('export.teilnehmerliste.schule.excel', [
                    'schuleId' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                ]),
            ],
            [
                'id' => 'bop-anwesenheitsliste-vorbereitung',
                'name' => 'Anwesenheitsliste Vorbereitung BO Tage',
                'format' => 'XLSX',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('anwesenheitslisteVorBOTage', [
                    'schuleId' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                ]),
            ],
            [
                'id' => 'bop-anwesenheitsliste-rechnung',
                'name' => 'Anwesenheitsliste Rechnung',
                'format' => 'XLSX',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('export.anwesenheitsliste.rechnung', [
                    'idSchule' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                ]),
            ],
            [
                'id' => 'bop-einverstaendnisliste',
                'name' => 'Liste X Einverstaendniserklaerung',
                'format' => 'XLSX',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('export.elterneinverstaendniserklaerung.schule', [
                    'partnerId' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                ]),
            ],
            [
                'id' => 'bop-zertifikat-pobo',
                'name' => 'Zertifikat POBO',
                'format' => 'DOCX',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('export.zertifikat.schule.pobo', [
                    'idSchule' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                ]),
            ],
            [
                'id' => 'bop-zertifikat-pobo-pdf',
                'name' => 'Zertifikat POBO PDF',
                'format' => 'PDF',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('export.zertifikat.schule.pobo.pdf', [
                    'schuleId' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                ]),
            ],
            [
                'id' => 'bop-auswertung-pobo',
                'name' => 'Auswertung POBO',
                'format' => 'PDF',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('export.auswertungBO.schule.pdf', [
                    'schulId' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                ]),
            ],
            [
                'id' => 'bop-auswertung-runde',
                'name' => 'Auswertung POBO Runde',
                'format' => 'PDF',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('auswertungPoboModal', [
                    'schuleId' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                    'runde' => $context['runde'] ?? 'alle',
                ]),
            ],
            [
                'id' => 'bop-auswertung-ordner',
                'name' => 'BO Auswertungen in Ordner generieren',
                'format' => 'ORDNER',
                'typ' => 'BOP',
                'method' => 'get',
                'url' => route('export.auswertungBO.schule.pdf.tofolder', [
                    'schulId' => $partnerId,
                    'schuljahr' => $schuljahr,
                    'teil' => $teil,
                ]),
            ],
        ];

        $accountingItems = [
            'bop-anwesenheitsdaten',
            'bop-anwesenheitsliste-tag1',
            'bop-anwesenheitsliste-vorbereitung',
            'bop-anwesenheitsliste-rechnung',
        ];

        $selectionPermissions = [
            'bereichsauswahl.index',
            'bereichsauswahl.store',
            'bereichsauswahl.update',
            'bereichsauswahl.planning',
        ];
        $assignmentPermissions = [
            'einteilung.index',
            'einteilung.store',
            'einteilung.update',
            'einteilung.destroy',
            'einteilung.export',
            'einteilung.planning',
        ];
        $schoolExportItems = [
            'bop-hausordnung',
            'bop-zertifikat-pobo',
            'bop-zertifikat-pobo-pdf',
            'bop-auswertung-pobo',
            'bop-auswertung-runde',
        ];
        $contactDocumentItems = [
            'bop-einverstaendnisliste',
            'bop-ordner-anlegen',
            'bop-auswertung-ordner',
        ];

        return array_values(array_filter($items, function (array $item) use ($accountingItems, $selectionPermissions, $assignmentPermissions, $schoolExportItems, $contactDocumentItems): bool {
            if (in_array($item['id'], $accountingItems, true)) {
                return auth()->user()?->can('anwesenheit.abrechnung') ?? false;
            }

            if ($item['id'] === 'bop-gruppe-anwesenheitsliste') {
                return auth()->user()?->can('anwesenheit.export') ?? false;
            }

            if ($item['id'] === 'bop-bereichsauswahl') {
                return collect($selectionPermissions)->contains(
                    fn (string $permission) => auth()->user()?->can($permission) ?? false
                );
            }

            if ($item['id'] === 'bop-einteilung') {
                return collect($assignmentPermissions)->contains(
                    fn (string $permission) => auth()->user()?->can($permission) ?? false
                );
            }

            if (in_array($item['id'], $schoolExportItems, true)) {
                return auth()->user()?->can('dokumente.schule.export') ?? false;
            }

            if ($item['id'] === 'bop-teilnehmerliste') {
                return auth()->user()?->can('teilnehmer.liste.export') ?? false;
            }

            if (in_array($item['id'], $contactDocumentItems, true)) {
                return auth()->user()?->can('dokumente.ansprechpartner.manage') ?? false;
            }

            return true;
        }));
    }

    private function safeExportName(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', trim($value)) ?: 'Export';
    }
}
