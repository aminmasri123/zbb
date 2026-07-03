<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bopProjektId = DB::table('projekts')->whereRaw('LOWER(name) = ?', ['bop'])->value('id');
        $bopKategorieId = DB::table('dokument_kategories')->where('name', 'BOP')->value('id');
        $potenzialanalyseId = DB::table('bereiches')->where('name', 'Potenzialanalyse')->value('id');

        $vorlagen = [
            ['name' => 'Hausordnung BOP', 'typ' => 'word', 'kontext' => 'teilnehmer', 'einsatzbereich' => 'gruppe', 'path' => '/vorlage/projekte/bop/word/Hausordnung_BOP.docx'],
            ['name' => 'Berufsfelderprobung BOP', 'typ' => 'word', 'kontext' => 'teilnehmer', 'einsatzbereich' => 'gruppe', 'path' => '/vorlage/projekte/bop/word/Berufsfelderprobung_Schueler_BOP.docx'],
            ['name' => 'Auswertungsbogen BOP', 'typ' => 'word', 'kontext' => 'teilnehmer', 'einsatzbereich' => 'gruppe', 'path' => '/vorlage/projekte/bop/word/Auswertungsbogen_BOP.docx'],
            ['name' => 'Toilettennutzungsliste BOP', 'typ' => 'word', 'kontext' => 'gruppe', 'einsatzbereich' => 'gruppe', 'path' => '/vorlage/projekte/bop/word/Toilettennutzungsliste.docx'],
            ['name' => 'Zertifikat Maske POBO', 'typ' => 'word', 'kontext' => 'teilnehmer', 'einsatzbereich' => 'gruppe', 'path' => '/vorlage/projekte/bop/word/Zertifikat_Maske_POBO.docx'],
            ['name' => 'Zertifikat Maske POBO klein text', 'typ' => 'word', 'kontext' => 'teilnehmer', 'einsatzbereich' => 'gruppe', 'path' => '/vorlage/projekte/bop/word/Zertifikat_Maske_POBO_klein_text.docx'],
            ['name' => 'Teilnahmebescheinigung POBO', 'typ' => 'word', 'kontext' => 'teilnehmer', 'einsatzbereich' => 'gruppe', 'path' => '/vorlage/projekte/bop/word/Teilnahmebescheinigung_Maske_POBO.docx'],
            ['name' => 'Teilnahmebescheinigung POBO klein text', 'typ' => 'word', 'kontext' => 'teilnehmer', 'einsatzbereich' => 'gruppe', 'path' => '/vorlage/projekte/bop/word/Teilnahmebescheinigung_Maske_POBO_klein_text.docx'],

            ['name' => 'Anwesenheit PA', 'typ' => 'word', 'kontext' => 'gruppe', 'einsatzbereich' => 'gruppe', 'path' => '/vorlage/projekte/bop/word/Anwesenheit_PA.docx', 'pa' => true],
            ['name' => 'Auswertung PA', 'typ' => 'word', 'kontext' => 'teilnehmer', 'einsatzbereich' => 'gruppe', 'path' => '/vorlage/projekte/bop/word/Auswertung_PA.docx', 'pa' => true],
            ['name' => 'Zertifikat Maske PA', 'typ' => 'word', 'kontext' => 'teilnehmer', 'einsatzbereich' => 'gruppe', 'path' => '/vorlage/projekte/bop/word/Zertifikat_Maske_PA.docx', 'pa' => true],
            ['name' => 'Zertifikat Maske PA klein text', 'typ' => 'word', 'kontext' => 'teilnehmer', 'einsatzbereich' => 'gruppe', 'path' => '/vorlage/projekte/bop/word/Zertifikat_Maske_PA_klein_text.docx', 'pa' => true],
            ['name' => 'Teilnahmebescheinigung PA', 'typ' => 'word', 'kontext' => 'teilnehmer', 'einsatzbereich' => 'gruppe', 'path' => '/vorlage/projekte/bop/word/Teilnahmebescheinigung_PA.docx', 'pa' => true],

            ['name' => 'Anwesenheitsliste BOP', 'typ' => 'excel', 'kontext' => 'gruppe', 'einsatzbereich' => 'partner', 'path' => '/vorlage/projekte/bop/excel/Anwesenheitsliste.xlsx'],
            ['name' => 'Anwesenheitsliste PA', 'typ' => 'excel', 'kontext' => 'gruppe', 'einsatzbereich' => 'partner', 'path' => '/vorlage/projekte/bop/excel/Anwesenheitsliste-PA.xlsx'],
            ['name' => 'Anwesenheitsliste BO Tag1', 'typ' => 'excel', 'kontext' => 'gruppe', 'einsatzbereich' => 'partner', 'path' => '/vorlage/projekte/bop/excel/Anwesenheitsliste-BO-TAG1.xlsx'],
            ['name' => 'Anwesenheitsliste Vorbereitung BO Tage', 'typ' => 'excel', 'kontext' => 'gruppe', 'einsatzbereich' => 'partner', 'path' => '/vorlage/projekte/bop/excel/Anwesenheitsliste-Vorbereitung-BO-Tage.xlsx'],
            ['name' => 'Anwesenheit Rechnung', 'typ' => 'excel', 'kontext' => 'gruppe', 'einsatzbereich' => 'partner', 'path' => '/vorlage/projekte/bop/excel/Anwesenheit_Rechnung.xlsx'],
            ['name' => 'Bereichauswahl', 'typ' => 'excel', 'kontext' => 'gruppe', 'einsatzbereich' => 'partner', 'path' => '/vorlage/projekte/bop/excel/Bereichauswahl.xlsx'],
            ['name' => 'Bereichseinteilung', 'typ' => 'excel', 'kontext' => 'gruppe', 'einsatzbereich' => 'partner', 'path' => '/vorlage/projekte/bop/excel/Bereichseinteilung.xlsx'],
            ['name' => 'Liste Einverstaendniserklaerung', 'typ' => 'excel', 'kontext' => 'gruppe', 'einsatzbereich' => 'partner', 'path' => '/vorlage/projekte/bop/excel/Liste-Elterneinverstaendniserklaerung.xlsx'],
            ['name' => 'BOP Zertifikat praxisorientierte Berufsorientierungstage Formular', 'typ' => 'pdf', 'kontext' => 'gruppe', 'einsatzbereich' => 'partner', 'path' => '/vorlage/projekte/bop/pdf/bop-zertifikat-praxisorientierte-berufsorientierungs-tage-formular.pdf'],
        ];

        foreach ($vorlagen as $index => $vorlage) {
            if (!file_exists(storage_path(ltrim($vorlage['path'], '/')))) {
                continue;
            }

            $formats = match ($vorlage['typ']) {
                'word' => ['docx', 'pdf'],
                'excel' => ['xlsx', 'pdf'],
                default => ['pdf'],
            };

            DB::table('dokumentes')->updateOrInsert(
                ['dateipfad' => $vorlage['path']],
                [
                    'name' => $vorlage['name'],
                    'typ' => $vorlage['typ'],
                    'kontext' => $vorlage['kontext'],
                    'einsatzbereich' => $vorlage['einsatzbereich'],
                    'ausgabeformate' => json_encode($formats),
                    'version' => 'BOP-Programm',
                    'dateipfadName' => basename($vorlage['path']),
                    'beschreibung' => 'Aus C:\\xampp\\htdocs\\bop uebernommene Originalvorlage.',
                    'aktiv' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $dokumentId = DB::table('dokumentes')->where('dateipfad', $vorlage['path'])->value('id');
            if (!$dokumentId) {
                continue;
            }

            if ($bopProjektId) {
                DB::table('projekt_has_dokumentes')->updateOrInsert(
                    ['projekt_id' => $bopProjektId, 'dokument_id' => $dokumentId],
                    ['gruppen_export' => 1, 'serienbrief' => 1, 'sort_order' => $index + 1]
                );
            }

            if ($bopKategorieId) {
                DB::table('dokument_has_kategories')->updateOrInsert(
                    ['dokument_id' => $dokumentId, 'dokument_kategorie_id' => $bopKategorieId],
                    ['gruppen_export' => 1, 'serienbrief' => 1]
                );
            }

            if (!empty($vorlage['pa']) && $potenzialanalyseId) {
                DB::table('dokument_has_bereiches')->updateOrInsert([
                    'dokument_id' => $dokumentId,
                    'bereich_id' => $potenzialanalyseId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $paths = [
            '/vorlage/projekte/bop/word/Hausordnung_BOP.docx',
            '/vorlage/projekte/bop/word/Berufsfelderprobung_Schueler_BOP.docx',
            '/vorlage/projekte/bop/word/Auswertungsbogen_BOP.docx',
            '/vorlage/projekte/bop/word/Toilettennutzungsliste.docx',
            '/vorlage/projekte/bop/word/Zertifikat_Maske_POBO.docx',
            '/vorlage/projekte/bop/word/Zertifikat_Maske_POBO_klein_text.docx',
            '/vorlage/projekte/bop/word/Teilnahmebescheinigung_Maske_POBO.docx',
            '/vorlage/projekte/bop/word/Teilnahmebescheinigung_Maske_POBO_klein_text.docx',
            '/vorlage/projekte/bop/word/Anwesenheit_PA.docx',
            '/vorlage/projekte/bop/word/Auswertung_PA.docx',
            '/vorlage/projekte/bop/word/Zertifikat_Maske_PA.docx',
            '/vorlage/projekte/bop/word/Zertifikat_Maske_PA_klein_text.docx',
            '/vorlage/projekte/bop/word/Teilnahmebescheinigung_PA.docx',
            '/vorlage/projekte/bop/excel/Anwesenheitsliste.xlsx',
            '/vorlage/projekte/bop/excel/Anwesenheitsliste-PA.xlsx',
            '/vorlage/projekte/bop/excel/Anwesenheitsliste-BO-TAG1.xlsx',
            '/vorlage/projekte/bop/excel/Anwesenheitsliste-Vorbereitung-BO-Tage.xlsx',
            '/vorlage/projekte/bop/excel/Anwesenheit_Rechnung.xlsx',
            '/vorlage/projekte/bop/excel/Bereichauswahl.xlsx',
            '/vorlage/projekte/bop/excel/Bereichseinteilung.xlsx',
            '/vorlage/projekte/bop/excel/Liste-Elterneinverstaendniserklaerung.xlsx',
            '/vorlage/projekte/bop/pdf/bop-zertifikat-praxisorientierte-berufsorientierungs-tage-formular.pdf',
        ];

        $ids = DB::table('dokumentes')->whereIn('dateipfad', $paths)->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('projekt_has_dokumentes')->whereIn('dokument_id', $ids)->delete();
            DB::table('dokument_has_kategories')->whereIn('dokument_id', $ids)->delete();
            DB::table('dokument_has_bereiches')->whereIn('dokument_id', $ids)->delete();
            DB::table('dokumentes')->whereIn('id', $ids)->delete();
        }
    }
};
