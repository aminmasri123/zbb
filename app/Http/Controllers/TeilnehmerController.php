<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Teilnehmer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TeilnehmerController extends Controller
{
    /* public function index(Request $request)
    {
        $search     = $request->input('search');
        $sort       = $request->input('sort', 'id');
        $direction  = strtolower($request->input('direction', 'desc'));
<<<<<<< HEAD
        $user       = User::findOrFail(Auth::id());
        $benutzerDarfAlleTeilnehmerSehen = $user->hasPermissionTo('view_all_teilnehmer');

        $default_projekt = $user->current_team_id;
=======
        $projektId  = $request->input('projekt_id');
        $user = auth()->user();
>>>>>>> fdca8b45f0516a1740e5116d4cb54714f5437c16

        // Falls kein Projekt ausgewählt wurde → Default-Projekt nehmen
        if (!$projektId && $user->default_projekt_id) {
            $projektId = $user->default_projekt_id;
        }
        // Prüfen, ob der User überhaupt zu diesem Projekt gehört
        $allowedProjektIds = $user->projekte()->pluck('id')->toArray();

        if (!$projektId || !in_array($projektId, $allowedProjektIds)) {
            // Kein Projekt oder User ist nicht berechtigt → nichts anzeigen
            return Inertia::render('Teilnehmer/Index', [
                'teilnehmers' => collect([]),
                'filters' => [
                    'search'     => $search,
                    'sort'       => $sort,
                    'direction'  => $direction,
                    'projekt_id' => null,
                ],
            ]);
        }
        $sortMap = [
            'id'         => 'id',
            'vorname'    => 'vorname',
            'nachname'   => 'nachname',
            'geschlecht' => 'geschlecht',
        ];

        $sortColumn = $sortMap[$sort] ?? 'id';
        $direction  = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

<<<<<<< HEAD
        // Prüfen, ob das default Projekt dem User zugewiesen ist
        $user_project_ids = $user->projekte()->pluck('projekts.id')->toArray();

        $use_default_filter = in_array($default_projekt, $user_project_ids);

        $query = Teilnehmer::query()
            ->whereHas('projekte', function ($q) use ($user, $use_default_filter, $default_projekt) {
                $q->when($use_default_filter, function ($q) use ($default_projekt) {
                    $q->where('projekts.id', $default_projekt);
                })
                ->whereHas('users', function ($q2) use ($user) {
                    $q2->where('users.id', $user->id);
=======
        $query = Teilnehmer::query()
            ->when($projektId, function ($q) use ($projektId) {
                $q->whereHas('projekte', function ($q2) use ($projektId) {
                    $q2->where('projekts.id', $projektId);
>>>>>>> fdca8b45f0516a1740e5116d4cb54714f5437c16
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->whereRaw("CONCAT(vorname, ' ', nachname) LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("CONCAT(nachname, ' ', vorname) LIKE ?", ["%{$search}%"])
                    ->orWhere('vorname', 'like', "%{$search}%")
                    ->orWhere('nachname', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumn, $direction);

        return Inertia::render('Teilnehmer/Index', [
            'teilnehmers' => $query->paginate(50),
            'filters' => [
                'search'     => $search,
                'sort'       => $sort,
                'direction'  => $direction,
                'projekt_id' => $projektId,
            ],
        ]);
    } */


   public function index(Request $request)
    {
        $suchbegriff    = $request->input('search');
        $sortierung     = $request->input('sort', 'id');
        $richtung       = strtolower($request->input('direction', 'desc'));

        $benutzer = User::findOrFail(Auth::id());

        // Mapping für erlaubte Sortierspalten
        $sortierbareSpalten = [
            'id'         => 'id',
            'vorname'    => 'vorname',
            'nachname'   => 'nachname',
            'geschlecht' => 'geschlecht',
        ];

        $sortierspalte = $sortierbareSpalten[$sortierung] ?? 'id';
        $richtung = in_array($richtung, ['asc', 'desc']) ? $richtung : 'desc';

        // Berechtigungsprüfung: Darf Benutzer alle Teilnehmer sehen?
        $darfAlleTeilnehmerSehen = $benutzer->hasPermissionTo('teilnehmer.view.all');

        // Basis-Query
        $abfrage = Teilnehmer::query();

        if (!$darfAlleTeilnehmerSehen) {
            // Wenn Benutzer keine volle Berechtigung hat → einschränken
            $benutzerProjekt = [$benutzer->current_team_id];
            $benutzerProjektIds  = $benutzer->projekte()->pluck('projekts.id')->toArray();
            $benutzerStandortIds = $benutzer->standorte()->pluck('standorts.id')->toArray();
            $abfrage->whereHas('projekte', function ($query) use ($benutzerProjekt) {
                $query->whereIn('projekts.id', $benutzerProjekt);
            })->whereHas('standorte', function ($query) use ($benutzerStandortIds) {
                $query->whereIn('standorts.id', $benutzerStandortIds);
            });

        }

        // Suche anwenden
        $abfrage->when($suchbegriff, function ($query) use ($suchbegriff) {
            $query->where(function ($unterabfrage) use ($suchbegriff) {
                $unterabfrage->where(DB::raw("CONCAT(vorname, ' ', nachname)"), 'like', "%{$suchbegriff}%")
                            ->orWhere(DB::raw("CONCAT(nachname, ' ', vorname)"), 'like', "%{$suchbegriff}%")
                            ->orWhere('vorname', 'like', "%{$suchbegriff}%")
                            ->orWhere('nachname', 'like', "%{$suchbegriff}%");
            });
        });

        // Sortierung anwenden
        $abfrage->orderBy($sortierspalte, $richtung);

        // Ergebnis zurückgeben
        return Inertia::render('Teilnehmer/Index', [
            'teilnehmers' => $abfrage->paginate(50),
            'filters' => [
                'search'    => $suchbegriff,
                'sort'      => $sortierung,
                'direction' => $richtung,
            ],
        ]);
    }

    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            // Verwende die Facade für das Abrufen der Eingabedaten
            $data = $request->all(); // holt alle Daten

            // Validierung der Eingabedaten
            $validatedData = Validator::make($data, [
                'vorname' => ['required', 'max:50'],
                'nachname' => ['required', 'max:50'],
                'geschlecht' => ['required', 'in:m,w,d'],
            ])->validate();


            // Passwort hashen und Benutzer erstellen
              // Passwort hashen und Benutzer erstellen
            $teilnehmer = Teilnehmer::create($validatedData);


            return response()->json(['message' => 'Benutzer erfolgreich erstellt!', 'teilnehmer' => $teilnehmer], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
             return response()->json(['message' => 'Validation Error', 'errors' => $e->errors()], 422);
         } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Ein Fehler ist aufgetreten.',
                    'error'   => $e->getMessage(),   // <-- eigentliche Ursache
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
        //
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
        //
    }

    public function destroy($id)
    {
        try {
            $teilnehmer = Teilnehmer::findOrFail($id); // Suche die Abteilung
            $teilnehmer->delete(); // Lösche die Abteilung

            return response()->json(['message' => 'die Daten von ' . $teilnehmer->vorname . ' ' . $teilnehmer->nachname . ' wurde  erfolgreich gelöscht!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Die Daten konnte nicht gefunden werden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
