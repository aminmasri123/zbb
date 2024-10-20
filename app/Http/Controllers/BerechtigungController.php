<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use App\Models\Berechtigungskategorie;
use Illuminate\Support\Facades\Request;
use Spatie\Permission\Models\Permission;

class BerechtigungController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id = null)
    {
        // Default Rolle ist Administrator, falls kein ID übergeben wurde
        $id = $id ?? 1;

        // Aktuell angemeldeten Benutzer abrufen
        $user = User::find(Auth::user())->first(); // Auth::user() gibt bereits das Benutzerobjekt zurück
        $userRoles = $user->getRoleNames();
        // IDs der Rollen abrufen
        $userRoleIds = Role::whereIn('name', $userRoles)->pluck('id'); // IDs der Rollen abrufen

        // Berechtigungskategorien abrufen, die den Benutzerrollen zugeordnet sind
        $berechtigungskategorien = Berechtigungskategorie::with(['permissions' => function($query) {
            $query->select('id', 'name'); // Optional: spezifische Felder abrufen
        }])
        ->whereHas('roles', function($query) use ($userRoleIds) {
            $query->whereIn('role_id', $userRoleIds); // Filtere nach den Rollen des Benutzers
        })
        ->get();

        return Inertia::render('Einstellung/RollePermission/Index', [
            'rollen' => Role::query()
                ->when(Request::input('search'), function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->get(),

            'berechtigungskategorien' => $berechtigungskategorien, // Berechtigungen mit Kategorien

            'roleSearched' => Role::findById($id),

            // Kategorien, auf die der aktuelle Benutzer Berechtigungen hat
            'kategorienDerUser' => Berechtigungskategorie::whereHas('roles', function($query) use ($userRoleIds) {
                $query->whereIn('role_id', $userRoleIds);
            })->with('permissions')->get(),

            'alleZugewiesenePermission' => Role::findById($id)->permissions,

            'roleId' => $id,
        ]);
    }






    public function berechtigungZuweisen(Request $request)
        {
            $roleId = Request::input('roleId');
            $permissionId = Request::input('permissionId');
            $action =Request::input('action');
            $role = Role::find($roleId);
            $permission = Permission::find($permissionId);

            if ($action === 'addPermission') {
                // Die Berechtigung zur Rolle hinzufügen
                $role->givePermissionTo($permission);
                return response()->json(['success' => true, 'message' => 'Berechtigung zur Rolle hinzugefügt.']);
            } elseif ($action === 'removePermission') {
                // Die Berechtigung von der Rolle entfernen
                $role->revokePermissionTo($permission);
                return response()->json(['success' => true, 'message' => 'Berechtigung von der Rolle entfernt.']);
            } else {
                return response()->json(['success' => false, 'message' => 'Ungültige Aktion.']);
            }
        }



/*
    public function storeRolle(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles',
        ]);

        try {
            $adminRole = Role::create(['name' => $request->name]);

            return redirect()->back()->with('success', 'Die Daten wurden erfolgreich gespeichert.');
        } catch (\Exception $e) {
            // Wenn ein Fehler auftritt, mache einen Rollback und gib den Fehler aus
            DB::rollback();
            return redirect()->back()->with('error', 'Ein Fehler ist aufgetreten: ' . $e->getMessage());
        }
    }

    public function destroyRolle(Role $rolle)
    {
        try {
            $rolle->delete();
            return redirect()->back()->with('success', 'Die Rolle wurde erfolgreich gelöscht.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Die Löschung der Rolle ist fehlgeschlagen.');
        }
    }

*/




    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
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
        //
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
