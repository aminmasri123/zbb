<?php

namespace App\Http\Controllers;

use App\Models\Anwesenheitsstatuten;
use App\Models\EinteilungBereiche;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Partner;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\Tage;
use App\Models\Zeiten;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

class EinteilungBereicheController extends Controller
{
    private const MAX_TEILNEHMER_PRO_BEREICH = 15;

    public function index($partnerId, $schuljahr, $teil)
    {
        return Inertia::render(
            'Teilnehmer/Einteilung/Index',
            $this->pagePayload((int) $partnerId, (string) $schuljahr, (string) $teil)
        );

        $projekt = Projekt::with('bereiche')->find(Auth()->user()->current_team_id);
        $partner = Partner::findOrFail($partnerId);

        // 1. Alle Bereiche laden (ohne Potenzialanalyse)
        $alle_bereiche = $projekt->bereiche
            ->where('name', '!=', 'Potenzialanalyse')
            ->values();

        $klassen = PersonenIstSchueler::query()
            ->where('schule_id', $partnerId)
            ->where('schuljahr', $schuljahr)
            ->where('teil', $teil)
            ->pluck('klasse')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        // 2. Das Results-Array mit allen Bereichen und Runden 1-3 vorbefüllen (damit sie im Frontend erscheinen)
        $results = [];
        foreach ($alle_bereiche as $b) {
            $slug = Str::slug($b->name);
            $results[$slug] = [1 => [], 2 => [], 3 => []];
        }

        // 3. EIN Query für alle Einteilungen
        $alleEinteilungen = EinteilungBereiche::with(['teilnehmende.person'])
            ->whereIn('bereich_id', $alle_bereiche->pluck('id'))
            ->whereHasMorph('teilnehmende', [PersonenIstSchueler::class], function ($q) use ($partnerId, $schuljahr, $teil) {
                $q->where('schule_id', $partnerId)->where('schuljahr', $schuljahr)->where('teil', $teil);
            })
            ->get();

        // 4. Die gefundenen Teilnehmer in das vorbefüllte Array einsortieren
        foreach ($alleEinteilungen as $e) {
            $slug = Str::slug($alle_bereiche->firstWhere('id', $e->bereich_id)->name);
            $results[$slug][$e->runde][] = $this->formatTeilnehmer($e);
        }

        return Inertia::render('Teilnehmer/Einteilung/Index', [
            'results' => $results,
            'alle_bereiche' => $alle_bereiche,
            'updated_at' => $alleEinteilungen->max('updated_at')?->toIso8601String(),
            'partner' => [
                'id' => $partner->id,
                'name' => $partner->name,
            ],
            'schuljahr' => $schuljahr,
            'teil' => $teil,
            'klassen' => $klassen,
            'anzahlBereiche' => $projekt?->bereiche->count() ?? 0,
        ]);
    }

    private function pagePayload(int $partnerId, string $schuljahr, string $teil): array
    {
        $projekt = $this->currentProjekt();
        $partner = Partner::findOrFail($partnerId);
        $alleBereiche = $this->projektBereiche($projekt);

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
            $results[$bereich->name] = [1 => [], 2 => [], 3 => []];
        }

