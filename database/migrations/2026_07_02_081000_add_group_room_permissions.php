<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            [
                'name' => 'gruppe.view.all',
                'berechtigungskategorie_id' => 3,
                'beschreibung' => 'Alle Gruppen im ausgewaehlten Projekt sehen.',
            ],
            [
                'name' => 'raeumlichkeiten.index',
                'berechtigungskategorie_id' => 24,
                'beschreibung' => 'Raumuebersicht sehen.',
            ],
            [
                'name' => 'raeumlichkeiten.store',
                'berechtigungskategorie_id' => 24,
                'beschreibung' => 'Raeume anlegen.',
            ],
            [
                'name' => 'raeumlichkeiten.update',
                'berechtigungskategorie_id' => 24,
                'beschreibung' => 'Raeume bearbeiten.',
            ],
            [
                'name' => 'raeumlichkeiten.destroy',
                'berechtigungskategorie_id' => 24,
                'beschreibung' => 'Raeume loeschen.',
            ],
            [
                'name' => 'raeumlichkeiten.meldung.store',
                'berechtigungskategorie_id' => 24,
                'beschreibung' => 'Schaeden oder Probleme in Raeumen melden.',
            ],
            [
                'name' => 'raeumlichkeiten.meldung.update',
                'berechtigungskategorie_id' => 24,
                'beschreibung' => 'Raummeldungen bearbeiten oder abschliessen.',
            ],
        ];

        foreach ($permissions as $permissionData) {
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
            'gruppe.view.all',
            'raeumlichkeiten.index',
            'raeumlichkeiten.store',
            'raeumlichkeiten.update',
            'raeumlichkeiten.destroy',
            'raeumlichkeiten.meldung.store',
            'raeumlichkeiten.meldung.update',
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
