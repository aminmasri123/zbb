<?php

namespace App\Http\Controllers;

use Exception;
use Inertia\Inertia;
use App\Models\Bereich;
use App\Models\DokumentKategorie;
use App\Models\Dokumente;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Standort;
use App\Models\Abteilung;
use App\Models\Kostenstelle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjektController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $search = $request->input('search'); // Benutze input(), um den Suchparameter abzurufen

        $abteilungen = Abteilung::select('id', 'name')->get();
        $bereiche = Bereich::query()
            ->orderBy('name')
            ->get(['id', 'name', 'beschreibung']);
        $kostenstellen = Kostenstelle::query()
            ->orderBy('kostenstelle')
            ->get(['id', 'kostenstelle']);

        // Hole die Projekte mit Suchfilter und lade die notwendigen Beziehungen
        $projekte = Projekt::query()
        ->when($search, function ($query) use ($search) {
            $query->where('projekts.name', 'like', "%{$search}%"); // Beachte: 'projekts.name' ist hier qualifiziert
        })
            ->with('abteilung')
            //->with('projektzeitraume')
            ->with('zeitraume')
            ->with('bereiche')
            ->with('kostenstellen')
            ->with('dokumente.bereiche')
            ->with('dokumentKategorien')
            ->orderBy('projekts.id', 'desc') // Sortiere nach Projektname
            ->paginate(100) // Paginierung
            ->withQueryString();

            // Standardmäßige Rückgabe für die Inertia-Ansicht

        return Inertia::render('Projekt/Index', [
            'projekte' => $projekte,
            'abteilungen' => $abteilungen,
            'bereiche' => $bereiche,
            'kostenstellen' => $kostenstellen,
            'dokumente' => Dokumente::query()
                ->with('bereiche:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'typ', 'kontext', 'einsatzbereich', 'ausgabeformate', 'version', 'dateipfad', 'dateipfadName', 'beschreibung', 'aktiv']),
            'dokumentKategorien' => DokumentKategorie::query()
                ->orderBy('name')
                ->get(['id', 'name', 'beschreibung']),
        ]);
    }
    public function indexAjaxFresh(Request $request)
    {
        $search = $request->input('search'); // Benutze input(), um den Suchparameter abzurufen
        $abteilungen = Abteilung::select('id', 'name')->get();

        $projekte = Projekt::query()
        ->when($search, function ($query) use ($search) {
            $query->where('projekts.name', 'like', "%{$search}%"); // Beachte: 'projekts.name' ist hier qualifiziert
        })
            ->with('abteilung')
            ->with('zeitraume')
            ->with('bereiche')
            ->with('kostenstellen')
            ->orderBy('projekts.id', 'desc') // Sortiere nach Projektname
            ->paginate(100) // Paginierung
            ->withQueryString();

        // Überprüfe, ob die Anfrage als AJAX-Request gesendet wurde
        if ($request->ajax()) {
            return response()->json([
                'projekte' => $projekte,
                'abteilungen' => $abteilungen
            ]);
        };
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

   public function store(Request $request)
    {

        // Validierung
        $validatedData = $request->validate([
            'name'         => 'required|max:50',
            'kostenstelle' => 'nullable|max:50',
            'abteilung'    => 'required|exists:abteilungs,id',
            'antragsdatum' => 'required|date',
            'starttermin'  => 'required|date',
            'anfangsdatum' => 'required|date',
            'endtermin'    => 'required|date',
            'enddatum'     => 'required|date',
            'klassenbuch_aktiv' => 'sometimes|boolean',
            'kostenstellen' => 'nullable|array',
            'kostenstellen.*.kostenstelle_id' => 'required_with:kostenstellen|integer|exists:kostenstelles,id',
            'kostenstellen.*.gueltig_von' => 'required_with:kostenstellen|date',
            'kostenstellen.*.gueltig_bis' => 'required_with:kostenstellen|date',
            'bereiche'      => 'nullable|array',
            'bereiche.*'    => 'integer|exists:bereiches,id',
        ]);

        try {
            $bereichIds = collect($validatedData['bereiche'] ?? [])
                ->filter()
                ->unique()
                ->values()
                ->all();
            $bereichSyncData = collect($bereichIds)
                ->mapWithKeys(fn ($id) => [$id => ['aktiv' => 1]])
                ->all();
            $kostenstelleSyncData = $this->resolveKostenstelleSyncData($validatedData);

            $projekt = DB::transaction(function () use ($validatedData, $bereichSyncData, $kostenstelleSyncData) {
            // 1️⃣ Projekt erstellen
            $projekt = Projekt::create([
                'name'         => $validatedData['name'],
                'abteilung_id' => $validatedData['abteilung'],
                'klassenbuch_aktiv' => (bool) ($validatedData['klassenbuch_aktiv'] ?? false),
            ]);

            // 2️⃣ Zeitraum anlegen
            $projekt->zeitraume()->create([
                'antragsdatum' => $validatedData['antragsdatum'],
                'starttermin'  => $validatedData['starttermin'],
                'anfangsdatum' => $validatedData['anfangsdatum'],
                'endtermin'    => $validatedData['endtermin'],
                'enddatum'     => $validatedData['enddatum'],
                'model_type'   => Projekt::class,
                'model_id'     => $projekt->id,
            ]);

                $projekt->bereiche()->sync($bereichSyncData);
                $projekt->kostenstellen()->sync($kostenstelleSyncData);

                return $projekt;
            });

            // 3️⃣ Projekt mit Relationen zurückgeben
            return response()->json([
                'message' => 'Projekt erfolgreich erstellt.',
                'projekt' => $projekt->load(['abteilung', 'zeitraume', 'bereiche', 'kostenstellen'])
            ], 201);

        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {

            return response()->json([
                'error'   => 'Beim Erstellen des Projekts ist ein Fehler aufgetreten.',
                'details' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $projekt = Projekt::query()
            ->with([
                'abteilung',
                'zeitraume',
                'bereiche',
                'kostenstellen',
                'dokumente.bereiche',
                'dokumentKategorien',
                'mitarbeiter.user.roles',
            ])
            ->findOrFail($id);

        $zugewieseneMitarbeiterIds = DB::table('projekt_has_personens')
            ->where('projekt_id', $projekt->id)
            ->pluck('personen_id')
            ->unique()
            ->values();

        $fehlendeMitarbeiter = Personen::query()
            ->mitarbeiter()
            ->aktiv()
            ->with('user.roles')
            ->when($zugewieseneMitarbeiterIds->isNotEmpty(), function ($query) use ($zugewieseneMitarbeiterIds) {
                $query->whereNotIn('id', $zugewieseneMitarbeiterIds);
            })
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();

        return Inertia::render('Projekt/Show', [
            'projekt' => $projekt,
            'fehlendeMitarbeiter' => $fehlendeMitarbeiter,
            'alleStandorte' => Standort::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Validierung
        $validatedData = $request->validate([
            'name' => 'required|max:50',
            'kostenstelle' => 'nullable|max:50',
            'abteilung' => 'required|exists:abteilungs,id',
            'antragsdatum' => 'required_without:zeitraume|date',
            'starttermin' => 'required_without:zeitraume|date',
            'anfangsdatum' => 'required_without:zeitraume|date',
            'endtermin' => 'required_without:zeitraume|date',
            'enddatum' => 'required_without:zeitraume|date',
            'klassenbuch_aktiv' => 'sometimes|boolean',
            'zeitraume' => 'sometimes|array|min:1',
            'zeitraume.*.id' => 'nullable|integer|exists:zeitraums,id',
            'zeitraume.*.antragsdatum' => 'required_with:zeitraume|date',
            'zeitraume.*.starttermin' => 'required_with:zeitraume|date',
            'zeitraume.*.anfangsdatum' => 'required_with:zeitraume|date',
            'zeitraume.*.endtermin' => 'required_with:zeitraume|date',
            'zeitraume.*.enddatum' => 'required_with:zeitraume|date',
            'kostenstellen' => 'sometimes|array',
            'kostenstellen.*.kostenstelle_id' => 'required_with:kostenstellen|integer|exists:kostenstelles,id',
            'kostenstellen.*.gueltig_von' => 'required_with:kostenstellen|date',
            'kostenstellen.*.gueltig_bis' => 'required_with:kostenstellen|date',
            'bereiche' => 'sometimes|array',
            'bereiche.*' => 'integer|exists:bereiches,id',
        ]);

        try {
            $bereichIds = collect($validatedData['bereiche'] ?? [])
                ->filter()
                ->unique()
                ->values()
                ->all();
            $bereichSyncData = collect($bereichIds)
                ->mapWithKeys(fn ($id) => [$id => ['aktiv' => 1]])
                ->all();
            $kostenstelleSyncData = $this->resolveKostenstelleSyncData($validatedData);

            $projekt = DB::transaction(function () use ($id, $request, $validatedData, $bereichSyncData, $kostenstelleSyncData) {
            // Projekt finden
            $projekt = Projekt::findOrFail($id);

            // Basisdaten updaten
            $payload = [
                'name' => $validatedData['name'],
                'abteilung_id' => $validatedData['abteilung'],
            ];

            if ($request->has('klassenbuch_aktiv')) {
                $payload['klassenbuch_aktiv'] = (bool) $validatedData['klassenbuch_aktiv'];
            }

            $projekt->update($payload);

            if ($request->has('zeitraume')) {
                $this->syncProjektZeitraume($projekt, $validatedData['zeitraume']);
            } else {
                // Rueckwaertskompatibel fuer alte Formulare: nur den ersten Zeitraum aktualisieren.
                $zeitraum = $projekt->zeitraume()->first();
                if ($zeitraum) {
                    $zeitraum->update([
                        'antragsdatum' => $validatedData['antragsdatum'],
                        'starttermin' => $validatedData['starttermin'],
                        'anfangsdatum' => $validatedData['anfangsdatum'],
                        'endtermin' => $validatedData['endtermin'],
                        'enddatum' => $validatedData['enddatum'],
                    ]);
                } else {
                    $projekt->zeitraume()->create([
                        'antragsdatum' => $validatedData['antragsdatum'],
                        'starttermin' => $validatedData['starttermin'],
                        'anfangsdatum' => $validatedData['anfangsdatum'],
                        'endtermin' => $validatedData['endtermin'],
                        'enddatum' => $validatedData['enddatum'],
                        'model_type' => Projekt::class,
                        'model_id' => $projekt->id,
                    ]);
                }
            }

                if ($request->has('bereiche')) {
                    $projekt->bereiche()->sync($bereichSyncData);
                }

                if ($request->has('kostenstellen')) {
                    $projekt->kostenstellen()->sync($kostenstelleSyncData);
                }

                return $projekt;
            });

            return response()->json([
                'message' => 'Projekt erfolgreich aktualisiert.',
                'projekt' => $projekt->load(['zeitraume', 'abteilung', 'bereiche', 'kostenstellen']) // Relationen nachladen
            ], 200);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Update fehlgeschlagen: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateDokumente(Request $request, Projekt $projekt)
    {
        $user = auth()->user();
        if (!$user?->can('projekt.update') && !$user?->can('projekt.store') && !$user?->can('projekt.index')) {
            abort(403);
        }

        $validated = $request->validate([
            'dokumente' => 'array',
            'dokumente.*.id' => 'required|integer|exists:dokumentes,id',
            'dokumente.*.gruppen_export' => 'boolean',
            'dokumente.*.serienbrief' => 'boolean',
        ]);

        $syncData = collect($validated['dokumente'] ?? [])
            ->unique('id')
            ->values()
            ->mapWithKeys(function ($entry, $index) {
                return [
                    (int) $entry['id'] => [
                        'gruppen_export' => (bool) ($entry['gruppen_export'] ?? true),
                        'serienbrief' => (bool) ($entry['serienbrief'] ?? false),
                        'sort_order' => $index + 1,
                    ],
                ];
            })
            ->all();

        $projekt->dokumente()->sync($syncData);

        return response()->json([
            'message' => 'Export-Vorlagen wurden aktualisiert.',
            'projekt' => $projekt->fresh()->load(['abteilung', 'zeitraume', 'bereiche', 'kostenstellen', 'dokumente.bereiche', 'dokumentKategorien']),
        ]);
    }

    private function resolveKostenstelleSyncData(array $validatedData): array
    {
        $kostenstellen = collect($validatedData['kostenstellen'] ?? [])
            ->filter(fn ($entry) => is_array($entry) && !empty($entry['kostenstelle_id']))
            ->values();

        if ($kostenstellen->isNotEmpty()) {
            return $kostenstellen
                ->mapWithKeys(function ($entry) {
                    if (($entry['gueltig_bis'] ?? '') < ($entry['gueltig_von'] ?? '')) {
                        throw ValidationException::withMessages([
                            'kostenstellen' => 'Das Ende der Kostenstelle darf nicht vor dem Anfang liegen.',
                        ]);
                    }

                    return [
                        (int) $entry['kostenstelle_id'] => [
                            'gueltig_von' => $entry['gueltig_von'],
                            'gueltig_bis' => $entry['gueltig_bis'],
                        ],
                    ];
                })
                ->all();
        }

        $kostenstelle = trim($validatedData['kostenstelle'] ?? '');

        if ($kostenstelle === '') {
            return [];
        }

        return [
            Kostenstelle::firstOrCreate([
                'kostenstelle' => $kostenstelle,
            ])->id => [
                'gueltig_von' => null,
                'gueltig_bis' => null,
            ],
        ];
    }

    private function syncProjektZeitraume(Projekt $projekt, array $zeitraume): void
    {
        $existingIds = $projekt->zeitraume()->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($zeitraume as $zeitraumData) {
            if (($zeitraumData['enddatum'] ?? '') < ($zeitraumData['anfangsdatum'] ?? '')) {
                throw ValidationException::withMessages([
                    'zeitraume' => 'Das Enddatum darf nicht vor dem Anfangsdatum liegen.',
                ]);
            }

            $payload = [
                'antragsdatum' => $zeitraumData['antragsdatum'],
                'starttermin' => $zeitraumData['starttermin'],
                'anfangsdatum' => $zeitraumData['anfangsdatum'],
                'endtermin' => $zeitraumData['endtermin'],
                'enddatum' => $zeitraumData['enddatum'],
            ];

            $zeitraumId = isset($zeitraumData['id']) ? (int) $zeitraumData['id'] : null;

            if ($zeitraumId && !in_array($zeitraumId, $existingIds, true)) {
                throw ValidationException::withMessages([
                    'zeitraume' => 'Ein Zeitraum gehoert nicht zu diesem Projekt.',
                ]);
            }

            if ($zeitraumId && in_array($zeitraumId, $existingIds, true)) {
                $projekt->zeitraume()->whereKey($zeitraumId)->update($payload);
                continue;
            }

            $projekt->zeitraume()->create([
                ...$payload,
                'model_type' => Projekt::class,
                'model_id' => $projekt->id,
            ]);
        }
    }



    public function destroy($id)
    {
        try {
            $projekt = Projekt::findOrFail($id);

            // Optional: Überprüfe, ob die Projekt gelöscht werden kann (z.B. durch Beziehungen)
            // if ($abteilung->hasRelations()) { ... }

            $projekt->delete(); // Lösche die Projekt

            return response()->json(['message' => 'Projekt erfolgreich gelöscht!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Projekt nicht gefunden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
