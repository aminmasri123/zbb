<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Gruppe;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\RaumHasPersonen;
use App\Models\Role;
use App\Models\Standort;
use App\Models\RoleDataAccessSetting;
use App\Notifications\ConfiguredEventNotification;
use App\Services\Auth\StaffAccountInvitationService;
use App\Services\NotificationRecipientService;
use App\Services\Projects\StaffProjectAssignmentSynchronizer;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UserController extends Controller
{
    public function __construct(
        private readonly StaffProjectAssignmentSynchronizer $projectAssignments,
        private readonly ActiveProjectContext $activeProjectContext,
        private readonly StaffAccountInvitationService $staffInvitations,
    ) {
    }

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

        $query = Personen::query()
            ->select('personens.*')
            ->leftJoin('users', 'users.person_id', '=', 'personens.id')
            ->mitarbeiter()
            ->aktiv()
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('users.username', 'like', "%{$search}%")
                        ->orWhere('personens.vorname', 'like', "%{$search}%")
                        ->orWhere('personens.nachname', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                });
            })
            ->with(['projekte', 'user.roles:id,name,color', 'user.latestStaffAccountInvitation']);

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
        } elseif (in_array($sort, ['username', 'email'])) {
            $query->orderBy("users.$sort", $direction);
        } else {
            $query->orderBy('personens.id', $direction);
        }
        $assignableProjects = $this->assignableProjectsFor($authUser);
        $users = $query->paginate(30)->withQueryString()->through(function (Personen $person): array {
            $account = $person->user;
            $invitation = $account?->latestStaffAccountInvitation;
            $invitationStatus = null;

            if ($invitation) {
                $invitationStatus = $invitation->accepted_at
                    ? 'accepted'
                    : (! $invitation->sent_at
                        ? 'delivery_failed'
                        : ($invitation->expires_at->isPast() ? 'expired' : 'pending'));
            }

            return [
                'id' => $account?->id,
                'display_id' => $person->id,
                'person_id' => $person->id,
                'username' => $account?->username,
                'email' => $account?->email,
                'has_login' => $account !== null,
                'invitation_status' => $invitationStatus,
                'invitation_expires_at' => $invitation?->expires_at,
                'person' => [
                    'id' => $person->id,
                    'vorname' => $person->vorname,
                    'nachname' => $person->nachname,
                ],
                'roles' => $account?->roles?->values() ?? [],
                'projekte' => $person->projekte->values(),
            ];
        });

        return Inertia::render('User/Index', [
            'users'        => $users,
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
        $validated = $request->validate([
            'projekt_id' => ['required', 'integer', 'exists:projekts,id'],
            'gruppe_id' => ['nullable', 'integer', 'exists:gruppes,id'],
        ]);

        $user = User::findOrFail(auth()->id());
        $projektId = (int) $validated['projekt_id'];
        $projekt = $this->activeProjectContext->forUser($user, $projektId);
        abort_unless($projekt, 403);

        $gruppeId = $request->input('gruppe_id');

        $user->current_team_id = $projektId;
        $user->default_projekt_id = $user->default_projekt_id ?: $projektId;
        $user->save();

        // Flash setzen
        session()->flash('success', "Super! \"{$projekt->name}\" wurde als aktives Projekt ausgewählt.");

        if ($gruppeId) {
            $gruppeGehortZumProjekt = Gruppe::where('id', $gruppeId)
                ->where('projekt_id', $projektId)
                ->exists();

            if (! $gruppeGehortZumProjekt) {
                return redirect()->route('gruppe.index');
            }
        }

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
        try {
            $request->merge([
                'account_setup_method' => $request->input('account_setup_method', 'manual'),
            ]);

            $validatedData = Validator::make($request->all(), [
                'first_name' => ['required', 'string', 'max:50'],
                'last_name' => ['required', 'string', 'max:50'],
                'email' => ['required', 'string', 'max:255', 'email', 'unique:users,email'],
                'username' => ['required', 'string', 'max:50', 'unique:users,username'],
                'account_setup_method' => ['required', Rule::in(['manual', 'email_invitation'])],
                'password' => [
                    Rule::requiredIf(fn () => $request->input('account_setup_method') === 'manual'),
                    'nullable',
                    'string',
                    PasswordRule::min(10)->letters()->mixedCase()->numbers(),
                    'confirmed',
                ],
                'password_confirmation' => ['nullable', 'string'],
                'rollen' => ['required', 'array', 'min:1'],
                'rollen.*' => ['exists:roles,id'],
                'projekt_zuweisungen' => ['nullable', 'array'],
                'projekt_zuweisungen.*.projekt_id' => ['nullable', 'integer', 'exists:projekts,id'],
                'projekt_zuweisungen.*.standort_ids' => ['array'],
                'projekt_zuweisungen.*.standort_ids.*' => ['integer', 'exists:standorts,id'],
                'projekt_zuweisungen.*.bereich_ids' => ['array'],
                'projekt_zuweisungen.*.bereich_ids.*' => ['integer', 'exists:bereiches,id'],
                'projekt_zuweisungen.*.default_bereich_id' => ['nullable', 'integer', 'exists:bereiches,id'],
                'projekt_zuweisungen.*.buero_raum_ids' => ['array'],
                'projekt_zuweisungen.*.buero_raum_ids.*' => ['integer', 'exists:raeumes,id'],
                'projekt_zuweisungen.*.default_buero_raum_id' => ['nullable', 'integer', 'exists:raeumes,id'],
                'projekt_zuweisungen.*.arbeitsbereich_raum_ids' => ['array'],
                'projekt_zuweisungen.*.arbeitsbereich_raum_ids.*' => ['integer', 'exists:raeumes,id'],
                'projekt_zuweisungen.*.default_arbeitsbereich_raum_id' => ['nullable', 'integer', 'exists:raeumes,id'],
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
                    'email' => Str::lower($validatedData['email']),
                    'password' => Hash::make(
                        $validatedData['account_setup_method'] === 'manual'
                            ? $validatedData['password']
                            : Str::random(64)
                    ),
                    'current_team_id' => collect($validatedData['projekt_zuweisungen'] ?? [])
                        ->pluck('projekt_id')
                        ->filter()
                        ->first(),
                ]);

                $user->roles()->sync($validatedData['rollen']);
                $this->projectAssignments->sync($person, $validatedData['projekt_zuweisungen'] ?? []);

                return $user->load('person', 'roles', 'projekte');
            });

            $name = trim(($user->person?->vorname ?? '') . ' ' . ($user->person?->nachname ?? '')) ?: $user->username;
            $invitationSent = null;

            if ($validatedData['account_setup_method'] === 'email_invitation') {
                try {
                    $this->staffInvitations->send($user, $request->user());
                    $invitationSent = true;
                } catch (\Throwable $exception) {
                    report($exception);
                    $invitationSent = false;
                }
            }

            Notification::send(
                app(NotificationRecipientService::class)->forEvent('user.created', [
                    'actor' => $request->user(),
                    'creator_user' => $request->user(),
                ]),
                new ConfiguredEventNotification([
                    'event_key' => 'user.created',
                    'message' => 'Neuer User "' . $name . '" wurde erstellt.',
                    'link' => route('user.edit', $user->id),
                    'id' => $user->id,
                    'typ' => 'User',
                ])
            );


            $message = match ($invitationSent) {
                true => "Mitarbeiter wurde angelegt. Die Einladung wurde an {$user->email} gesendet.",
                false => 'Mitarbeiter wurde angelegt, aber die Einladungs-E-Mail konnte nicht versendet werden. Sie können die Einladung in der Benutzerübersicht erneut senden.',
                default => 'Mitarbeiter und Benutzerkonto wurden erfolgreich angelegt.',
            };

            return response()->json([
                'message' => $message,
                'user' => $user,
                'setup_method' => $validatedData['account_setup_method'],
                'invitation_sent' => $invitationSent,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Bitte prüfen Sie die Eingaben.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Das Mitarbeiterkonto konnte nicht angelegt werden.',
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
            'projekt_zuweisungen.*.bereich_ids' => ['array'],
            'projekt_zuweisungen.*.bereich_ids.*' => ['integer', 'exists:bereiches,id'],
            'projekt_zuweisungen.*.default_bereich_id' => ['nullable', 'integer', 'exists:bereiches,id'],
            'projekt_zuweisungen.*.buero_raum_ids' => ['array'],
            'projekt_zuweisungen.*.buero_raum_ids.*' => ['integer', 'exists:raeumes,id'],
            'projekt_zuweisungen.*.default_buero_raum_id' => ['nullable', 'integer', 'exists:raeumes,id'],
            'projekt_zuweisungen.*.arbeitsbereich_raum_ids' => ['array'],
            'projekt_zuweisungen.*.arbeitsbereich_raum_ids.*' => ['integer', 'exists:raeumes,id'],
            'projekt_zuweisungen.*.default_arbeitsbereich_raum_id' => ['nullable', 'integer', 'exists:raeumes,id'],
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
            if ($person->typ === 'mitarbeiter') {
                $this->projectAssignments->sync($person, $validated['projekt_zuweisungen'] ?? []);
            }
        });

        return redirect()->route('user.edit', $user->id)
                         ->with('success', 'Benutzer wurde erfolgreich aktualisiert.');
    }
    public function destroy($id)
    {
        try {

            $user = User::findOrFail($id); // Suche die Abteilung

            if ((int) $user->id === (int) auth()->id()) {
                return response()->json([
                    'message' => 'Sie koennen Ihr eigenes Konto nicht direkt loeschen. Bitte reichen Sie zuerst einen Loeschantrag ein.',
                ], 403);
            }

            $user->delete(); // Lösche die Abteilung
            return response()->json(['message' => 'der Konto von ' . $user->first_name . ' ' . $user->last_name . ' wurde  erfolgreich gelöscht!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Der Konto konnte nicht gefunden werden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }

    public function destroyStaff(Request $request, int $personId)
    {
        Validator::make(
            $request->all(),
            ['confirmation' => ['required', Rule::in(['delete'])]],
            [
                'confirmation.required' => 'Bitte geben Sie "delete" ein, um die vollständige Löschung zu bestätigen.',
                'confirmation.in' => 'Die Bestätigung ist nicht korrekt. Bitte geben Sie exakt "delete" ein.',
            ],
        )->validate();

        $person = Personen::find($personId);

        if (! $person) {
            return response()->json([
                'message' => 'Der Mitarbeiter war bereits vollständig gelöscht. Die Liste wurde aktualisiert.',
                'person_id' => $personId,
                'already_deleted' => true,
            ]);
        }

        if ($person->typ !== 'mitarbeiter') {
            return response()->json([
                'message' => 'Über diese Funktion können ausschließlich Mitarbeiter gelöscht werden.',
            ], 422);
        }

        if ($person->user()->whereKey(auth()->id())->exists()) {
            return response()->json([
                'message' => 'Sie können Ihren eigenen Mitarbeiterdatensatz nicht vollständig löschen.',
            ], 403);
        }

        $name = trim("{$person->vorname} {$person->nachname}");

        try {
            DB::transaction(function () use ($person): void {
                if (! $person->delete()) {
                    throw new \RuntimeException('Der Mitarbeiterdatensatz konnte nicht gelöscht werden.');
                }
            });
        } catch (QueryException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Der Mitarbeiter kann nicht vollständig gelöscht werden, weil noch geschützte Daten mit ihm verknüpft sind. Entfernen Sie zuerst diese Zuordnungen oder deaktivieren Sie den Mitarbeiter.',
            ], 409);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Der Mitarbeiter konnte nicht vollständig gelöscht werden. Bitte versuchen Sie es erneut.',
            ], 500);
        }

        return response()->json([
            'message' => ($name !== '' ? $name : 'Der Mitarbeiter').' wurde vollständig gelöscht.',
            'person_id' => $personId,
        ]);
    }

    public function staffDeletionStatus(int $personId)
    {
        return response()->json([
            'person_id' => $personId,
            'exists' => Personen::whereKey($personId)->exists(),
        ]);
    }

    private function assignableProjectsFor(?User $user)
    {
        $query = Projekt::query()
            ->with([
                'abteilung',
                'bereiche' => fn ($query) => $query->orderBy('name'),
                'raeume' => fn ($query) => $query->with('standort:id,name')->orderBy('name'),
            ])
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
                    'projekt_id' => $rows->first()->projekt_id,
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
