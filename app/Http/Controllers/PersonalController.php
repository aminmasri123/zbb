<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Standort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class PersonalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
     public function index(Request $request)
    {
        $rollen = Role::select('id', 'name')->get();
        $standorte = Standort::all();

        $search          = $request->input('search');
        $selectedProject = $request->input('project');
        $sort            = $request->input('sort', 'id');
        $direction       = $request->input('direction', 'desc');

        // Gültige Sortierspalten
        $allowedSortColumns = [
            'id',
            'username',
            'email',
            'vorname',
            'nachname',
        ];

        // Ungültige Spalten abfangen
        if (!in_array($sort, $allowedSortColumns)) {
            $sort = 'id';
        }

        $authUser   = auth()->user();
        $adminRoles = ['Administrator', 'Geschäftsführer', 'Sekretariat'];

       $query = Personen::query()
            ->with([
                'user.roles',
                'projekte',
                //'projekte.personenStandorte',  // ✔ Standorte pro Person & pro Projekt
                'projekte.abteilung',
                'projektStandorte',            // ✔ alle Standorte der Person
                'standorte',                   // falls du generell Standorte vom User hast
                'standorte.adresse',
            ])
            ->mitarbeiter()
            ->aktiv()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('vorname', 'like', "%$search%")
                    ->orWhere('nachname', 'like', "%$search%")
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('email', 'like', "%$search%");
                    });
                });
            });

        // Zugriffsbeschränkung
        if (!$authUser->roles->whereIn('name', $adminRoles)->count()) {
            $query->whereHas('projekte', function ($query) use ($authUser) {
                $query->whereIn('projekt_id', $authUser->projekte->pluck('id'));
            });
        }

        // Filter nach Projekt
        if ($selectedProject) {
            $query->whereHas('projekte', function ($query) use ($selectedProject) {
                $query->where('name', $selectedProject);
            });
        }

        // Sortierung (JOIN beachten!)
        if (in_array($sort, ['vorname', 'nachname'])) {
            $query->orderBy("personens.$sort", $direction);
        }

        return Inertia::render('Personal/Index', [
            'users'        => $query->paginate(30)->withQueryString(),
            'authProjekte' => $authUser->projekte,
            'rollen'       => $rollen,
            'standorte'    => $standorte,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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
        $person = Personen::with('user', 'user.roles')->findOrFail($id);

        // Rollen
        $rollen = Role::orderBy('name')->get();

        // Alle Projekte & Standorte
        $alleProjekte = Projekt::orderBy('name')->get();
        $alleStandorte = Standort::orderBy('name')->get();
        // Projekt-Zuweisungen des Users (gruppiert pro Projekt, mit mehreren Standorten)
        $zuweisungen = DB::table('projekt_has_personens')
            ->join('projekts', 'projekts.id', '=', 'projekt_has_personens.projekt_id')
            ->where('personen_id', $id)
            ->select(
                'projekt_has_personens.projekt_id',
                'projekts.name as projekt_name',
                'projekt_has_personens.standort_id'
            )
            ->get()
            ->groupBy('projekt_id')
            ->map(function ($rows) {
                return [
                    'projekt_id'   => $rows->first()->projekt_id,
                    'projekt_name' => $rows->first()->projekt_name,
                    'standort_ids' => $rows->pluck('standort_id')->unique()->values()->all(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Personal/Edit', [
            'person'          => $person,
            'rollen'        => $rollen,
            'alleProjekte'  => $alleProjekte,
            'alleStandorte' => $alleStandorte,
            'zuweisungen'   => $zuweisungen,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
{
    $validated = $request->validate([
        'first_name' => ['required', 'string', 'max:255'],
        'last_name'  => ['required', 'string', 'max:255'],
        'username'   => ['required', 'string', 'max:255'],
        'email'      => ['required', 'email', 'max:255'],
        'password'   => ['nullable', 'string', 'min:8', 'confirmed'],

        'rollen'     => ['array'],
        'rollen.*'   => ['integer', 'exists:roles,id'],

        'projekt_zuweisungen'                          => ['array'],
        'projekt_zuweisungen.*.projekt_id'             => ['nullable', 'integer', 'exists:projekts,id'],
        'projekt_zuweisungen.*.standort_ids'           => ['array'],
        'projekt_zuweisungen.*.standort_ids.*'         => ['integer', 'exists:standorts,id'],
    ]);

    $person = Personen::with('user')->findOrFail($id);

    DB::transaction(function () use ($validated, $person, $request, $id) {

        // ► PERSON aktualisieren
        $person->vorname   = $validated['first_name'];
        $person->nachname  = $validated['last_name'];
        $person->save();

        // ► USER aktualisieren
        $user = $person->user;
        $user->username = $validated['username'];
        $user->email    = $validated['email'];

        if (!empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }

        $user->save();

        // ► Rollen aktualisieren
        $user->roles()->sync($validated['rollen'] ?? []);


        // -----------------------------------------
        //    P R O J E K T E   &   S T A N D O R T E
        // -----------------------------------------

        $zuweisungen = $request->input('projekt_zuweisungen', []);

        // Vorherige Einträge löschen
        DB::table('projekt_has_personens')
            ->where('personen_id', $person->id)
            ->delete();

        // Neue Einträge anlegen
        foreach ($zuweisungen as $item) {

            $projektId   = $item['projekt_id'] ?? null;
            $standortIds = $item['standort_ids'] ?? [];

            if (!$projektId || empty($standortIds)) {
                continue;
            }

            foreach ($standortIds as $sid) {
                DB::table('projekt_has_personens')->insert([
                    'personen_id' => $person->id,
                    'projekt_id'  => $projektId,
                    'standort_id' => $sid,
                    'status'      => 'aktiv',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }
    });

    return redirect()
        ->route('personal.index')
        ->with('success', 'Benutzer wurde erfolgreich aktualisiert.');
}


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
