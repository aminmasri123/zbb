<?php

namespace Tests\Unit;

use App\Services\Bop\RolandEvaluationPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use setasign\Fpdi\Fpdi;
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

    public function test_large_roland_export_uses_static_template_for_all_pages(): void
    {
        $participant = [
            'name' => 'Mustermann, Erika',
            'geburtsdatum' => '15.04.2011',
            'geschlecht' => 'w',
            'schule' => 'Gemeinschaftsschule Musterstadt',
            'klasse' => '8a',
        ];

        $path = app(RolandEvaluationPdfService::class)->create(
            collect(array_fill(0, 12, $participant))
        );

        try {
            $pdf = new Fpdi();

            $this->assertSame(12, $pdf->setSourceFile($path));
            $this->assertGreaterThan(10_000, File::size($path));
        } finally {
            File::delete($path);
        }
    }

    public function test_roland_export_uses_the_versioned_non_overlapping_template(): void
    {
        $service = file_get_contents(app_path('Services/Bop/RolandEvaluationPdfService.php'));

        $this->assertIsString($service);
        $this->assertStringContainsString('auswertungsbogen-pa-roland-template-v3.pdf', $service);
        $this->assertFileExists(resource_path('pdf/auswertungsbogen-pa-roland-template-v3.pdf'));
    }
}
