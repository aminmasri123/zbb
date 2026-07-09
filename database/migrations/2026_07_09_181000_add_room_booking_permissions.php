<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            [
                'name' => 'raeumlichkeiten.buchung.store',
                'berechtigungskategorie_id' => 24,
                'beschreibung' => 'Raumbuchungen anlegen.',
            ],
            [
                'name' => 'raeumlichkeiten.buchung.update',
                'berechtigungskategorie_id' => 24,
                'beschreibung' => 'Raumbuchungen bearbeiten.',
            ],
            [
                'name' => 'raeumlichkeiten.buchung.destroy',
                'berechtigungskategorie_id' => 24,
                'beschreibung' => 'Raumbuchungen stornieren.',
            ],
        ];

        $existingCategoryIds = DB::table('berechtigungskategories')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($permissions as $permissionData) {
            if (! in_array((int) $permissionData['berechtigungskategorie_id'], $existingCategoryIds, true)) {
                continue;
            }

            DB::table('permissions')->updateOrInsert(
                [
                    'name' => $permissionData['name'],
                    'guard_name' => 'web',
                ],
                [
                    'berechtigungskategorie_id' => $permissionData['berechtigungskategorie_id'],
                    'beschreibung' => $permissionData['beschreibung'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', collect($permissions)->pluck('name')->all())
            ->where('guard_name', 'web')
            ->pluck('id');

        $roleIds = DB::table('roles')
            ->whereIn('name', ['Administrator', 'Developer'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $permissionNames = [
            'raeumlichkeiten.buchung.store',
            'raeumlichkeiten.buchung.update',
            'raeumlichkeiten.buchung.destroy',
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
