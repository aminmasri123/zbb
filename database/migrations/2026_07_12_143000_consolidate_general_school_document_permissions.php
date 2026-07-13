<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const DEFINITIONS = [
        'dokumente.schule.export' => [
            'Auswertung',
            'Erlaubt den Export schulweiter Dokumente fuer alle zur ausgewaehlten Schule, zum Schuljahr und zum Teilabschnitt gehoerenden Teilnehmer. Enthalten sind Hausordnung, beide Varianten des PA-Auswertungsbogens, POBO-Zertifikate als Word und PDF sowie die POBO-Auswertung insgesamt oder nach Runde. Das Recht gilt projektartenuebergreifend und erlaubt nur das Erzeugen oder Herunterladen dieser Dokumente; eine dauerhafte Ablage auf dem Server und der Export der Teilnehmerliste sind nicht enthalten. Der Datenumfang bleibt durch das aktive Projekt und den rollenbezogenen Datenzugriff begrenzt.',
        ],
        'teilnehmer.liste.export' => [
            'Teilnehmer',
            'Erlaubt den Export einer schulweiten Teilnehmerliste mit personenbezogenen Stammdaten wie Vorname, Nachname, Geschlecht, Geburtsdatum und Klasse. Das Recht gilt allgemein und ist nicht an einen Projektnamen gebunden; exportiert werden dennoch ausschliesslich Teilnehmer der ausgewaehlten Schule innerhalb des aktiven Projekts, Schuljahrs und Teilabschnitts. Andere Teilnehmerdokumente, Auswertungen oder dauerhafte Dateiablagen sind nicht enthalten.',
        ],
        'dokumente.ansprechpartner.manage' => [
            'Dateimanager',
            'Erlaubt das Erzeugen und dauerhafte Ablegen der fuer schulische Ansprechpartner bestimmten Unterlagen. Enthalten sind die Liste fehlender Elterneinverstaendniserklaerungen, das Anlegen der vorgesehenen Ordnerstruktur sowie das Generieren von BO-Auswertungen und PA-Berichten in diese Ordner. Das Recht erlaubt damit Schreibzugriffe auf die serverseitige Dokumentablage, jedoch keine anderen Datei- oder Teilnehmeraenderungen. Verarbeitet werden nur Daten der ausgewaehlten Schule im aktiven Projekt, Schuljahr und Teilabschnitt.',
        ],
    ];

    private const LEGACY_MAP = [
        'dokumente.schule.export' => [
            'hausordnung.export.schule.pdf',
            'export.auswertungsbogenPA.schule.pdf',
            'export.auswertungsbogenPA.roland.schule.pdf',
            'export.zertifikat.schule.pobo',
            'export.zertifikat.schule.pobo.pdf',
            'export.auswertungBO.schule.pdf',
            'auswertungPoboModal',
        ],
        'teilnehmer.liste.export' => [
            'export.teilnehmerliste.schule.excel',
        ],
        'dokumente.ansprechpartner.manage' => [
            'export.elterneinverstaendniserklaerung.schule',
            'export.auswertungBO.schule.pdf.tofolder',
            'export.auswertungPA.schule.pdf.tofolder',
            'alleTeilnehmer.folder.create',
        ],
    ];

    private const LEGACY_CATEGORIES = [
        'hausordnung.export.schule.pdf' => 'Auswertung',
        'export.auswertungsbogenPA.schule.pdf' => 'Auswertung',
        'export.auswertungsbogenPA.roland.schule.pdf' => 'Auswertung',
        'export.zertifikat.schule.pobo' => 'Auswertung',
        'export.zertifikat.schule.pobo.pdf' => 'Auswertung',
        'export.auswertungBO.schule.pdf' => 'Auswertung',
        'auswertungPoboModal' => 'Auswertung',
        'export.teilnehmerliste.schule.excel' => 'Teilnehmer',
        'export.elterneinverstaendniserklaerung.schule' => 'Auswertung',
        'export.auswertungBO.schule.pdf.tofolder' => 'Auswertung',
        'export.auswertungPA.schule.pdf.tofolder' => 'Auswertung',
        'alleTeilnehmer.folder.create' => 'Dateimanager',
    ];

    private const CATEGORY_DESCRIPTIONS = [
        'Auswertung' => 'Auswertungen, Berichte und fachliche Dokumentexporte.',
        'Teilnehmer' => 'Teilnehmerdaten, Teilnehmerlisten und teilnehmerbezogene Funktionen.',
        'Dateimanager' => 'Dateien, Ordner, dauerhafte Ablagen und Dokumentverwaltung.',
    ];

    public function up(): void
    {
        foreach (self::DEFINITIONS as $name => [$categoryName, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'berechtigungskategorie_id' => $this->categoryId($categoryName),
                    'beschreibung' => $description,
                ]
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

        DB::table('permissions')
            ->whereIn('name', collect(self::LEGACY_MAP)->flatten()->all())
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::LEGACY_MAP as $target => $legacyNames) {
            foreach ($legacyNames as $legacyName) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $legacyName, 'guard_name' => 'web'],
                    [
                        'berechtigungskategorie_id' => $this->categoryId(self::LEGACY_CATEGORIES[$legacyName]),
                        'beschreibung' => 'Historische technische Berechtigung fuer einen einzelnen Dokumentexport.',
                    ]
                );
                $this->copyAssignments([$target], $legacyName);
            }
        }

        DB::table('permissions')->whereIn('name', array_keys(self::DEFINITIONS))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function categoryId(string $name): int
    {
        $categoryId = DB::table('berechtigungskategories')->where('name', $name)->value('id');

        if (! $categoryId) {
            $categoryId = DB::table('berechtigungskategories')->insertGetId([
                'name' => $name,
                'beschreibung' => self::CATEGORY_DESCRIPTIONS[$name] ?? '',
            ]);
        }

        return (int) $categoryId;
    }

    private function copyAssignments(array $sourceNames, string $targetName): void
    {
        $targetId = DB::table('permissions')
            ->where('name', $targetName)
            ->where('guard_name', 'web')
            ->value('id');
        $sourceIds = DB::table('permissions')
            ->whereIn('name', $sourceNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        if (! $targetId || $sourceIds->isEmpty()) {
            return;
        }

        foreach (DB::table('role_has_permissions')->whereIn('permission_id', $sourceIds)->pluck('role_id')->unique() as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $targetId,
                'role_id' => $roleId,
            ]);
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
