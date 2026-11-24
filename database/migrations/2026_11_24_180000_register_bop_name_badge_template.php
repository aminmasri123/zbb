<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpWord\PhpWord;

return new class extends Migration
{
    public function up(): void
    {
        $relative = '/vorlage/projekte/bop/word/Namensschild_BOP.docx';
        $path = storage_path(ltrim($relative, '/'));
        if (!is_file($path)) {
            File::ensureDirectoryExists(dirname($path));
            $word = new PhpWord;
            $section = $word->addSection(['pageSizeW' => 11906, 'pageSizeH' => 16838]);
            $section->addTextBreak(8);
            $section->addText('-----------------------', ['size' => 40], ['alignment' => 'center']);
            $section->addText('${voller_name}', ['size' => 48], ['alignment' => 'center']);
            $section->addText('${bereichsauswahl_code}', ['size' => 12], ['alignment' => 'center']);
            $section->addText('-----------------------', ['size' => 40], ['alignment' => 'center']);
            $word->save($path);
        }
        $id = DB::table('dokumentes')->where('dateipfad', $relative)->value('id');
        if (!$id) {
            $id = DB::table('dokumentes')->insertGetId([
                'name' => 'Namensschilder BOP',
                'typ' => 'word', 'kontext' => 'teilnehmer', 'einsatzbereich' => 'gruppe',
                'dateipfad' => $relative, 'dateipfadName' => 'Namensschild_BOP.docx',
                'ausgabeformate' => json_encode(['docx']),
                'export_permission' => 'gruppe.bop.export.namensschilder',
                'beschreibung' => 'Gemeinsame Vorlage für Namensschilder in Partner, Gruppe und BOP-Paketen. Platzhalter: ${voller_name}, ${bereichsauswahl_code}.',
                'aktiv' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (DB::table('projekts')->whereRaw('UPPER(name) LIKE ?', ['%BOP%'])->pluck('id') as $projectId) {
            DB::table('projekt_has_dokumentes')->updateOrInsert(
                ['projekt_id' => $projectId, 'dokument_id' => $id], []
            );
        }
    }

    public function down(): void
    {
        // Keep the editable template and user changes.
    }
};
