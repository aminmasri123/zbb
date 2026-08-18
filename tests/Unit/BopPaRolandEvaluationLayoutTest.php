<?php

namespace Tests\Unit;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class BopPaRolandEvaluationLayoutTest extends TestCase
{
    public function test_roland_layout_contains_the_reference_tasks_and_corrected_labels(): void
    {
        $view = file_get_contents(resource_path('views/pdf/auswertungsbogenPA-roland.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('Auswertung hametBOP', $view);
        $this->assertStringContainsString("[4, 'Robina Hood'", $view);
        $this->assertStringContainsString("[6, 'Spaghetti-Gericht'", $view);
        $this->assertStringContainsString("[14, 'Turmbau'", $view);
        $this->assertStringContainsString("[15, '1.000-€-Gewinn'", $view);
        $this->assertStringContainsString("['Treppe', 'bis', 5, 10, 14, 19, 24]", $view);
        $this->assertStringContainsString('grau hinterlegte Felder: optional zu beurteilen', $view);
    }

    public function test_roland_layout_keeps_every_participant_on_exactly_one_page(): void
    {
        $participant = [
            'name' => 'Mustermann, Erika',
            'geburtsdatum' => '15.04.2011',
            'geschlecht' => 'w',
            'schule' => 'Gemeinschaftsschule Musterstadt',
            'klasse' => '8a',
        ];

        $pdf = Pdf::loadView('pdf.auswertungsbogenPA-roland', [
            'teilnehmer' => collect([$participant, $participant]),
            'schulname' => $participant['schule'],
            'schuljahr' => '2026',
            'teil' => '1',
        ])->setPaper('A4', 'landscape');

        $pdf->render();

        $this->assertSame(2, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }
}
