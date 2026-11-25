<?php

use App\Services\Bop\RolltagAttendanceTemplateResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            [
                'name' => 'Rolltag Anwesenheitsliste Klassen',
                'path' => '/vorlage/projekte/bop/excel/bo/botag1/Anwesenheitsliste-BO-TAG1-Klasse.xlsx',
                'permission' => RolltagAttendanceTemplateResolver::CLASS_EXPORT_PERMISSION,
                'description' => 'Excel-Vorlage für einzelne Klassen und den Klassenexport im Rolltag-Paket.',
            ],
            [
                'name' => 'Rolltag Anwesenheitsliste Gruppen und Räume',
                'path' => '/vorlage/projekte/bop/excel/bo/botag1/Anwesenheitsliste-BO-TAG1.xlsx',
                'permission' => RolltagAttendanceTemplateResolver::GROUP_EXPORT_PERMISSION,
                'description' => 'Excel-Vorlage für geplante Gruppen und manuell konfigurierte Räume am Rolltag.',
            ],
        ];

        foreach ($templates as $index => $template) {
            $values = [
                'name' => $template['name'],
                'typ' => 'excel',
                'kontext' => 'partner',
                'einsatzbereich' => 'partner',
                'dateipfadName' => basename($template['path']),
                'ausgabeformate' => json_encode(['xlsx']),
                'version' => 'Rolltag',
                'export_permission' => $template['permission'],
                'beschreibung' => $template['description'],
                'updated_at' => now(),
            ];

            $documentId = DB::table('dokumentes')->where('dateipfad', $template['path'])->value('id');
            if ($documentId) {
                DB::table('dokumentes')->where('id', $documentId)->update($values);
            } else {
                $documentId = DB::table('dokumentes')->insertGetId($values + [
                    'dateipfad' => $template['path'],
                    'aktiv' => true,
                    'created_at' => now(),
                ]);
            }

            foreach (DB::table('projekts')->whereRaw('UPPER(name) LIKE ?', ['%BOP%'])->pluck('id') as $projectId) {
                DB::table('projekt_has_dokumentes')->updateOrInsert(
                    ['projekt_id' => $projectId, 'dokument_id' => $documentId],
                    ['gruppen_export' => 1, 'serienbrief' => 0, 'sort_order' => $index]
                );
            }
        }

        $duplicateIds = DB::table('dokumentes')
            ->where('dateipfad', '/vorlage/projekte/bop/excel/Anwesenheitsliste-BO-TAG1.xlsx')
            ->pluck('id');
        if ($duplicateIds->isNotEmpty()) {
            DB::table('projekt_has_dokumentes')->whereIn('dokument_id', $duplicateIds)->delete();
            DB::table('dokument_has_kategories')->whereIn('dokument_id', $duplicateIds)->delete();
            DB::table('dokument_has_bereiches')->whereIn('dokument_id', $duplicateIds)->delete();
            DB::table('dokument_paket_has_dokumentes')->whereIn('dokument_id', $duplicateIds)->delete();
            DB::table('dokumentes')->whereIn('id', $duplicateIds)->delete();
        }
    }

    public function down(): void
    {
        // Die verwalteten Vorlagen und spätere Benutzeränderungen bleiben erhalten.
    }
};
