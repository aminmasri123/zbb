<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Inertia\Inertia;
use App\Models\RoleDataAccessSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Berechtigungskategorie;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            $query->select('id', 'name', 'display_name', 'beschreibung', 'berechtigungskategorie_id');
        }])
        ->whereHas('roles', function($query) use ($userRoleIds) {
            $query->whereIn('role_id', $userRoleIds); // Filtere nach den Rollen des Benutzers
        })
        ->get();

        $kategorienDerUser = Berechtigungskategorie::whereHas('roles', function($query) use ($userRoleIds) {
            $query->whereIn('role_id', $userRoleIds);
        })->with('permissions')->get();

        $this->attachPermissionDisplayNames($berechtigungskategorien);
        $this->attachPermissionDisplayNames($kategorienDerUser);

        return Inertia::render('Einstellung/RollePermission/Index', [
            'rollen' => Role::query()
                ->when(request('search'), function ($query, $search) {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->get(),

            'berechtigungskategorien' => $berechtigungskategorien, // Berechtigungen mit Kategorien

            'roleSearched' => $role,

            // Kategorien, auf die der aktuelle Benutzer Berechtigungen hat
            'kategorienDerUser' => $kategorienDerUser,

            'alleZugewiesenePermission' => $role->permissions,

            'roleId' => $id,
            'dataAccess' => RoleDataAccessSetting::valuesForRole($role),
            'dataAccessOptions' => [
                'team' => RoleDataAccessSetting::TEAM_SCOPES,
                'participant' => RoleDataAccessSetting::PARTICIPANT_SCOPES,
            ],
        ]);
    }

    private function attachPermissionDisplayNames($categories): void
    {
        $permissionNames = $categories
            ->flatMap(fn ($category) => $category->permissions ?? collect())
            ->pluck('name')
            ->filter(fn ($name) => str_starts_with((string) $name, 'dokumente.export.'))
            ->unique()
            ->values();

        $documentNames = $permissionNames->isEmpty()
            ? collect()
            : DB::table('dokumentes')
                ->whereIn('export_permission', $permissionNames)
                ->pluck('name', 'export_permission');

        $categories->each(function ($category) use ($documentNames): void {
            $category->permissions->each(function ($permission) use ($documentNames): void {
                $permission->display_name = $permission->display_name
                    ?: ($documentNames[$permission->name] ?? $permission->name);
                $permission->technical_name = $permission->name;
            });
        });
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
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\pL\pN][\pL\pN._:-]*$/u',
                Rule::unique('permissions', 'name')->where('guard_name', 'web'),
            ],
            'display_name' => ['required', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string', 'max:5000'],
            'berechtigungskategorie_id' => ['required', 'integer', 'exists:berechtigungskategories,id'],
            'assign_to_role' => ['sometimes', 'boolean'],
            'role_id' => ['nullable', 'required_if:assign_to_role,true', 'integer', 'exists:roles,id'],
        ], [
            'name.regex' => 'Der technische Name darf nur Buchstaben, Zahlen, Punkte, Doppelpunkte, Unterstriche und Bindestriche enthalten.',
        ]);

        $category = Berechtigungskategorie::query()
            ->whereKey($data['berechtigungskategorie_id'])
            ->whereHas('roles', fn ($roles) => $roles->whereIn('roles.id', $request->user()->roles()->pluck('roles.id')))
            ->firstOrFail();

        $permission = DB::transaction(function () use ($category, $data) {
            $permission = Permission::create([
                'name' => $data['name'],
                'display_name' => $data['display_name'],
                'guard_name' => 'web',
                'berechtigungskategorie_id' => $category->id,
                'beschreibung' => $data['beschreibung'] ?? null,
            ]);

            $roles = Role::query()
                ->where('guard_name', 'web')
                ->where(function ($query) use ($data) {
                    $query->where('name', 'Administrator');

                    if (($data['assign_to_role'] ?? false) && isset($data['role_id'])) {
                        $query->orWhere('id', $data['role_id']);
                    }
                })
                ->get();

            foreach ($roles as $role) {
                $role->berechtigungskategories()->syncWithoutDetaching([$category->id]);
                $role->givePermissionTo($permission);
            }

            return $permission;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'technical_name' => $permission->name,
                'display_name' => $permission->display_name,
                'beschreibung' => $permission->beschreibung,
                'berechtigungskategorie_id' => $permission->berechtigungskategorie_id,
            ],
        ], 201);
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
