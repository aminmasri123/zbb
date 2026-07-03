<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Standort;
use App\Models\RoleDataAccessSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Notifications\CreateUserNotification;

class UserController extends Controller
{
     public function index(Request $request)
    {
        $rollen = Role::select('id', 'name')->get();

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

        $authUser = auth()->user();
        $teamScope = RoleDataAccessSetting::scopeForUser($authUser, 'team');

        $query = User::query()
            ->select('users.*') // wichtig für Pagination!
            ->leftJoin('personens', 'users.person_id', '=', 'personens.id')
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('users.username', 'like', "%{$search}%")
                        ->orWhere('personens.vorname', 'like', "%{$search}%")
                        ->orWhere('personens.nachname', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                });
            })
            ->with(['projekte', 'person', 'roles:name,color']);

        // Zugriffsbeschränkung
        $this->applyTeamVisibility($query, $authUser, $teamScope);

        // Filter nach Projekt
        if ($selectedProject) {
            $query->whereHas('projekte', function ($query) use ($selectedProject) {
                $query->where('name', $selectedProject);
            });
        }

        // Sortierung (JOIN beachten!)
        if (in_array($sort, ['vorname', 'nachname'])) {
            $query->orderBy("personens.$sort", $direction);
        } else {
            $query->orderBy("users.$sort", $direction);
        }
        $assignableProjects = $this->assignableProjectsFor($authUser);

        return Inertia::render('User/Index', [
            'users'        => $query->paginate(30)->withQueryString(),
            'authProjekte' => $assignableProjects,
            'alleProjekte' => $assignableProjects,
            'standorte'    => Standort::orderBy('name')->get(['id', 'name']),
            'rollen'       => $rollen,
        ]);
    }

    public function create()
    {
        return Inertia::render('User/CreateMitarbeiter', [
            'rollen' => Role::orderBy('name')->get(),
            'alleProjekte' => $this->assignableProjectsFor(auth()->user()),
            'alleStandorte' => Standort::orderBy('name')->get(['id', 'name']),
        ]);
    }


   public function switch(Request $request)
    {
        $user = User::findOrFail(auth()->id());
        $projektId = $request->input('projekt_id');
        $projektName = Projekt::where('id', $projektId)->value('name');

        if ($user->projekte()->where('projekts.id', $projektId)->exists()) {
            $user->current_team_id = $projektId;
            $user->save();
        }

        // Flash setzen
        session()->flash('success', "Super! \"$projektName\" wurde als aktives Projekt ausgewählt.");

        return back();
    }




    public function check(Request $request) // Typ-Hinweis für die Request-Klasse
    {
        // Erhalte die User-ID aus der Anfrage
        $id = Request::input('userId'); // Hier wird die richtige Methode auf der Request-Instanz verwendet

        // Finde den Benutzer mit der gegebenen ID
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Toggle-Logik für den Check-Status
        $user->eee = $user->eee == 1 ? 0 : 1;

        // Speichern der Änderungen
        if ($user->save()) {
            return response()->json(['success' => $user->eee]); // Rückgabe des neuen Wertes
        }

        // Fehlerhafte Antwort
        return response()->json(['error' => 'User status could not be updated'], 500);
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
         // 🔔 Alle User mit Rollen benachrichtigen
         $rollen = ['Abteilungsleitung', 'Assistenz der Abt.-Leitung']; // <- deine Rollennamen
         $empfaenger = User::role($rollen)->get();

         foreach ($empfaenger as $user) {
             $user->notify(new CreateUserNotification($user));
         }
        try {
            // Verwende die Facade für das Abrufen der Eingabedaten
            $data = $request->all(); // holt alle Daten

            // Validierung der Eingabedaten
            $validatedData = Validator::make($data, [
                'first_name' => ['required', 'max:50'],
                'last_name' => ['required', 'max:50'],
                'email' => ['required', 'max:50', 'email', 'unique:users,email'],
                'password' => ['required', 'min:8'],
                'username' => ['required', 'max:50', 'unique:users,username'],
                'rollen' => ['required', 'array'],
                'rollen.*' => ['exists:roles,id'],
                'projekt_zuweisungen' => ['nullable', 'array'],
                'projekt_zuweisungen.*.projekt_id' => ['nullable', 'integer', 'exists:projekts,id'],
                'projekt_zuweisungen.*.standort_ids' => ['array'],
                'projekt_zuweisungen.*.standort_ids.*' => ['integer', 'exists:standorts,id'],
            ])->validate();

            $user = DB::transaction(function () use ($validatedData, $request) {
                $person = Personen::create([
                    'vorname' => $validatedData['first_name'],
                    'nachname' => $validatedData['last_name'],
                    'geschlecht' => $request->input('geschlecht', 'd'),
                    'typ' => 'mitarbeiter',
                    'aktiv' => true,
                ]);

                $user = User::create([
                    'person_id' => $person->id,
                    'username' => $validatedData['username'],
                    'email' => $validatedData['email'],
                    'password' => Hash::make($validatedData['password']),
                    'current_team_id' => collect($validatedData['projekt_zuweisungen'] ?? [])
                        ->pluck('projekt_id')
                        ->filter()
                        ->first(),
                ]);

                $user->roles()->sync($validatedData['rollen']);
                $this->syncProjektZuweisungen($person, $validatedData['projekt_zuweisungen'] ?? []);

                return $user->load('person', 'roles', 'projekte');
            });


            return response()->json(['message' => 'Benutzer erfolgreich erstellt!', 'user' => $user], 201);
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
       $user = User::with([
            'roles',
            'projekte.abteilung',
        ])->findOrFail($id);

        $abteilungen = $user->projekte
            ->pluck('abteilung')   // alle Abteilungen der Projekte
            ->unique('id')         // nur eindeutige
            ->values();            // Index neu setzen

        return Inertia::render('Profile/Show-Profil', [
            'user' => $user,
            'abteilungen' => $abteilungen,
        ]);

    }


    public function edit($id)
    {
        $user = User::with('roles', 'person')->findOrFail($id);


        $rollen = Role::all();

        return Inertia::render('User/Edit', [
            'user' => $user,
            'rollen' => $rollen,
            'alleProjekte' => $this->assignableProjectsFor(auth()->user()),
            'alleStandorte' => Standort::orderBy('name')->get(['id', 'name']),
            'zuweisungen' => $user->person_id ? $this->buildProjektZuweisungen($user->person_id) : [],
        ]);
    }


    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'username'   => 'required|string|max:255|unique:users,username,' . $user->id,
            'email'      => 'required|email|max:255|unique:users,email,' . $user->id,
            'password'   => 'nullable|string|min:8|confirmed',
            'rollen' => ['required', 'array'],
            'rollen.*' => ['exists:roles,id'],
            'projekt_zuweisungen' => ['array'],
            'projekt_zuweisungen.*.projekt_id' => ['nullable', 'integer', 'exists:projekts,id'],
            'projekt_zuweisungen.*.standort_ids' => ['array'],
            'projekt_zuweisungen.*.standort_ids.*' => ['integer', 'exists:standorts,id'],
        ]);

        DB::transaction(function () use ($validated, $user) {
            $person = $user->person ?: Personen::create([
                'vorname' => $validated['first_name'],
                'nachname' => $validated['last_name'],
                'geschlecht' => 'd',
                'typ' => 'mitarbeiter',
                'aktiv' => true,
            ]);

            $person->update([
                'vorname' => $validated['first_name'],
                'nachname' => $validated['last_name'],
                'typ' => $person->typ ?: 'mitarbeiter',
            ]);

            $user->person_id = $person->id;
            $user->username = $validated['username'];
            $user->email = $validated['email'];

            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();
            $user->roles()->sync($validated['rollen']);
            $this->syncProjektZuweisungen($person, $validated['projekt_zuweisungen'] ?? []);
        });

        return redirect()->route('user.edit', $user->id)
                         ->with('success', 'Benutzer wurde erfolgreich aktualisiert.');
    }
    public function destroy($id)
    {
        try {

            $user = User::findOrFail($id); // Suche die Abteilung

            $user->delete(); // Lösche die Abteilung
            return response()->json(['message' => 'der Konto von ' . $user->first_name . ' ' . $user->last_name . ' wurde  erfolgreich gelöscht!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Der Konto konnte nicht gefunden werden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }

    private function assignableProjectsFor(?User $user)
    {
        $query = Projekt::query()
            ->with('abteilung')
            ->orderBy('name');

        if (! $user) {
            $query->whereRaw('1 = 0');
        } else {
            match (RoleDataAccessSetting::scopeForUser($user, 'team')) {
                'all' => null,
                'department' => $this->filterProjectsByDepartments($query, $this->departmentIdsFor($user)),
                'own_projects' => $this->filterProjectsByIds($query, $this->projectIdsFor($user)),
                default => $query->whereRaw('1 = 0'),
            };
        }

        return $query->get(['id', 'name', 'abteilung_id']);
    }

    private function applyTeamVisibility($query, User $user, string $scope): void
    {
        match ($scope) {
            'all' => null,
            'department' => $this->filterUsersByDepartments($query, $this->departmentIdsFor($user)),
            'own_projects' => $this->filterUsersByProjects($query, $this->projectIdsFor($user)),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function filterUsersByProjects($query, $projectIds): void
    {
        if ($projectIds->isEmpty()) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('projekte', function ($query) use ($projectIds) {
            $query->whereIn('projekts.id', $projectIds);
        });
    }

    private function filterUsersByDepartments($query, $departmentIds): void
    {
        if ($departmentIds->isEmpty()) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('projekte', function ($query) use ($departmentIds) {
            $query->whereIn('projekts.abteilung_id', $departmentIds);
        });
    }

    private function filterProjectsByIds($query, $projectIds): void
    {
        if ($projectIds->isEmpty()) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('id', $projectIds);
    }

    private function filterProjectsByDepartments($query, $departmentIds): void
    {
        if ($departmentIds->isEmpty()) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('abteilung_id', $departmentIds);
    }

    private function projectIdsFor(User $user)
    {
        return $user->projekte()->pluck('projekts.id')->filter()->unique()->values();
    }

    private function departmentIdsFor(User $user)
    {
        return $user->projekte()->pluck('projekts.abteilung_id')->filter()->unique()->values();
    }

    private function buildProjektZuweisungen(int $personId): array
    {
        return DB::table('projekt_has_personens')
            ->join('projekts', 'projekts.id', '=', 'projekt_has_personens.projekt_id')
            ->where('personen_id', $personId)
            ->select(
                'projekt_has_personens.projekt_id',
                'projekts.name as projekt_name',
                'projekt_has_personens.standort_id'
            )
            ->get()
            ->groupBy('projekt_id')
            ->map(function ($rows) {
                return [
                    'projekt_id' => $rows->first()->projekt_id,
                    'projekt_name' => $rows->first()->projekt_name,
                    'standort_ids' => $rows->pluck('standort_id')->filter()->unique()->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function syncProjektZuweisungen(Personen $person, array $zuweisungen): void
    {
        DB::table('projekt_has_personens')
            ->where('personen_id', $person->id)
            ->delete();

        foreach ($zuweisungen as $item) {
            $projektId = $item['projekt_id'] ?? null;
            $standortIds = collect($item['standort_ids'] ?? [])
                ->filter()
                ->unique()
                ->values();

            if (! $projektId || $standortIds->isEmpty()) {
                continue;
            }

            foreach ($standortIds as $standortId) {
                DB::table('projekt_has_personens')->insert([
                    'personen_id' => $person->id,
                    'projekt_id' => $projektId,
                    'standort_id' => $standortId,
                    'status' => 'aktiv',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
