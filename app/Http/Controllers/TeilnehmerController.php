<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Brief;
use App\Models\Gruppe;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Fahrtarten;
use App\Models\Teilnehmer;
use App\Models\Abschluesse;
use App\Models\Kontakttypen;
use App\Models\SozialeDaten;
use Illuminate\Http\Request;
use App\Models\Notizvarianten;
use App\Models\Leistungsbezuege;
use App\Models\BereichHasPersonen;
use Illuminate\Support\Facades\DB;
use App\Models\Anwesenheitsstatuten;
use Illuminate\Support\Facades\Auth;
use App\Models\PersonenHasSozialedaten;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TeilnehmerController extends Controller
{
    public function index(Request $request)
{
    $suchbegriff = $request->input('search');
    $sortierung  = $request->input('sort', 'id');
    $richtung    = strtolower($request->input('direction', 'desc'));

    $benutzer = auth()->user();

    $gruppen = Gruppe::where('personen_id', $benutzer->id)
        ->with('bereich')
        ->get();

    // Mögliche Sortierfelder
    $sortierbareSpalten = [
        'id'         => 'id',
        'vorname'    => 'vorname',
        'nachname'   => 'nachname',
        'geschlecht' => 'geschlecht',
    ];

    $sortierspalte = $sortierbareSpalten[$sortierung] ?? 'id';
    $richtung = in_array($richtung, ['asc', 'desc']) ? $richtung : 'desc';

    // Prüfen, ob User ALLE sehen darf
    $darfAlle = $benutzer->hasPermissionTo('teilnehmer.view.all');

    // Basis-Query (kein ->get() !!)
    $abfrage = Personen::query()
        ->teilnehmer()
        ->active()
        ->visibleForUser($benutzer)   // <-- dein globaler Berechtigungsscope
        ->with(['projekte.abteilung', 'standorte']);

    // Wenn KEINE Vollberechtigung → Projekte + Standorte filtern
    if (!$darfAlle) {

        // Projekte des Benutzers
        $benutzerProjektIds = $benutzer->projekte()->pluck('projekts.id')->toArray();

        // Standorte des Benutzers
        $benutzerStandortIds = $benutzer->standorte()->pluck('standorts.id')->toArray();

        $abfrage->where(function ($q) use ($benutzerProjektIds, $benutzerStandortIds) {

            // Teilnehmer aus gleichen Projekten
            if (!empty($benutzerProjektIds)) {
                $q->whereHas('projekte', function ($sub) use ($benutzerProjektIds) {
                    $sub->whereIn('projekts.id', $benutzerProjektIds);
                });
            }

            // Teilnehmer aus gleichen Standorten
            if (!empty($benutzerStandortIds)) {
                $q->orWhereHas('standorte', function ($sub) use ($benutzerStandortIds) {
                    $sub->whereIn('standorts.id', $benutzerStandortIds);
                });
            }
        });
    }

    // 🔍 Suche
    if ($suchbegriff) {
        $abfrage->where(function ($q) use ($suchbegriff) {
            $q->where(DB::raw("CONCAT(vorname, ' ', nachname)"), 'like', "%{$suchbegriff}%")
              ->orWhere(DB::raw("CONCAT(nachname, ' ', vorname)"), 'like', "%{$suchbegriff}%")
              ->orWhere('vorname', 'like', "%{$suchbegriff}%")
              ->orWhere('nachname', 'like', "%{$suchbegriff}%");
        });
    }

    // Sortieren
    $abfrage->orderBy($sortierspalte, $richtung);

    return Inertia::render('Teilnehmer/Index', [
        'teilnehmers' => $abfrage->paginate(50)->withQueryString(),
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

                 $validatedData['typ'] = 'teilnehmer';

            // Passwort hashen und Benutzer erstellen
              // Passwort hashen und Benutzer erstellen
            $teilnehmer = Personen::create($validatedData);


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
        $personen = personen::Teilnehmer()->with([
            'adresses',
            'standorte',
            'gruppen',
            'gruppen.bereich',
            'kontaktes.kontakttyp',
            'projekte',
            'baenke',
            'fahrtabrechnungen.fahrtarten',
            'fahrtabrechnungen.personal',
            'abschluesse',
            'sozialedaten',
            'notizen.notizkategorie',
            'notizen.notiztyp',
            'notizen.notizprioritaet',
            'notizen.user',
        ])->findOrFail($id);

        $personen->gruppen->each(function ($t) {
            $t->zeitgeplant = $t->pivot->zeitgeplant;
            $t->zeittatsaechlich = $t->pivot->zeittatsaechlich;
            $t->person = $t->pivot->person;
            $t->status = $t->pivot->status;
            $t->tag = $t->pivot->tag;
            $t->user = $t->pivot->user;

        });
        $anwesenheitsstatuten =Anwesenheitsstatuten::all();
        $abschluesse = Abschluesse::all();
        $personen->projekte->each(function ($projekt) {
            $projekt->pivotModel->load('zeitraume');
        });

        $notiztypen = Notizvarianten::where('typ', 'typ')->get();
        $notizkategorie = Notizvarianten::where('typ', 'kategorie')->get();
        $notizprioritaet = Notizvarianten::where('typ', 'prioritaet')->get();

        $fahrtarten = Fahrtarten::all();

        $leistungsbezuege = Leistungsbezuege::all();
        $erhalteneBriefe = auth()->user()->receivedFreigaben();

        $meineBriefe = auth()->user()->ownLetters();

        // Jetzt manuell umwandeln für Inertia:
         $teilnehmerData = $personen->toArray();

        //$betreuer = Personen::where('typ', 'mitarbeiter')->orderBy('nachname')->select('nachname', 'vorname')->get();
        $betreuer = Personen::mitarbeiter()
            ->whereHas('projekte', function($query) use ($personen) {
                $query->where('projekts.id', auth()->user()->current_team_id);
            })
            ->orderBy('nachname')
            ->select('nachname', 'vorname', 'id') // id hinzugefügt für Referenz
            ->get();


        $projekte = Projekt::orderBy('name')->get();
        $gruppen = Gruppe::where('projekt_id', Auth()->user()->current_team_id)->with('bereich', 'betreuer')->get();

        $thisProjekt = Projekt::where('id', auth()->user()->current_team_id)->first();
        $dokumente = $thisProjekt?->dokumente;
        $kontakttypen = Kontakttypen::all();
        return Inertia::render('Teilnehmer/Edit', [
            'teilnehmer' => $personen->toArray(),
            'kontakttypen' => $kontakttypen,
            'projekte' => $projekte,
            'betreuer' => $betreuer,
            'erhalteneBriefe' => $erhalteneBriefe,
            'meineBriefe' => $meineBriefe,
            'anwesenheitsstatuten' => $anwesenheitsstatuten,
            'abschluesse' => $abschluesse,
            'leistungsbezuege' => $leistungsbezuege,
            'notiztypen' => $notiztypen,
            'notizkategorie' => $notizkategorie,
            'notizprioritaet' => $notizprioritaet,
            'fahrtarten' => $fahrtarten,
            'gruppen' => $gruppen,
            'dokumente' => $dokumente,
            ],
        );
    }

    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        try {

            $validatedData = $request->validate([
                'vorname' => ['required', 'max:50'],
                'nachname' => ['required', 'max:50'],
                'geschlecht' => ['required', 'in:m,w,d'],
                'geburtsdatum' => ['nullable', 'date'],
                'bemerkungen' => ['nullable', 'string'],
            ]);
            $teilnehmer = Personen::findOrFail($id);
            $teilnehmer->update($validatedData);

            return back()->with('success', 'Teilnehmer wurde erfolgreich aktualisiert.');
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation Error', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Ein Fehler ist aufgetreten.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function updateSozialdaten(Request $request, $id)
    {
        // 1) Validierung
        $validated = $request->validate([
            'ist_drittstaatsangehoerig' => ['required', 'boolean'],
            'ist_gefluechtet'           => ['required', 'boolean'],
            'hat_migrationshintergrund' => ['required', 'boolean'],
            'hat_behinderung'           => ['required', 'boolean'],
            'leistungsbezug_id'         => ['nullable', 'exists:leistungsbezueges,id'],
            'ist_wohnsitz_stabil'       => ['required', 'boolean'],
            'teilnehmer_id'             => ['required', 'exists:personens,id'],
            'kundennummer'              => ['string', 'nullable'],
        ]);
        // 2) Speichern (create/update anhand person_id)
       PersonenHasSozialedaten::updateOrCreate(
            ['person_id' => $validated['teilnehmer_id']],
            [
            'wohnsitz_stabil' => $validated['ist_wohnsitz_stabil'],
            'leistungsbezug_id' => $validated['leistungsbezug_id'] ?? null,
            'behinderung' => $validated['hat_behinderung']  ,
            'migrationshintergrund' => $validated['hat_migrationshintergrund'],
            'gefluechtet' => $validated['ist_gefluechtet'],
            'drittstaatsangehoerig' => $validated['ist_drittstaatsangehoerig'],
            'kundennummer' =>  $validated['kundennummer'],
            ]
        );

        // ↙️ hier kommt die Swal-Nachricht rein
        return back()->with('swal', [
            'icon'  => 'success',
            'title' => 'Gespeichert',
            'text'  => 'Die Sozialdaten wurden erfolgreich gespeichert.',
            'timer' => 1600,
            'showConfirmButton' => false,
        ]);

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
