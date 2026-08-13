<?php

namespace Tests\Unit;

use Tests\TestCase;
use ZipArchive;

class BopPaAttendanceLayoutTest extends TestCase
{
    public function test_word_template_has_34_participant_places_and_a_trainer_page(): void
    {
        $template = storage_path('vorlage/projekte/bop/word/pa/Anwesenheitsliste-PA.docx');
        $zip = new ZipArchive();

        $this->assertTrue($zip->open($template) === true);

        try {
            $documentXml = $zip->getFromName('word/document.xml');

            $this->assertIsString($documentXml);
            $this->assertStringContainsString('${nachname34}', $documentXml);
            $this->assertStringContainsString('${vorname34}', $documentXml);
            $this->assertStringContainsString('Ausbilder/-innen', $documentXml);
        } finally {
            $zip->close();
        }
    }

    public function test_digital_pdf_reserves_two_participant_pages_and_page_three_for_trainers(): void
    {
        $component = file_get_contents(resource_path('js/Pages/Partner/BOP/ModalAnwesenheitslistePADigital.vue'));

        $this->assertIsString($component);
        $this->assertStringContainsString('rowsPerPage: 17', $component);
        $this->assertStringContainsString('const participantPages = isPreparationPa.value ? calculatedParticipantPages : 2', $component);
        $this->assertStringContainsString('drawTrainerTable(doc, layout)', $component);
        $this->assertStringContainsString('Unterschriftenliste zum Nachweis der Potenzialanalyse - PA/ Ausbilder/-innen', $component);
    }
}
