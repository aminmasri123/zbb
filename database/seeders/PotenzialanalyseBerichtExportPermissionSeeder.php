<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class PotenzialanalyseBerichtExportPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $newPermission = 'gruppe.bop.export.berichte-pa';
        $legacyPermission = 'gruppe.bop.export.auswertungsbogen-pa';
        $description = 'Erlaubt den Export von PA-Berichten fuer einzelne Teilnehmer und Gruppen.';

        $categoryId = $this->findCategoryForLegacyPermission($legacyPermission);
        if (! $categoryId) {
            $this->command?->error(sprintf('Keine Kategorie fuer %s gefunden. Seeder abgebrochen.', $legacyPermission));

            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', $newPermission)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => $newPermission,
                'guard_name' => 'web',
                'berechtigungskategorie_id' => $categoryId,
                'beschreibung' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permissions')
                ->where('id', $permissionId)
                ->update([
                    'berechtigungskategorie_id' => $categoryId,
                    'beschreibung' => $description,
                    'updated_at' => now(),
                ]);
        }

        $legacyPermissionId = DB::table('permissions')
            ->where('name', $legacyPermission)
            ->where('guard_name', 'web')
            ->value('id');

        if ($legacyPermissionId) {
            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $legacyPermissionId)
                ->distinct()
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }

            $this->command?->info(sprintf('PA-Berichte-Permission wurde Rollen mit %s zugewiesen.', $legacyPermission));
        } else {
            $this->command?->warn(sprintf('Legacy-Permission %s nicht vorhanden; es wurde nur %s erstellt/aktualisiert.', $legacyPermission, $newPermission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function findCategoryForLegacyPermission(string $legacyPermission): ?int
    {
        return DB::table('permissions')
            ->where('name', $legacyPermission)
            ->where('guard_name', 'web')
            ->value('berechtigungskategorie_id');
    }
}

