<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Teilnehmer;
use App\Models\Kontakttypen;
use Illuminate\Http\Request;
use App\Models\BereichHasPersonen;
use App\Models\Gruppe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TeilnehmerController extends Controller
{
    public function index(Request $request)
    {


        $suchbegriff    = $request->input('search');
        $sortierung     = $request->input('sort', 'id');
        $richtung       = strtolower($request->input('direction', 'desc'));


        $benutzer = User::findOrFail(Auth::id());
        $gruppen = Gruppe::where('personen_id', $benutzer->id)
        ->with('bereich')
        ->get();

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
        $abfrage = Personen::query()->where('typ', 'teilnehmer')
        ->with('projekte');

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
            'gruppen' => $gruppen,
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


    public function show($id)
    {
        $personen = personen::with([
            'adresses',
            'standorte',
            'kontaktes.kontakttyp',
            'projekte',
            'baenke'
        ])->findOrFail($id);

        // Pivot + Zeiträume nachladen:
        $personen->projekte->each(function ($projekt) {
            $projekt->pivotModel->load('zeitraume');
        });

        // Jetzt manuell umwandeln für Inertia:
         $teilnehmerData = $personen->toArray();

        //$betreuer = Personen::where('typ', 'mitarbeiter')->orderBy('nachname')->select('nachname', 'vorname')->get();
        $betreuer = Personen::where('typ', 'mitarbeiter')
            ->whereHas('projekte', function($query) use ($personen) {
                $query->whereIn('projekts.id', $personen->projekte->pluck('id'));
            })
            ->orderBy('nachname')
            ->select('nachname', 'vorname', 'id') // id hinzugefügt für Referenz
            ->get();

        $projekte = Projekt::orderBy('name')->get();

        $kontakttypen = Kontakttypen::all();
        return Inertia::render('Teilnehmer/Edit', [
            'teilnehmer' => $personen->toArray(),
            'kontakttypen' => $kontakttypen,
            'projekte' => $projekte,
            'betreuer' => $betreuer
            ],
        );
    }

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
        try {
            $teilnehmer = Personen::findOrFail($id);

            $validatedData = Validator::make($request->all(), [
                'vorname' => ['required', 'max:50'],
                'nachname' => ['required', 'max:50'],
                'geschlecht' => ['required', 'in:m,w,d'],
                'geburtsdatum' => ['nullable', 'date'],
                'bemerkungen' => ['nullable', 'string'],
            ])->validate();

            $teilnehmer->update($validatedData);

            return back()->with('success', 'Teilnehmer wurde erfolgreich aktualisiert.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation Error', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ein Fehler ist aufgetreten.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $teilnehmer = Personen::findOrFail($id); // Suche die Abteilung
            $teilnehmer->delete(); // Lösche die Abteilung

            return response()->json(['message' => 'die Daten von ' . $teilnehmer->vorname . ' ' . $teilnehmer->nachname . ' wurde  erfolgreich gelöscht!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Die Daten konnte nicht gefunden werden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }




}
