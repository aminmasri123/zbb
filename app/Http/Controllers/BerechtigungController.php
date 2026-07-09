<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Inertia\Inertia;
use App\Models\RoleDataAccessSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Berechtigungskategorie;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

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
        if ($id === null) {
            $role = Role::where('guard_name', 'web')
                ->where('name', 'Administrator')
                ->first()
                ?? Role::where('guard_name', 'web')->orderBy('id')->firstOrFail();
            $id = $role->id;
        } else {
            $role = Role::where('guard_name', 'web')->findOrFail($id);
        }

        // Aktuell angemeldeten Benutzer abrufen
        $user = Auth::user();
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
                ->when(request('search'), function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->get(),

            'berechtigungskategorien' => $berechtigungskategorien, // Berechtigungen mit Kategorien

            'roleSearched' => $role,

            // Kategorien, auf die der aktuelle Benutzer Berechtigungen hat
            'kategorienDerUser' => Berechtigungskategorie::whereHas('roles', function($query) use ($userRoleIds) {
                $query->whereIn('role_id', $userRoleIds);
            })->with('permissions')->get(),

            'alleZugewiesenePermission' => $role->permissions,

            'roleId' => $id,
            'dataAccess' => RoleDataAccessSetting::valuesForRole($role),
            'dataAccessOptions' => [
                'team' => RoleDataAccessSetting::TEAM_SCOPES,
                'participant' => RoleDataAccessSetting::PARTICIPANT_SCOPES,
            ],
        ]);
    }

    public function berechtigungZuweisen(Request $request)
        {
            $data = $request->validate([
                'roleId' => ['required', 'integer', 'exists:roles,id'],
                'permissionId' => ['required', 'integer', 'exists:permissions,id'],
                'action' => ['required', 'in:addPermission,removePermission'],
            ]);

            $role = Role::findOrFail($data['roleId']);
            $permission = Permission::findOrFail($data['permissionId']);

            if ($role->name === 'Administrator' && $data['action'] === 'removePermission') {
                return response()->json([
                    'success' => false,
                    'message' => 'Die Administrator-Rolle muss alle Berechtigungen behalten.',
                ], 422);
            }

            if ($data['action'] === 'addPermission') {
                // Die Berechtigung zur Rolle hinzufügen
                $role->givePermissionTo($permission);
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                return response()->json([
                    'success' => true,
                    'message' => 'Berechtigung wurde erfolgreich zur Rolle hinzugefügt.',
                ]);
            } elseif ($data['action'] === 'removePermission') {
                // Die Berechtigung von der Rolle entfernen
                $role->revokePermissionTo($permission);
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                return response()->json(['success' => true, 'message' => 'Berechtigung von der Rolle entfernt.']);
            } else {
                return response()->json(['success' => false, 'message' => 'Ungültige Aktion.']);
            }
        }

    public function berechtigungKategorieZuweisen(Request $request)
    {
        $data = $request->validate([
            'roleId' => ['required', 'integer', 'exists:roles,id'],
            'berechtigungskategorieId' => ['required', 'integer', 'exists:berechtigungskategories,id'],
            'action' => ['required', 'in:addCategoryPermissions,removeCategoryPermissions'],
        ]);

        $role = Role::findOrFail($data['roleId']);
        $kategorie = Berechtigungskategorie::with('permissions:id,berechtigungskategorie_id')->findOrFail($data['berechtigungskategorieId']);
        $permissionIds = $kategorie->permissions->pluck('id')->values();

        if ($role->name === 'Administrator' && $data['action'] === 'removeCategoryPermissions') {
            return response()->json([
                'success' => false,
                'message' => 'Die Administrator-Rolle muss alle Berechtigungen behalten.',
            ], 422);
        }

        DB::transaction(function () use ($data, $role, $permissionIds) {
            if ($data['action'] === 'addCategoryPermissions') {
                $rows = $permissionIds
                    ->map(fn ($permissionId) => [
                        'permission_id' => $permissionId,
                        'role_id' => $role->id,
                    ])
                    ->all();

                if ($rows !== []) {
                    DB::table('role_has_permissions')->insertOrIgnore($rows);
                }

                return;
            }

            DB::table('role_has_permissions')
                ->where('role_id', $role->id)
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'message' => $data['action'] === 'addCategoryPermissions'
                ? 'Alle Berechtigungen der Kategorie wurden zur Rolle hinzugefügt.'
                : 'Alle Berechtigungen der Kategorie wurden von der Rolle entfernt.',
            'permissionIds' => $permissionIds,
        ]);
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
