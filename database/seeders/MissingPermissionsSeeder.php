<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Spatie\Permission\PermissionRegistrar;

class MissingPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $missingPermissions = $this->missingPermissions();

        if ($missingPermissions->isEmpty()) {
            $this->command?->info('Keine fehlenden Permissions gefunden.');
            return;
        }

        foreach ($missingPermissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                [
                    'name' => $permission['name'],
                    'guard_name' => $permission['guard_name'],
                ],
                [
                    'berechtigungskategorie_id' => $permission['berechtigungskategorie_id'],
                    'beschreibung' => $permission['beschreibung'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->assignToAdministrator($missingPermissions->pluck('name')->all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info($missingPermissions->count() . ' fehlende Permissions angelegt und Administrator zugewiesen.');
    }

    private function missingPermissions()
    {
        $catalog = $this->permissionCatalog();
        $existingPermissionKeys = DB::table('permissions')
            ->get(['name', 'guard_name'])
            ->map(fn ($permission) => $permission->guard_name . '|' . $permission->name)
            ->all();

        return $catalog
            ->reject(fn (array $permission) => in_array($permission['guard_name'] . '|' . $permission['name'], $existingPermissionKeys, true))
            ->values();
    }

    private function permissionCatalog()
    {
        $userSeeder = app(UserSeeder::class);
        $method = new ReflectionMethod($userSeeder, 'permissionCatalog');
        $method->setAccessible(true);

        return collect($method->invoke($userSeeder));
    }

    private function assignToAdministrator(array $permissionNames): void
    {
        $administratorRoleId = DB::table('roles')
            ->where('name', 'Administrator')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $administratorRoleId) {
            $this->command?->warn('Administrator-Rolle wurde nicht gefunden. Permissions wurden angelegt, aber nicht zugewiesen.');
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $permissionNames)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $administratorRoleId,
            ]);
        }
    }
}
