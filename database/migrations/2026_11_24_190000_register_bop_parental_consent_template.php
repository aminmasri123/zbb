<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $path = '/vorlage/projekte/bop/word/Elterneinverstaendniserklaerung_BOP.docx';
        $name = 'Elterneinverständniserklärung BOP';
        $id = DB::table('dokumentes')->where('dateipfad', $path)->value('id');
        if (!$id) {
            $id = DB::table('dokumentes')->insertGetId([
                'name' => $name, 'typ' => 'word', 'kontext' => 'partner', 'einsatzbereich' => 'partner',
                'dateipfad' => $path, 'dateipfadName' => 'Elterneinverständniserklärung BOP.docx',
                'ausgabeformate' => json_encode(['docx']), 'gruppen_export_modus' => 'eine_datei',
                'export_permission' => 'dokumente.schule.export', 'aktiv' => true,
                'beschreibung' => 'Elterneinverständniserklärung als gemeinsamer Serienbrief. Platzhalter: ${voller_name}, ${schule}, ${klasse}.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (DB::table('projekts')->whereRaw('UPPER(name) LIKE ?', ['%BOP%'])->get() as $project) {
            DB::table('projekt_has_dokumentes')->updateOrInsert(['projekt_id' => $project->id, 'dokument_id' => $id], []);
            $settings = json_decode($project->rule_settings ?? '{}', true) ?: [];
            $package = $settings['bop_partner_packages']['vorbereitung-pa'] ?? [
                'name' => 'Vorbereitung für die PA',
                'documents' => ['Elterneinverständniserklärung', 'Anwesenheitsliste Vorbereitung PA'],
            ];
            $package['documents'] = array_values(array_unique([...$package['documents'], $name]));
            $settings['bop_partner_packages']['vorbereitung-pa'] = $package;
            DB::table('projekts')->where('id', $project->id)->update(['rule_settings' => json_encode($settings)]);
        }
    }

    public function down(): void
    {
        // Preserve the template and any subsequent user edits.
    }
};
