<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $categoryId = DB::table('berechtigungskategories')->where('name', 'Teilnehmer')->value('id');
        if (! $categoryId) {
            return;
        }

        DB::table('permissions')->updateOrInsert(
            ['name' => 'teilnehmer.data-request.manage', 'guard_name' => 'web'],
            [
                'berechtigungskategorie_id' => $categoryId,
                'beschreibung' => 'Erlaubt das Pruefen, Bearbeiten und Abschliessen von Datenauskunftsanfragen eines Teilnehmers innerhalb des rollenbezogen erlaubten Projekt- und Teilnehmerbereichs.',
            ]
        );

        $administratorRoleId = DB::table('roles')
            ->where('name', 'Administrator')
            ->where('guard_name', 'web')
            ->value('id');
        $permissionId = DB::table('permissions')
            ->where('name', 'teilnehmer.data-request.manage')
            ->where('guard_name', 'web')
            ->value('id');

        if ($administratorRoleId && $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $administratorRoleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'teilnehmer.data-request.manage')
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
