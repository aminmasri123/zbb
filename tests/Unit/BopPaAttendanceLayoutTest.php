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
            $this->assertSame(
                file_get_contents(public_path('img/bop/kooperationspartner.png')),
                $zip->getFromName('word/media/bop-attendance-footer.png')
            );
        } finally {
            $zip->close();
        }
    }

    public function test_digital_pdf_reserves_two_participant_pages_and_page_three_for_trainers(): void
    {
        $component = file_get_contents(resource_path('js/Pages/Partner/BOP/ModalAnwesenheitslistePADigital.vue'));

        $this->assertIsString($component);
        $this->assertStringContainsString('rowsPerPage: 17', $component);
        $this->assertStringContainsString('firstParticipantPageRows: isPreparationPa.value ? 17 : 13', $component);
        $this->assertStringContainsString('secondParticipantPageRows: isPreparationPa.value ? 17 : 21', $component);
        $this->assertStringContainsString('tableY: (isPreparationPa.value ? 48 : 62) * widthScale', $component);
        $this->assertStringContainsString("doc.setFontSize(form.exportFormat === 'A3' ? 17 : 12)", $component);
        $this->assertStringContainsString("doc.setFontSize(form.exportFormat === 'A3' ? 14 : 10)", $component);
        $this->assertStringContainsString("doc.setFontSize(form.exportFormat === 'A3' ? 12.5 : 9)", $component);
        $this->assertStringContainsString("exportMode: 'alle'", $component);
        $this->assertStringContainsString('const classSchedules = ref({})', $component);
        $this->assertStringContainsString('mergedScheduleForAllClasses', $component);
        $this->assertStringContainsString('signature_ids_by_class', $component);
        $this->assertStringContainsString('isParticipantExpectedOnDay', $component);
        $this->assertStringContainsString('Zur Bestätigung delete eingeben', $component);
        $this->assertStringContainsString('const classedParticipants = computed', $component);
        $this->assertStringContainsString("Klasse {{ row.participant.klasse || 'ohne Klassenangabe' }}", $component);
        $this->assertStringContainsString('const participantPages = isPreparationPa.value ? calculatedParticipantPages : 2', $component);
        $this->assertStringContainsString('drawTrainerTable(doc, layout)', $component);
        $this->assertStringContainsString("doc.text(String(participant?.nachname || '')", $component);
        $this->assertStringContainsString('drawPdfSignature(doc, signature', $component);
        $this->assertStringContainsString('Unterschriftenliste zum Nachweis der Potenzialanalyse - PA/ Ausbilder/-innen', $component);
        $this->assertStringContainsString("headerPageY: (isPreparationPa.value ? 7 : 15) * widthScale", $component);
        $this->assertStringContainsString('const preparationPageMargin = 15 * widthScale', $component);
        $this->assertStringContainsString("headerX: (isPreparationPa.value ? 15 : 20) * widthScale", $component);
        $this->assertStringContainsString("doc.text('Zeitraum:', layout.headerPeriodX, layout.headerTitleY)", $component);
        $this->assertStringContainsString('const tableWidth = 201.6 * layout.widthScale', $component);
    }
}
