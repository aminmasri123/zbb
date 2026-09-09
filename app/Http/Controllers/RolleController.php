<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class RolleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
            'beschreibung' => ['nullable', 'string', 'max:1000'],
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'color' => '#000000',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'role' => $role,
        ]);
    }
    
    public function update(Request $request, string $id)
    {
        $role = Role::where('guard_name', 'web')->findOrFail($id);
        if (is_string($request->input('display_name'))) {
            $request->merge(['display_name' => trim($request->input('display_name'))]);
        }
        $data = $request->validate(['display_name' => ['required', 'string', 'max:255']]);
        if (Role::where('guard_name', $role->guard_name)->where('id', '!=', $role->id)
            ->where(fn ($query) => $query->where('name', $data['display_name'])->orWhere('display_name', $data['display_name']))->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['display_name' => 'Diese Rollenbezeichnung ist bereits vergeben.']);
        }
        // The canonical name remains stable for hasRole(), policies and default data scopes.
        $role->update(['display_name' => $data['display_name']]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return response()->json(['message' => 'Rollenbezeichnung gespeichert.', 'role' => $role->fresh()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'Administrator') {
            return response()->json([
                'message' => 'Die Administrator-Rolle darf nicht gelöscht werden.',
            ], 422);
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => 'Rolle erfolgreich gelöscht'], 200);
    }
}
