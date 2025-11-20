<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Projekt;
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

        $authUser   = auth()->user();
        $adminRoles = ['Administrator', 'Geschäftsführer', 'Sekretariat'];

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
        } else {
            $query->orderBy("users.$sort", $direction);
        }
        return Inertia::render('User/Index', [
            'users'        => $query->paginate(30)->withQueryString(),
            'authProjekte' => $authUser->projekte,
            'rollen'       => $rollen,
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
            ])->validate();


            // Passwort hashen und Benutzer erstellen
              // Passwort hashen und Benutzer erstellen
            $validatedData['password'] = Hash::make($validatedData['password']);
            $user = User::create($validatedData);

            // Rollen zuweisen
            $user->assignRole($validatedData['rollen']);


            $user->load('roles'); // falls du Rollen brauchst


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
        $user = User::with('roles')->findOrFail($id);


        $rollen = Role::all();

        return Inertia::render('User/Edit', [
            'user' => $user,
            'rollen' => $rollen,
        ]);
    }


    public function update(Request $request, User $user)
    {
        // Validierung
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'username'   => 'required|string|max:255|unique:users,username,' . $user->id,
            'email'      => 'required|email|max:255|unique:users,email,' . $user->id,
            'password'   => 'nullable|string|min:8|confirmed',
            'rollen' => ['required', 'array'],
            'rollen.*' => ['exists:roles,id'],
        ]);
        // User-Daten aktualisieren
        $user->first_name = $validated['first_name'];
        $user->last_name  = $validated['last_name'];
        $user->username   = $validated['username'];
        $user->email      = $validated['email'];
        $user->assignRole($validated['rollen']);


        // Passwort nur ändern, wenn eingegeben
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

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
}
