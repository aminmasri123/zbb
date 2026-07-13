<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const DEFINITIONS = [
        'bereichsauswahl.index' => ['Bereichauswahl', 'Erlaubt das Einsehen der Bereichswahlen, Zugangscodes und des Bearbeitungsstands der Teilnehmer fuer einen Partner, ein Schuljahr und einen Teilabschnitt.'],
        'bereichsauswahl.store' => ['Bereichauswahl', 'Erlaubt das erstmalige Erfassen einer Bereichsauswahl fuer einen Teilnehmer im erlaubten Datenbereich. Bereits vorhandene Wahlen duerfen damit nicht geaendert werden.'],
        'bereichsauswahl.update' => ['Bereichauswahl', 'Erlaubt das Bearbeiten und Korrigieren der einzelnen Bereichswahlen eines Teilnehmers. Die zentrale Zugangssteuerung und die Anzahl der Wahlfelder sind nicht enthalten.'],
        'bereichsauswahl.destroy' => ['Bereichauswahl', 'Erlaubt das Zuruecksetzen oder endgueltige Loeschen einer bestehenden Bereichsauswahl eines Teilnehmers. Die Stammdaten des Teilnehmers bleiben davon unberuehrt.'],
        'bereichsauswahl.planning' => ['Bereichauswahl', 'Erlaubt die zentrale Planung der Bereichsauswahl: den Teilnehmerzugang aktivieren oder deaktivieren und die Anzahl der sichtbaren Wahlfelder festlegen. Einzelne Teilnehmerwahlen werden weiterhin ueber bereichsauswahl.store oder bereichsauswahl.update gesteuert.'],
        'einteilung.index' => ['Einteilung', 'Erlaubt das Einsehen vorhandener Einteilungen, Runden, Kapazitaeten und Zuordnungen fuer einen Partner, ein Schuljahr und einen Teilabschnitt.'],
        'einteilung.store' => ['Einteilung', 'Erlaubt das manuelle Anlegen einer Einteilung sowie das automatische Berechnen und Speichern einer neuen Einteilung anhand vorhandener Bereichswahlen.'],
        'einteilung.update' => ['Einteilung', 'Erlaubt das Bearbeiten bestehender Einteilungen und das manuelle Verschieben oder Neuzuordnen einzelner Teilnehmer zwischen Bereichen.'],
        'einteilung.destroy' => ['Einteilung', 'Erlaubt das Zuruecksetzen oder Loeschen der Einteilungen eines Partner-, Schuljahr- und Teilabschnittskontexts. Bereits erzeugte Teilnehmerzuordnungen werden dabei synchron bereinigt.'],
        'einteilung.export' => ['Einteilung', 'Erlaubt den Excel-Export einer Einteilung einschliesslich Runden, Bereiche und Teilnehmerzuordnungen. Das Recht erlaubt keine Aenderung der Einteilungsdaten.'],
        'einteilung.planning' => ['Einteilung', 'Erlaubt die administrative Einteilungsplanung: Rundenzahl und Kapazitaetsparameter festlegen, komplette Runden tauschen und aus einer fertigen Einteilung automatisch echte Gruppen mit Zeit-, Raum- und Betreuerzuordnung generieren.'],
    ];

    private const LEGACY_MAP = [
        'bereichsauswahl.index' => ['bereichsauswahl.index'],
        'bereichsauswahl.store' => [],
        'bereichsauswahl.update' => ['bereichsauswahl.bop.radio.update'],
        'bereichsauswahl.destroy' => [],
        'bereichsauswahl.planning' => ['bereichsauswahl.setting.update'],
        'einteilung.index' => ['einteilung.show'],
        'einteilung.store' => ['einteilung.create', 'einteilung.store'],
        'einteilung.update' => ['einteilung.update'],
        'einteilung.destroy' => ['einteilung.destroy'],
        'einteilung.export' => ['einteilung.export.excel'],
        'einteilung.planning' => [
            'einteilung.parameter.update',
            'einteilung.runden.switch',
            'gruppen.generieren',
        ],
    ];

    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->text('beschreibung')->nullable()->change();
        });

        foreach (self::DEFINITIONS as $name => [$categoryName, $description]) {
            $categoryId = DB::table('berechtigungskategories')->where('name', $categoryName)->value('id');
            if (! $categoryId) {
                continue;
            }

            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['berechtigungskategorie_id' => $categoryId, 'beschreibung' => $description]
            );
        }

        foreach (self::LEGACY_MAP as $target => $sources) {
            $this->copyAssignments($sources, $target);
        }

        $administratorRoleId = DB::table('roles')
            ->where('name', 'Administrator')
            ->where('guard_name', 'web')
            ->value('id');

        if ($administratorRoleId) {
            foreach (DB::table('permissions')->whereIn('name', array_keys(self::DEFINITIONS))->pluck('id') as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $administratorRoleId,
                ]);
            }
        }

        $legacyNames = collect(self::LEGACY_MAP)
            ->flatten()
            ->unique()
            ->reject(fn (string $name) => array_key_exists($name, self::DEFINITIONS));

        DB::table('permissions')->whereIn('name', $legacyNames)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::LEGACY_MAP as $target => $legacyNames) {
            foreach ($legacyNames as $legacyName) {
                $categoryName = str_starts_with($legacyName, 'bereichsauswahl.') ? 'Bereichauswahl' : 'Einteilung';
                $categoryId = DB::table('berechtigungskategories')->where('name', $categoryName)->value('id');
                if (! $categoryId) {
                    continue;
                }

                DB::table('permissions')->updateOrInsert(
                    ['name' => $legacyName, 'guard_name' => 'web'],
                    ['berechtigungskategorie_id' => $categoryId, 'beschreibung' => 'Historische BOP-Berechtigung.']
                );
                $this->copyAssignments([$target], $legacyName);
            }
        }

        DB::table('permissions')
            ->whereIn('name', array_keys(self::DEFINITIONS))
            ->whereNotIn('name', ['bereichsauswahl.index', 'einteilung.store', 'einteilung.update', 'einteilung.destroy'])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function copyAssignments(array $sourceNames, string $targetName): void
    {
        if ($sourceNames === []) {
            return;
        }

        $targetId = DB::table('permissions')->where('name', $targetName)->where('guard_name', 'web')->value('id');
        $sourceIds = DB::table('permissions')->whereIn('name', $sourceNames)->where('guard_name', 'web')->pluck('id');
        if (! $targetId || $sourceIds->isEmpty()) {
            return;
        }

        foreach (DB::table('role_has_permissions')->whereIn('permission_id', $sourceIds)->pluck('role_id')->unique() as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $targetId, 'role_id' => $roleId]);
        }

        foreach (DB::table('model_has_permissions')->whereIn('permission_id', $sourceIds)->get() as $assignment) {
            DB::table('model_has_permissions')->insertOrIgnore([
                'permission_id' => $targetId,
                'model_type' => $assignment->model_type,
                'model_id' => $assignment->model_id,
            ]);
        }
    }
};
