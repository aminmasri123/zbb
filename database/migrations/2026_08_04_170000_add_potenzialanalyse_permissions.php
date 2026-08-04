<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const CATEGORY = 'Potenzialanalyse';

    private const DEFINITIONS = [
        'potenzialanalyse.index' => 'Erlaubt das Einsehen von Potenzialanalyse-Daten in berechtigten Gruppen, einschliesslich Uebungsergebnissen, Kompetenzbewertungen und Berichtsentwuerfen.',
        'potenzialanalyse.update' => 'Erlaubt das Bearbeiten und Speichern von Potenzialanalyse-Daten fuer Teilnehmer in berechtigten Gruppen, einschliesslich Berichtstexten und Status.',
        'potenzialanalyse.manage' => 'Erlaubt die Konfiguration der Potenzialanalyse im Projekt, insbesondere Aktivierung, PA-Tage, Uebungen und Kriterien.',
    ];

    public function up(): void
    {
        $categoryId = $this->ensureCategory();

        foreach (self::DEFINITIONS as $name => $description) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'berechtigungskategorie_id' => $categoryId,
                    'beschreibung' => $description,
                    'updated_at' => now(),
                ]
            );
        }

        $this->assignToSystemRoles($categoryId);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::DEFINITIONS))
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function ensureCategory(): int
    {
        $categoryId = DB::table('berechtigungskategories')->where('name', self::CATEGORY)->value('id');

        if ($categoryId) {
            DB::table('berechtigungskategories')
                ->where('id', $categoryId)
                ->update([
                    'beschreibung' => 'Potenzialanalyse, PA-Uebungen, Bewertungen und PA-Berichte.',
                ]);

            return (int) $categoryId;
        }

        return (int) DB::table('berechtigungskategories')->insertGetId([
            'name' => self::CATEGORY,
            'beschreibung' => 'Potenzialanalyse, PA-Uebungen, Bewertungen und PA-Berichte.',
        ]);
    }

    private function assignToSystemRoles(int $categoryId): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::DEFINITIONS))
            ->where('guard_name', 'web')
            ->pluck('id');

        $roleIds = DB::table('roles')
            ->whereIn('name', ['Administrator', 'Developer'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_berechtigungskategories')->insertOrIgnore([
                'role_id' => $roleId,
                'berechtigungskategorie_id' => $categoryId,
            ]);

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }
};
