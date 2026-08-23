<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'ai.report.use';

    public function up(): void
    {
        $categoryId = DB::table('berechtigungskategories')
            ->where('name', 'KI-Agent')
            ->value('id');

        if (! $categoryId) {
            $categoryId = DB::table('berechtigungskategories')->insertGetId([
                'name' => 'KI-Agent',
                'beschreibung' => 'Berechtigungen fuer intern autorisierte KI-Berichtsentwuerfe.',
            ]);
        }

        $values = [
            'berechtigungskategorie_id' => $categoryId,
            'beschreibung' => 'Erlaubt das Erzeugen eines projektgebundenen KI-Berichtsentwurfs. Eine menschliche Freigabe bleibt erforderlich.',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (DB::getSchemaBuilder()->hasColumn('permissions', 'display_name')) {
            $values['display_name'] = 'KI-Berichtsentwurf erstellen';
        }

        DB::table('permissions')->updateOrInsert(
            ['name' => self::PERMISSION, 'guard_name' => 'web'],
            $values,
        );

        $permissionId = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        $roleIds = DB::table('roles')
            ->whereIn('name', ['Administrator', 'Developer'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        $categoryId = DB::table('berechtigungskategories')
            ->where('name', 'KI-Agent')
            ->value('id');

        if ($categoryId && ! DB::table('permissions')->where('berechtigungskategorie_id', $categoryId)->exists()) {
            DB::table('role_berechtigungskategories')->where('berechtigungskategorie_id', $categoryId)->delete();
            DB::table('berechtigungskategories')->where('id', $categoryId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
