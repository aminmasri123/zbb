<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const DEFINITIONS = [
        'anwesenheit.index' => 'Erlaubt das Einsehen von Anwesenheitseintraegen und Auswertungen im erlaubten Projekt- und Teilnehmerbereich.',
        'anwesenheit.manage' => 'Erlaubt das Erfassen und Bearbeiten von Anwesenheitseintraegen im erlaubten Projekt- und Teilnehmerbereich.',
        'anwesenheit.destroy' => 'Erlaubt das endgueltige Loeschen von Anwesenheitseintraegen im erlaubten Projekt- und Teilnehmerbereich.',
        'anwesenheit.export' => 'Erlaubt normale, nicht abrechnungsbezogene Anwesenheitslisten und Auswertungen zu exportieren.',
        'anwesenheit.archiv' => 'Erlaubt Archivordner und signierte PDF-Anwesenheitslisten zu verwalten.',
        'anwesenheit.abrechnung' => 'Erlaubt abrechnungsbezogene BOP-Anwesenheitslisten, Anwesenheitsdaten und Entwuerfe zu bearbeiten und zu exportieren.',
    ];

    private const LEGACY_MAP = [
        'anwesenheit.index' => [
            'anwesenheit.store',
            'anwesenheit.update',
            'anwesenheit.destroy',
        ],
        'anwesenheit.manage' => [
            'anwesenheit.store',
            'anwesenheit.update',
        ],
        'anwesenheit.destroy' => [
            'anwesenheit.destroy',
            'gruppeHasPersonen.destroy',
        ],
        'anwesenheit.export' => [
            'gruppe.bop.export.anwesenheitsliste',
            'export.anwesenheitslite_V1',
            'export.projekt.anwesenheit.periode',
        ],
        'anwesenheit.archiv' => [
            'anwesenheitsliste.POBO.bibb.archive.folder',
            'anwesenheitsliste.POBO.bibb.pdf.store',
            'anwesenheitsliste.PA.digital.archive.folder',
            'anwesenheitsliste.PA.digital.pdf.store',
        ],
        'anwesenheit.abrechnung' => [
            'index-anpassung-anwesenheitsdaten',
            'export.anwesenheitsdaten.schule.excel',
            'anwesenheitslisteVorBOTage',
            'export.anwesenheitsliste.rechnung',
            'anwesenheitsliste.POBO.bibb.preview',
            'anwesenheitsliste.POBO.bibb.draft.show',
            'anwesenheitsliste.POBO.bibb.draft.store',
            'anwesenheitsliste.POBO.bibb.draft.destroy',
            'anwesenheitsliste.POBO.bibb.export.word',
            'anwesenheitsliste.PA.digital.preview',
            'anwesenheitsliste.PA.digital.draft.show',
            'anwesenheitsliste.PA.digital.draft.store',
            'anwesenheitsliste.PA.digital.draft.destroy',
            'anwesenheitsliste.PA.export.word',
            'anwesenheitsliste.BoTag1.export',
        ],
    ];

    public function up(): void
    {
        $categoryId = $this->categoryId('Anwesenheitsliste', 'Anwesenheiten, Exporte, Archivierung und BOP-Abrechnung.');

        foreach (self::DEFINITIONS as $name => $description) {
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
            $targetIds = DB::table('permissions')->whereIn('name', array_keys(self::DEFINITIONS))->pluck('id');
            foreach ($targetIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $administratorRoleId,
                ]);
            }
        }

        $this->moveNonAttendancePermissions();

        $legacyNames = collect(self::LEGACY_MAP)
            ->flatten()
            ->unique()
            ->reject(fn (string $name) => array_key_exists($name, self::DEFINITIONS))
            ->values();

        DB::table('permissions')->whereIn('name', $legacyNames)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $attendanceCategoryId = $this->categoryId('Anwesenheitsliste', 'Anwesenheiten, digitale Listen und Anwesenheitsexporte.');

        foreach (self::LEGACY_MAP as $target => $legacyNames) {
            foreach ($legacyNames as $legacyName) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $legacyName, 'guard_name' => 'web'],
                    [
                        'berechtigungskategorie_id' => $attendanceCategoryId,
                        'beschreibung' => 'Historische Anwesenheitsberechtigung.',
                    ]
                );
                $this->copyAssignments([$target], $legacyName);
            }
        }

        DB::table('permissions')
            ->whereIn('name', array_keys(self::DEFINITIONS))
            ->where('name', '!=', 'anwesenheit.destroy')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function copyAssignments(array $sourceNames, string $targetName): void
    {
        $targetId = DB::table('permissions')->where('name', $targetName)->where('guard_name', 'web')->value('id');
        $sourceIds = DB::table('permissions')->whereIn('name', $sourceNames)->where('guard_name', 'web')->pluck('id');

        if (! $targetId || $sourceIds->isEmpty()) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')->whereIn('permission_id', $sourceIds)->pluck('role_id')->unique();
        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $targetId, 'role_id' => $roleId]);
        }

        $directAssignments = DB::table('model_has_permissions')->whereIn('permission_id', $sourceIds)->get();
        foreach ($directAssignments as $assignment) {
            DB::table('model_has_permissions')->insertOrIgnore([
                'permission_id' => $targetId,
                'model_type' => $assignment->model_type,
                'model_id' => $assignment->model_id,
            ]);
        }
    }

    private function moveNonAttendancePermissions(): void
    {
        $participantCategoryId = $this->categoryId('Teilnehmer', 'Teilnehmerdaten, Teilnehmerprofile und personenbezogene Teilnehmerfunktionen.');
        $fileCategoryId = $this->categoryId('Dateimanager', 'Dateien, Ordner, Downloads, Uploads und Freigaben.');
        $reportCategoryId = $this->categoryId('Auswertung', 'Auswertungen, Berichte und finanzbezogene Einstiege.');

        DB::table('permissions')->whereIn('name', [
            'export.teilnehmerliste.schule.excel',
            'teilnehmer.liste.schule',
        ])->update(['berechtigungskategorie_id' => $participantCategoryId]);

        DB::table('permissions')->where('name', 'alleTeilnehmer.folder.create')
            ->update(['berechtigungskategorie_id' => $fileCategoryId]);

        DB::table('permissions')->where('name', 'hausordnung.export.schule.pdf')
            ->update(['berechtigungskategorie_id' => $reportCategoryId]);
    }

    private function categoryId(string $name, string $description): int
    {
        DB::table('berechtigungskategories')->updateOrInsert(
            ['name' => $name],
            ['beschreibung' => $description]
        );

        return (int) DB::table('berechtigungskategories')->where('name', $name)->value('id');
    }
};
