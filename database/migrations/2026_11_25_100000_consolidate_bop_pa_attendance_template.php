<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CANONICAL_PATH = '/vorlage/projekte/bop/word/pa/Anwesenheitsliste-PA.docx';

    private const DUPLICATE_PATHS = [
        '/vorlage/projekte/bop/word/Anwesenheit_PA.docx',
        '/vorlage/projekte/bop/excel/Anwesenheitsliste-PA.xlsx',
    ];

    public function up(): void
    {
        $values = [
            'name' => 'Anwesenheitsliste PA',
            'typ' => 'word',
            'kontext' => 'gruppe',
            'einsatzbereich' => 'gruppe',
            'dateipfadName' => 'Anwesenheitsliste-PA.docx',
            'ausgabeformate' => json_encode(['docx']),
            'export_permission' => 'anwesenheit.abrechnung',
            'beschreibung' => 'Zentrale BOP-Word-Vorlage für die PA-Anwesenheitsliste auf Partner-, Schul- und Gruppenebene.',
            'updated_at' => now(),
        ];

        $documentId = DB::table('dokumentes')->where('dateipfad', self::CANONICAL_PATH)->value('id');
        if ($documentId) {
            DB::table('dokumentes')->where('id', $documentId)->update($values);
        } else {
            $documentId = DB::table('dokumentes')->insertGetId($values + [
                'dateipfad' => self::CANONICAL_PATH,
                'aktiv' => true,
                'created_at' => now(),
            ]);
        }

        foreach (DB::table('projekts')->whereRaw('UPPER(name) LIKE ?', ['%BOP%'])->pluck('id') as $projectId) {
            DB::table('projekt_has_dokumentes')->updateOrInsert(
                ['projekt_id' => $projectId, 'dokument_id' => $documentId],
                ['gruppen_export' => 1, 'serienbrief' => 1, 'sort_order' => 0]
            );
        }

        $duplicateIds = DB::table('dokumentes')->whereIn('dateipfad', self::DUPLICATE_PATHS)->pluck('id');
        if ($duplicateIds->isEmpty()) {
            return;
        }

        DB::table('projekt_has_dokumentes')->whereIn('dokument_id', $duplicateIds)->delete();
        DB::table('dokument_has_kategories')->whereIn('dokument_id', $duplicateIds)->delete();
        DB::table('dokument_has_bereiches')->whereIn('dokument_id', $duplicateIds)->delete();
        DB::table('dokument_paket_has_dokumentes')->whereIn('dokument_id', $duplicateIds)->delete();
        DB::table('dokumentes')->whereIn('id', $duplicateIds)->delete();
    }

    public function down(): void
    {
        // Die zentrale Vorlage und spätere Benutzeränderungen bleiben erhalten.
    }
};
