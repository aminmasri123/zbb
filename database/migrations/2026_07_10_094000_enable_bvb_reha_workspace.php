<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'bvb_reha.workspace.index' => 'Erlaubt den lesenden Zugriff auf den BvB-Reha-Arbeitsbereich und explizit typisierte BvB-Reha-Projekte.',
        'bvb_reha.participants.index' => 'Erlaubt das Einsehen der vorhandenen Projektteilnahmen in explizit typisierten BvB-Reha-Projekten.',
    ];

    public function up(): void
    {
        DB::table('modules')
            ->where('key', 'bvb_reha')
            ->update([
                'is_enforced' => true,
                'supports_location_scope' => false,
                'default_enabled' => false,
                'updated_at' => now(),
            ]);

        $categoryId = $this->projectCategoryId();

        foreach (self::PERMISSIONS as $name => $description) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'berechtigungskategorie_id' => $categoryId,
                    'beschreibung' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::PERMISSIONS))
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

        $this->clearPermissionCache();
    }

    public function down(): void
    {
        DB::table('modules')
            ->where('key', 'bvb_reha')
            ->update([
                'is_enforced' => false,
                'default_enabled' => true,
                'updated_at' => now(),
            ]);

        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        $this->clearPermissionCache();
    }

    private function projectCategoryId(): int
    {
        $categoryId = DB::table('berechtigungskategories')->where('name', 'Projekt')->value('id');

        if (!$categoryId) {
            $categoryId = DB::table('berechtigungskategories')->insertGetId([
                'name' => 'Projekt',
                'beschreibung' => 'Projekte, Projekttypen und projektbezogene Fachmodule.',
            ]);
        }

        return (int) $categoryId;
    }

    private function clearPermissionCache(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
