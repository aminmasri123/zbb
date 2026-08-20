<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class AccessManagementSeeder extends Seeder
{
    private const CATEGORY = 'Zutrittsverwaltung';

    private const PERMISSIONS = [
        'zutritt.index' => [
            'Zutrittsverwaltung ansehen',
            'Erlaubt das Einsehen der Zutrittsantraege und ihrer Bearbeitungsstaende.',
        ],
        'zutritt.antrag.store' => [
            'Zutrittsantraege stellen',
            'Erlaubt das Stellen eines neuen Zutrittsantrags fuer eine berechtigte Person.',
        ],
        'zutritt.antrag.approve' => [
            'Zutrittsantraege genehmigen',
            'Erlaubt das Genehmigen oder Ablehnen von Zutrittsantraegen. Eine Selbstgenehmigung bleibt ausgeschlossen.',
        ],
        'zutritt.aktivierung.update' => [
            'Zutritte technisch bearbeiten',
            'Erlaubt die dokumentierte manuelle Aktivierung und den Entzug genehmigter Zutrittsrechte.',
        ],
        'zutritt.stammdaten.manage' => [
            'Zutrittsstammdaten verwalten',
            'Erlaubt das Verwalten von Tueren und Zutrittsprofilen.',
        ],
    ];

    public function run(): void
    {
        $this->ensureRequiredTablesExist();

        DB::transaction(function (): void {
            $now = now();

            DB::table('modules')->updateOrInsert(
                ['key' => 'access_management'],
                [
                    'name' => 'Zutrittsverwaltung',
                    'description' => 'Tueren, Zutrittsprofile, Antraege, Genehmigungen und manuelle technische Aktivierung.',
                    'category' => 'resources',
                    'is_system_module' => false,
                    'is_enforced' => true,
                    'supports_location_scope' => false,
                    'visible_in_settings' => true,
                    'default_enabled' => false,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $categoryId = $this->ensurePermissionCategory();

            foreach (self::PERMISSIONS as $name => [$displayName, $description]) {
                $values = [
                    'berechtigungskategorie_id' => $categoryId,
                    'beschreibung' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (Schema::hasColumn('permissions', 'display_name')) {
                    $values['display_name'] = $displayName;
                }

                DB::table('permissions')->updateOrInsert(
                    ['name' => $name, 'guard_name' => 'web'],
                    $values
                );
            }

            $this->assignPermissionsToAdministrator($categoryId);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Zutrittsmodul und Administrator-Berechtigungen wurden idempotent eingerichtet. Das Modul bleibt inaktiv.');
    }

    private function ensureRequiredTablesExist(): void
    {
        $requiredTables = [
            'modules',
            'module_assignments',
            'berechtigungskategories',
            'permissions',
            'roles',
            'role_has_permissions',
            'role_berechtigungskategories',
            'access_doors',
            'access_profiles',
            'access_requests',
            'access_request_events',
        ];

        $missingTables = array_values(array_filter(
            $requiredTables,
            fn (string $table) => ! Schema::hasTable($table)
        ));

        if ($missingTables !== []) {
            throw new RuntimeException(
                'Zuerst die Zutrittsmigration ausfuehren. Fehlende Tabellen: '.implode(', ', $missingTables)
            );
        }
    }

    private function ensurePermissionCategory(): int
    {
        $categoryId = DB::table('berechtigungskategories')
            ->where('name', self::CATEGORY)
            ->value('id');

        if ($categoryId) {
            DB::table('berechtigungskategories')
                ->where('id', $categoryId)
                ->update([
                    'beschreibung' => 'Zutrittsantraege, Genehmigungen, technische Aktivierung sowie Tuer- und Profilstammdaten.',
                ]);

            return (int) $categoryId;
        }

        return (int) DB::table('berechtigungskategories')->insertGetId([
            'name' => self::CATEGORY,
            'beschreibung' => 'Zutrittsantraege, Genehmigungen, technische Aktivierung sowie Tuer- und Profilstammdaten.',
        ]);
    }

    private function assignPermissionsToAdministrator(int $categoryId): void
    {
        $administratorRoleId = DB::table('roles')
            ->where('name', 'Administrator')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $administratorRoleId) {
            throw new RuntimeException('Die Rolle Administrator wurde nicht gefunden.');
        }

        DB::table('role_berechtigungskategories')->insertOrIgnore([
            'role_id' => $administratorRoleId,
            'berechtigungskategorie_id' => $categoryId,
        ]);

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $administratorRoleId,
            ]);
        }
    }
}
