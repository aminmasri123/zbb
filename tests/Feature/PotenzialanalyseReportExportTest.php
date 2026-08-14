<?php

namespace Tests\Feature;

use App\Support\RoutePermissionMap;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class PotenzialanalyseReportExportTest extends TestCase
{
    public function test_pa_report_uses_the_unchanged_original_bop_sources(): void
    {
        $this->assertSame(
            'f20e2d6333994072bb1064c702ceec032f53b6d49b9dffd64ee0fb760c3b20cd',
            hash_file('sha256', resource_path('views/pdf/berichtPA.blade.php'))
        );
        $this->assertSame(
            'fb73d15b9efd482f3c85975f976708c3bf1ea0ba618bd93984577ae806d39d99',
            hash_file('sha256', config_path('beurteilungen.php'))
        );
        $this->assertSame(
            '52385fe29d95bd73ff7015626f8902011cb6a29fae0ad84db53aed59b3f38c8e',
            hash_file('sha256', storage_path('app/public/img/logo-hamet-bop.png'))
        );
        $this->assertSame(
            'd17547962849aa7081d3f5dc5b74c511070a3031241e1c2f69b28ce3ab43fc2b',
            hash_file('sha256', public_path('css/bootstrap.min.css'))
        );
    }

    public function test_original_bop_pa_report_renders_as_four_page_pdf(): void
    {
        $fields = [
            'feinmotorik',
            'grobmotorik',
            'wahrnehmung_symmetrie',
            'analyse_problemloesefaehigkeit',
            'arbeitsplanung',
            'motivation_leistungsbereitschaft',
            'durchhaltevermoegen',
            'sorgfalt',
            'kommunikation',
            'teamfaehigkeit',
            'umgangsformen',
        ];
        $ratings = array_fill_keys($fields, 4);
        $participant = (object) [
            'vorname' => 'Mia',
            'nachname' => 'Beispiel',
            'klasse' => '7.1',
            'schule' => (object) ['schule' => 'GemS Beispiel'],
            'auswertungPa' => (object) $ratings,
            'selbsteinschaetzung' => (object) $ratings,
            'zusammenfassung' => 'Mia hat engagiert an der Potenzialanalyse teilgenommen.',
            'uebungen' => collect([
                (object) [
                    'name' => 'Hammerwerk',
                    'hoechstwert' => 20,
                    'auswertbar' => '1',
                    'pivot' => (object) ['punkte' => 18, 'zeit' => '542'],
                ],
            ]),
        ];

        $pdf = Pdf::loadView('pdf.berichtPA', [
            'beurteilungen' => config('beurteilungen'),
            'teilnehmer' => $participant,
        ])
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setPaper('a4', 'portrait');

        $pdf->render();
        $output = $pdf->output();

        $this->assertStringStartsWith('%PDF-', $output);
        $this->assertGreaterThan(1000, strlen($output));
        $this->assertSame(4, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }

    public function test_pa_report_routes_use_the_existing_pa_export_permission(): void
    {
        $this->assertSame(
            ['gruppe.bop.export.berichte-pa'],
            RoutePermissionMap::permissionsFor('potenzialanalyse.gruppe.teilnehmer.bericht')
        );
        $this->assertSame(
            ['gruppe.bop.export.berichte-pa'],
            RoutePermissionMap::permissionsFor('potenzialanalyse.gruppe.berichte')
        );
    }
}
