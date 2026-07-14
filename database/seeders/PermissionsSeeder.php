<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('berechtigungskategories') || ! Schema::hasTable('permissions')) {
            $this->command?->warn('Berechtigungstabellen fehlen. Seeder wird uebersprungen.');

            return;
        }

        $userSeeder = app(UserSeeder::class);
        $categories = $userSeeder->permissionCategoryCatalog();

        DB::transaction(function () use ($userSeeder, $categories): void {
            foreach ($categories as $category) {
                DB::table('berechtigungskategories')->updateOrInsert(
                    ['name' => $category['name']],
                    ['beschreibung' => $category['beschreibung']]
                );
            }

            $categoryIdsByLegacyId = [];

            foreach ($categories as $legacyId => $category) {
                $categoryIdsByLegacyId[(int) $legacyId] = (int) DB::table('berechtigungskategories')
                    ->where('name', $category['name'])
                    ->value('id');
            }

            foreach ($userSeeder->permissionCatalog() as $permission) {
                $exists = DB::table('permissions')
                    ->where('name', $permission['name'])
                    ->where('guard_name', $permission['guard_name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('permissions')->insert([
                    'name' => $permission['name'],
                    'guard_name' => $permission['guard_name'],
                    'berechtigungskategorie_id' => $categoryIdsByLegacyId[$permission['berechtigungskategorie_id']],
                    'beschreibung' => $permission['beschreibung'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->assignAllPermissionsToAdministrator();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Alle Berechtigungen wurden geprueft und fehlende Eintraege angelegt.');
    }

    private function assignAllPermissionsToAdministrator(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $administratorRoleId = DB::table('roles')
            ->where('name', 'Administrator')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $administratorRoleId) {
            return;
        }

        foreach (DB::table('permissions')->where('guard_name', 'web')->pluck('id') as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $administratorRoleId,
            ]);
        }
    }
}