        foreach ($schueler as $item) {
            foreach ($item->einteilungen as $einteilung) {
                $bereich = $einteilung->bereich;
                if (!$bereich || !isset($results[$bereich->name]) || !in_array((int) $einteilung->runde, [1, 2, 3], true)) {
                    continue;
                }

                $results[$bereich->name][(int) $einteilung->runde][] = $this->formatSchueler($item);
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

    private function formatSchueler(PersonenIstSchueler $m): array
    {
        $alleRunden = $m->einteilungen->pluck('bereich_id', 'runde');

        return [
            'id' => $m->id,
            'person_id' => $m->person_id,
            'vorname' => $m->person?->vorname ?? '',
            'nachname' => $m->person?->nachname ?? '',
            'geschlecht' => $m->person?->geschlecht ?? '',
            'klasse' => $m->klasse,
            'einteilung_ids' => [
                1 => $alleRunden[1] ?? null,
                2 => $alleRunden[2] ?? null,
                3 => $alleRunden[3] ?? null,
            ],
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

    private function projektBereiche(?Projekt $projekt = null)
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

    private function rundeValues(array $data)
    {
        return collect([1, 2, 3])
            ->map(fn ($runde) => $data['runde_' . $runde] ?? null)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function validateDistinctBereiche(array $data, bool $required): void
    {
        $werte = $this->rundeValues($data);

        if ($required && $werte->count() !== 3) {
            throw ValidationException::withMessages([
                'runde_1' => 'Bitte alle drei Runden auswaehlen.',
            ]);
        }

        if ($werte->unique()->count() !== $werte->count()) {
            throw ValidationException::withMessages([
                'runde_1' => 'Ein Teilnehmer darf einen Bereich nicht mehrfach besuchen.',
            ]);
        }
    }

    private function assertBereicheInProjekt($bereichIds): void
    {
        $allowed = $this->projektBereiche()->pluck('id')->map(fn ($id) => (int) $id);
        $invalid = collect($bereichIds)->map(fn ($id) => (int) $id)->diff($allowed);

        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages([
                'runde_1' => 'Der Bereich gehoert nicht zum aktuellen Projekt.',
            ]);
        }
    }

    private function freierBereich($prioritaeten, array $belegung, array $verwendet): ?int
    {
        foreach ($prioritaeten as $bereichId) {
            $bereichId = (int) $bereichId;
            if (in_array($bereichId, $verwendet, true)) {
                continue;
            }

            if (($belegung[$bereichId] ?? 0) < self::MAX_TEILNEHMER_PRO_BEREICH) {
                return $bereichId;
            }
        }

        return null;
    }

    private function teilnehmerName(PersonenIstSchueler $item): string
    {
        return trim(($item->person?->vorname ?? '') . ' ' . ($item->person?->nachname ?? '')) ?: 'Teilnehmer #' . $item->id;
    }

    private function tageIds(Carbon $start, Carbon $end)
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

    private function formatTeilnehmer($e)
    {
        $m = $e->teilnehmende;
        $isSchueler = $m instanceof \App\Models\PersonenIstSchueler;

        // Wir holen alle 3 Einteilungen für diesen Teilnehmer
        $alleRunden = $m->einteilungen->pluck('bereich_id', 'runde');

        return [
            'id'         => $m->id,
            'vorname'    => $isSchueler ? ($m->person->vorname ?? '') : $m->vorname,
            'nachname'   => $isSchueler ? ($m->person->nachname ?? '') : $m->nachname,
            'geschlecht' => $isSchueler ? ($m->person->geschlecht ?? '') : $m->geschlecht,
            'klasse'     => $isSchueler ? $m->klasse : '-',
            // Hier speichern wir die IDs für die Dropdowns (Runde 1, 2, 3)
            'einteilung_ids' => [
                1 => $alleRunden[1] ?? '',
                2 => $alleRunden[2] ?? '',
                3 => $alleRunden[3] ?? ''
            ]
        ];
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function createManual(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => 'required|integer|exists:partners,id',
            'schuljahr' => 'required|string',
            'teil' => 'required|string',
            'schueler_id' => 'required|integer|exists:personen_ist_schuelers,id',
            'runde_1' => 'required|integer|exists:bereiches,id',
            'runde_2' => 'required|integer|exists:bereiches,id',
            'runde_3' => 'required|integer|exists:bereiches,id',
        ]);

        $this->validateDistinctBereiche($validated, true);
        $this->assertBereicheInProjekt($this->rundeValues($validated));

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

        DB::transaction(function () use ($schueler, $validated) {
            foreach ([1, 2, 3] as $runde) {
                $schueler->einteilungen()->create([
                    'bereich_id' => $validated['runde_' . $runde],
                    'runde' => $runde,
                ]);
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
            'partner_id' => 'required|integer|exists:partners,id',
            'schuljahr' => 'required|string',
            'teil' => 'required|string',
        ]);

        $partnerId = (int) $validated['partner_id'];
        $schuljahr = (string) $validated['schuljahr'];
        $teil = (string) $validated['teil'];
        $bereiche = $this->projektBereiche();

        if ($bereiche->count() < 3) {
            throw ValidationException::withMessages([
                'bereiche' => 'Fuer die Einteilung werden mindestens drei Bereiche benoetigt.',
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

        if ($schueler->count() > $bereiche->count() * self::MAX_TEILNEHMER_PRO_BEREICH) {
            throw ValidationException::withMessages([
                'teilnehmer' => 'Die Anzahl der Schueler ueberschreitet die erlaubte Grenze.',
            ]);
        }

        $bereichIds = $bereiche->pluck('id')->map(fn ($id) => (int) $id)->values();
        $teilnehmerOhneAuswahl = [];

        DB::transaction(function () use ($schueler, $bereichIds, &$teilnehmerOhneAuswahl) {
            EinteilungBereiche::where('teilnehmende_type', PersonenIstSchueler::class)
                ->whereIn('teilnehmende_id', $schueler->pluck('id'))
                ->delete();

            $belegung = [];
            foreach ([1, 2, 3] as $runde) {
                foreach ($bereichIds as $bereichId) {
                    $belegung[$runde][$bereichId] = 0;
                }
            }

            foreach ($schueler as $item) {
                $auswahl = $item->bereichsauswahl;
                if (!$auswahl) {
                    $teilnehmerOhneAuswahl[] = $this->teilnehmerName($item);
                    continue;
                }

                $gewaehlteBereiche = collect([
                    $auswahl->bereich_id1,
                    $auswahl->bereich_id2,
                    $auswahl->bereich_id3,
                    $auswahl->bereich_id4,
                ])
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $bereichIds->contains($id))
                    ->unique()
                    ->values();

                if ($gewaehlteBereiche->isEmpty()) {
                    $teilnehmerOhneAuswahl[] = $this->teilnehmerName($item);
                    continue;
                }

                $prioritaeten = $gewaehlteBereiche
                    ->merge($bereichIds->diff($gewaehlteBereiche))
                    ->values();

                $verwendet = [];
                foreach ([1, 2, 3] as $runde) {
                    $bereichId = $this->freierBereich($prioritaeten, $belegung[$runde], $verwendet);
                    if (!$bereichId) {
                        continue;
                    }

                    $item->einteilungen()->create([
                        'bereich_id' => $bereichId,
                        'runde' => $runde,
                    ]);
                    $belegung[$runde][$bereichId]++;
                    $verwendet[] = $bereichId;
                }
            }
        });

        $message = 'Teilnehmer wurden erfolgreich eingeteilt.';
        if (!empty($teilnehmerOhneAuswahl)) {
            $message .= ' Teilnehmer ohne Auswahl: ' . implode(', ', $teilnehmerOhneAuswahl);
        }

        return response()->json([
            'message' => $message,
            'teilnehmerOhneAuswahl' => $teilnehmerOhneAuswahl,
            'payload' => $this->pagePayload($partnerId, $schuljahr, $teil),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
   public function update(Request $request)
    {
        $validated = $request->validate([
            'schueler_id' => 'required|integer|exists:personen_ist_schuelers,id',
            'runde_1' => 'nullable|integer|exists:bereiches,id',
            'runde_2' => 'nullable|integer|exists:bereiches,id',
            'runde_3' => 'nullable|integer|exists:bereiches,id',
            'seite' => 'nullable|string',
            'partner_id' => 'nullable|integer|exists:partners,id',
            'schuljahr' => 'nullable|string',
            'teil' => 'nullable|string',
        ]);

        $this->validateDistinctBereiche($validated, false);
        $this->assertBereicheInProjekt($this->rundeValues($validated));

        $hasContext = !empty($validated['partner_id']) && !empty($validated['schuljahr']) && !empty($validated['teil']);
        $schueler = $hasContext
            ? $this->schuelerInContext(
                (int) $validated['schueler_id'],
                (int) $validated['partner_id'],
                (string) $validated['schuljahr'],
                (string) $validated['teil']
            )
            : PersonenIstSchueler::findOrFail($validated['schueler_id']);

        DB::transaction(function () use ($schueler, $validated) {
            foreach ([1, 2, 3] as $runde) {
                $bereichId = $validated['runde_' . $runde] ?? null;
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

        $einteilung_ids = $schueler->einteilungen()->pluck('bereich_id', 'runde')->toArray();

        return response()->json([
            'message' => 'Einteilung erfolgreich aktualisiert',
            'schueler_id' => $schueler->id,
            'einteilung_ids' => $einteilung_ids,
            'payload' => $hasContext
                ? $this->pagePayload((int) $validated['partner_id'], (string) $validated['schuljahr'], (string) $validated['teil'])
                : null,
        ]);

        $request->validate([
            'schueler_id' => 'required|integer|exists:personen_ist_schuelers,id',
            'runde_1' => 'nullable|integer',
            'runde_2' => 'nullable|integer',
            'runde_3' => 'nullable|integer',
            'seite' => 'nullable|string',
        ]);

        $schueler = PersonenIstSchueler::findOrFail($request->schueler_id);

        foreach ([1, 2, 3] as $runde) {
            $bereichId = $request->{'runde_'.$runde};
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
            } else {
                if ($eintrag) $eintrag->delete();
            }
        }

        // 🔹 Lade die neuen Einteilungen zurück, um sie sofort an das Frontend zu senden
        $einteilung_ids = $schueler->einteilungen()->pluck('bereich_id', 'runde')->toArray();

        return response()->json([
            'message' => 'Einteilung erfolgreich aktualisiert',
            'schueler_id' => $schueler->id,
            'einteilung_ids' => $einteilung_ids,
        ]);
    }

    public function destroyContext(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => 'required|integer|exists:partners,id',
            'schuljahr' => 'required|string',
            'teil' => 'required|string',
        ]);

        $ids = $this->schuelerQuery((int) $validated['partner_id'], (string) $validated['schuljahr'], (string) $validated['teil'])
            ->pluck('id');

        $deleted = EinteilungBereiche::where('teilnehmende_type', PersonenIstSchueler::class)
            ->whereIn('teilnehmende_id', $ids)
            ->delete();

        return $this->payloadResponse(
            $deleted > 0 ? 'Einteilungen wurden geloescht.' : 'Es waren keine Einteilungen zum Loeschen vorhanden.',
            (int) $validated['partner_id'],
            (string) $validated['schuljahr'],
            (string) $validated['teil']
        );
    }

    public function gruppenGenerieren(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => 'required|integer|exists:partners,id',
            'schuljahr' => 'required|string',
            'teil' => 'required|string',
            'runde1von' => 'required|date',
            'runde1bis' => 'required|date|after_or_equal:runde1von',
            'runde2von' => 'required|date',
            'runde2bis' => 'required|date|after_or_equal:runde2von',
            'runde3von' => 'required|date',
            'runde3bis' => 'required|date|after_or_equal:runde3von',
            'startzeit' => 'required|date_format:H:i',
            'endzeit' => 'required|date_format:H:i|after:startzeit',
            'raum_id' => 'nullable|integer|exists:raeumes,id',
            'betreuer_id' => 'nullable|integer|exists:personens,id',
            'bereiche' => 'nullable|array',
            'bereiche.*' => 'integer|exists:bereiches,id',
        ]);

        $partnerId = (int) $validated['partner_id'];
        $schuljahr = (string) $validated['schuljahr'];
        $teil = (string) $validated['teil'];
        $projekt = $this->currentProjekt();
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

        $status = Anwesenheitsstatuten::where('status', 'unentschuldigt')->first()
            ?? Anwesenheitsstatuten::first();

        if (!$status) {
            throw ValidationException::withMessages([
                'anwesenheit' => 'Es wurde kein Anwesenheitsstatus gefunden.',
            ]);
        }

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
            &$gruppenAnzahl,
            &$pivotAnzahl
        ) {
            foreach ([1, 2, 3] as $runde) {
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
                            GruppeHasPersonen::updateOrCreate(
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
                            $pivotAnzahl++;
                        }
                    }
                }
            }
        });

        return $this->payloadResponse(
            "Gruppen wurden generiert ({$gruppenAnzahl} Gruppen, {$pivotAnzahl} Anwesenheitseintraege).",
            $partnerId,
            $schuljahr,
            $teil
        );
    }

    public function exportExcel(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => 'required|integer|exists:partners,id',
            'schuljahr' => 'required|string',
            'teil' => 'required|string',
            'eintritt' => 'required|date',
            'austritt' => 'required|date|after_or_equal:eintritt',
        ]);

        $partnerId = (int) $validated['partner_id'];
        $schuljahr = (string) $validated['schuljahr'];
        $teil = (string) $validated['teil'];
        $partner = Partner::findOrFail($partnerId);
        $bereiche = $this->projektBereiche();
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
        foreach ([1, 2, 3] as $runde) {
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
