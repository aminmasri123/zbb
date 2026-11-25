<?php

use App\Services\Bop\BibbAttendanceWordTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $path = '/vorlage/projekte/bop/word/bo/Teilnehmendenliste_zum_Nachweis_der_praktischen_Berufsorientierung_A3.docx';
        $values = [
            'name' => 'Anwesenheitsliste BO/BIBB DIN A3',
            'typ' => 'word',
            'kontext' => 'partner',
            'einsatzbereich' => 'partner',
            'dateipfadName' => basename($path),
            'ausgabeformate' => json_encode(['docx']),
            'version' => 'BIBB-A3',
            'export_permission' => BibbAttendanceWordTemplate::A3_EXPORT_PERMISSION,
            'beschreibung' => 'Zentrale DIN-A3-Word-Vorlage für die BO/BIBB-Anwesenheitsliste in BOP-Paketen und im Einzelexport.',
            'updated_at' => now(),
        ];

        $documentId = DB::table('dokumentes')->where('dateipfad', $path)->value('id');
        if ($documentId) {
            DB::table('dokumentes')->where('id', $documentId)->update($values);
        } else {
            $documentId = DB::table('dokumentes')->insertGetId($values + [
                'dateipfad' => $path,
                'aktiv' => true,
                'created_at' => now(),
            ]);
        }

        foreach (DB::table('projekts')->whereRaw('UPPER(name) LIKE ?', ['%BOP%'])->get(['id', 'rule_settings']) as $project) {
            DB::table('projekt_has_dokumentes')->updateOrInsert(
                ['projekt_id' => $project->id, 'dokument_id' => $documentId],
                ['gruppen_export' => 1, 'serienbrief' => 1, 'sort_order' => 0]
            );

            $settings = json_decode($project->rule_settings ?: '{}', true) ?: [];
            if (isset($settings['bop_partner_packages']['rolltag']['documents'])) {
                $documents = $settings['bop_partner_packages']['rolltag']['documents'];
                if (! in_array('Anwesenheitsliste BO/BIBB DIN A3', $documents, true)) {
                    $settings['bop_partner_packages']['rolltag']['documents'][] = 'Anwesenheitsliste BO/BIBB DIN A3';
                    DB::table('projekts')->where('id', $project->id)->update([
                        'rule_settings' => json_encode($settings),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Die verwaltete Vorlage und spätere Benutzeränderungen bleiben erhalten.
    }
};
