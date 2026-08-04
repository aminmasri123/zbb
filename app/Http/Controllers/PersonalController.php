<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\RaumHasPersonen;
use App\Models\Role;
use App\Models\Standort;
use App\Services\Projects\StaffProjectAssignmentSynchronizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PersonalController extends Controller
{
    public function __construct(private readonly StaffProjectAssignmentSynchronizer $projectAssignments)
    {
    }

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
            'authProjekte' => $authUser->projekte()
                ->with([
                    'bereiche' => fn ($query) => $query->orderBy('name'),
                    'raeume' => fn ($query) => $query->with('standort:id,name')->orderBy('name'),
                ])
                ->orderBy('name')
                ->get(),
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
        $alleProjekte = Projekt::with([
            'bereiche' => fn ($query) => $query->orderBy('name'),
            'raeume' => fn ($query) => $query->with('standort:id,name')->orderBy('name'),
        ])->orderBy('name')->get();
        $alleStandorte = Standort::orderBy('name')->get();
        // Projekt-Zuweisungen des Users (gruppiert pro Projekt, mit mehreren Standorten)
        $zuweisungen = $this->buildProjektZuweisungen((int) $id);

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
        'projekt_zuweisungen.*.bereich_ids'            => ['array'],
        'projekt_zuweisungen.*.bereich_ids.*'          => ['integer', 'exists:bereiches,id'],
        'projekt_zuweisungen.*.default_bereich_id'     => ['nullable', 'integer', 'exists:bereiches,id'],
        'projekt_zuweisungen.*.buero_raum_ids'         => ['array'],
        'projekt_zuweisungen.*.buero_raum_ids.*'       => ['integer', 'exists:raeumes,id'],
        'projekt_zuweisungen.*.default_buero_raum_id'  => ['nullable', 'integer', 'exists:raeumes,id'],
        'projekt_zuweisungen.*.arbeitsbereich_raum_ids' => ['array'],
        'projekt_zuweisungen.*.arbeitsbereich_raum_ids.*' => ['integer', 'exists:raeumes,id'],
        'projekt_zuweisungen.*.default_arbeitsbereich_raum_id' => ['nullable', 'integer', 'exists:raeumes,id'],
    ]);

    $person = Personen::with('user')->findOrFail($id);

    DB::transaction(function () use ($validated, $person) {

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

        $this->projectAssignments->sync($person, $validated['projekt_zuweisungen'] ?? []);
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

    private function buildProjektZuweisungen(int $personId): array
    {
        $assignments = DB::table('projekt_has_personens')
            ->join('projekts', 'projekts.id', '=', 'projekt_has_personens.projekt_id')
            ->where('personen_id', $personId)
            ->select(
                'projekt_has_personens.id as projekt_has_personen_id',
                'projekt_has_personens.projekt_id',
                'projekts.name as projekt_name',
                'projekt_has_personens.standort_id'
            )
            ->get();

        if ($assignments->isEmpty()) {
            return [];
        }

        $assignmentIds = $assignments
            ->pluck('projekt_has_personen_id')
            ->filter()
            ->unique()
            ->values();

        $bereichRows = DB::table('bereich_has_personens')
            ->whereIn('projekt_has_personen_id', $assignmentIds)
            ->get()
            ->groupBy('projekt_has_personen_id');

        $raumRows = DB::table('raum_has_personens')
            ->whereIn('projekt_has_personen_id', $assignmentIds)
            ->get()
            ->groupBy('projekt_has_personen_id');

        return $assignments
            ->groupBy('projekt_id')
            ->map(function ($rows) use ($bereichRows, $raumRows) {
                $rowAssignmentIds = $rows->pluck('projekt_has_personen_id')->filter()->unique()->values();
                $bereiche = $rowAssignmentIds
                    ->flatMap(fn ($assignmentId) => $bereichRows->get($assignmentId, collect()));
                $raeume = $rowAssignmentIds
                    ->flatMap(fn ($assignmentId) => $raumRows->get($assignmentId, collect()));

                $bereichIds = $bereiche->pluck('bereich_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
                $defaultBereichId = $bereiche->first(fn ($row) => (bool) $row->is_default)?->bereich_id;
                $buero = $raeume->where('assignment_type', RaumHasPersonen::TYPE_BUERO);
                $arbeitsbereiche = $raeume->where('assignment_type', RaumHasPersonen::TYPE_ARBEITSBEREICH);
                $bueroIds = $buero->pluck('raum_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
                $arbeitsbereichIds = $arbeitsbereiche->pluck('raum_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
                $defaultBueroId = $buero->first(fn ($row) => (bool) $row->is_default)?->raum_id;
                $defaultArbeitsbereichId = $arbeitsbereiche->first(fn ($row) => (bool) $row->is_default)?->raum_id;

                return [
                    'projekt_id'   => $rows->first()->projekt_id,
                    'projekt_name' => $rows->first()->projekt_name,
                    'standort_ids' => $rows->pluck('standort_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
                    'bereich_ids' => $bereichIds->all(),
                    'default_bereich_id' => $defaultBereichId && $bereichIds->contains($defaultBereichId)
                        ? (int) $defaultBereichId
                        : null,
                    'buero_raum_ids' => $bueroIds->all(),
                    'default_buero_raum_id' => $defaultBueroId && $bueroIds->contains((int) $defaultBueroId)
                        ? (int) $defaultBueroId
                        : null,
                    'arbeitsbereich_raum_ids' => $arbeitsbereichIds->all(),
                    'default_arbeitsbereich_raum_id' => $defaultArbeitsbereichId && $arbeitsbereichIds->contains((int) $defaultArbeitsbereichId)
                        ? (int) $defaultArbeitsbereichId
                        : null,
                ];
            })
            ->values()
            ->all();
    }
}
