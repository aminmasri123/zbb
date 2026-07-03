<?php

namespace App\Http\Controllers;

use App\Models\RoleDataAccessSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleDataAccessController extends Controller
{
    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'team_scope' => ['required', Rule::in(array_keys(RoleDataAccessSetting::TEAM_SCOPES))],
            'participant_scope' => ['required', Rule::in(array_keys(RoleDataAccessSetting::PARTICIPANT_SCOPES))],
        ]);

        $setting = RoleDataAccessSetting::updateOrCreate(
            ['role_id' => $role->id],
            $data
        );

        return response()->json([
            'message' => 'Datenzugriff wurde gespeichert.',
            'setting' => $setting,
        ]);
    }
}
