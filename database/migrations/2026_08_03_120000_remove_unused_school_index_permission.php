<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('name', 'schule.index')
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $categoryId = DB::table('permissions')
            ->where('name', 'kooperationspartner.index')
            ->where('guard_name', 'web')
            ->value('berechtigungskategorie_id');

        DB::table('permissions')->updateOrInsert(
            ['name' => 'schule.index', 'guard_name' => 'web'],
            [
                'berechtigungskategorie_id' => $categoryId,
                'beschreibung' => 'Erlaubt den Zugriff auf die Schuluebersicht bzw. schulbezogene Organisationsdaten.',
            ]
        );

        $permissionId = DB::table('permissions')
            ->where('name', 'schule.index')
            ->where('guard_name', 'web')
            ->value('id');
        $administratorRoleId = DB::table('roles')
            ->where('name', 'Administrator')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId && $administratorRoleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $administratorRoleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
