<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UserPermissionController extends Controller
{
    public function update(Request $request, User $user)
    {
        abort_unless($user->person?->typ === 'mitarbeiter', 404);

        if ($user->hasRole('Administrator')) {
            return back()->with('error', 'Bei Administratoren werden alle Berechtigungen ausschließlich über die Administrator-Rolle verwaltet.');
        }

        $data = $request->validate([
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => [
                'integer',
                Rule::exists('permissions', 'id')->where('guard_name', 'web'),
            ],
        ]);

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('id', array_values(array_unique($data['permission_ids'])))
            ->get();

        $user->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Zusätzliche Berechtigungen wurden aktualisiert.');
    }
}
