<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $rollen = Role::select('id', 'name')->get();
        $search = Request::input('search');
        $selectedProject = Request::input('project');
        $sort = Request::input('sort', 'id');  // Standardmäßig nach ID sortieren
        $direction = Request::input('direction', 'desc'); // Standardmäßig aufsteigend

        // Stelle sicher, dass die Sortierspalte nur gültige Spaltennamen enthält
        $allowedSortColumns = ['id', 'first_name', 'last_name', 'email']; // Erlaubte Spalten
        if (!in_array($sort, $allowedSortColumns)) {
            $sort = 'id'; // Fallback auf 'id' falls eine ungültige Spalte übergeben wurde
        }

        $authUser = auth()->user();
        $adminRoles = ['Administrator', 'Geschäftsführer', 'Sekretariat'];

        $query = User::query()
        ->when($search, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                // Verkette first_name und last_name
                $query->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        })->with('projekte');
        if (!$authUser->roles->whereIn('name', $adminRoles)->count()) {
            $query->whereHas('projekte', function ($query) use ($authUser) {
                $query->whereIn('projekt_id', $authUser->projekte->pluck('id'));
            });
        }

        if ($selectedProject) {
            $query->whereHas('projekte', function ($query) use ($selectedProject) {
                $query->where('name', $selectedProject);
            });
        }

        // Sortierung nach gültiger Spalte und Richtung anwenden
        $query->orderBy($sort, $direction);

        return Inertia::render('User/Index', [
            'users' => $query->paginate(10),
            'authProjekte' => $authUser->projekte,
            'rollen' => $rollen, // Verwende 'pluck', um nur die Namen der Rollen zu erhalten
        ]);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
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
            // Verwende die Facade für das Abrufen der Eingabedaten
            $data = Request::only(['first_name', 'last_name', 'email', 'password']); // spezifische Felder abrufen

            // Validierung der Eingabedaten
            $validatedData = Validator::make($data, [
                'first_name' => ['required', 'max:50'],
                'last_name' => ['required', 'max:50'],
                'email' => ['required', 'max:50', 'email', 'unique:users'],
                'password' => ['required', 'min:8'],
            ])->validate();
            // Passwort hashen und Benutzer erstellen
              // Passwort hashen und Benutzer erstellen
        $validatedData['password'] = Hash::make($validatedData['password']);
        $user = User::create($validatedData);

        return response()->json(['message' => 'Benutzer erfolgreich erstellt!', 'user' => $user], 201);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['message' => 'Validation Error', 'errors' => $e->errors()], 422);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Ein Fehler ist aufgetreten.'], 500);
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

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
