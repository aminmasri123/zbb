<?php

use App\Services\Bop\PaPreparationAttendanceTemplateExportService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $path = '/'.PaPreparationAttendanceTemplateExportService::TEMPLATE_PATH;
        $values = [
            'name' => 'Anwesenheitsliste Vorbereitung PA',
            'typ' => 'excel',
            'kontext' => 'partner',
            'einsatzbereich' => 'partner',
            'dateipfadName' => 'Anwesenheitsliste-Vorbereitung-BO-Tage.xlsx',
            'ausgabeformate' => json_encode(['xlsx', 'pdf']),
            'export_permission' => PaPreparationAttendanceTemplateExportService::EXPORT_PERMISSION,
            'beschreibung' => 'Excel-Vorlage für die PA-Vorbereitung auf Partner- und Schulsebene. Schule, Klassen, Termin, Teilnehmende und Unterschriften werden beim Export eingesetzt.',
            'updated_at' => now(),
        ];

        $id = DB::table('dokumentes')->where('dateipfad', $path)->value('id');
        if ($id) {
            DB::table('dokumentes')->where('id', $id)->update($values);
        } else {
            $id = DB::table('dokumentes')->insertGetId($values + [
                'dateipfad' => $path,
                'aktiv' => true,
                'created_at' => now(),
            ]);
        }

        foreach (DB::table('projekts')->whereRaw('UPPER(name) LIKE ?', ['%BOP%'])->pluck('id') as $projectId) {
            DB::table('projekt_has_dokumentes')->updateOrInsert(
                ['projekt_id' => $projectId, 'dokument_id' => $id],
                ['gruppen_export' => 1, 'serienbrief' => 0, 'sort_order' => 0]
            );
        }
    }

    public function down(): void
    {
        // Keep the managed template and any user changes.
    }
};
