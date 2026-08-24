<?php

namespace Tests\Feature;

use App\Models\Gruppe;
use App\Models\Personen;
use App\Services\Bop\PotenzialanalyseReportService;
use App\Support\RoutePermissionMap;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use setasign\Fpdi\Fpdi;
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
        $pdf = Pdf::loadView('pdf.berichtPA', [
            'beurteilungen' => config('beurteilungen'),
            'teilnehmer' => $this->participantFixture(),
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

    public function test_data_driven_profile_report_renders_without_bop_specific_fields(): void
    {
        $person = new Personen(['vorname' => 'Mia', 'nachname' => 'Beispiel']);
        $gruppe = new Gruppe();
        $gruppe->setRelation('projekt', (object) ['name' => 'Freies Projekt']);

        $pdf = Pdf::loadView('pdf.bericht-pa-profil', [
            'person' => $person,
            'gruppe' => $gruppe,
            'merkmale' => collect([
                'Zukunftskompetenzen' => collect([[
                    'label' => 'Lernflexibilität',
                    'selbst' => 4,
                    'anleiter' => 5,
                    'selbst_bemerkung' => null,
                    'anleiter_bemerkung' => 'Passt Strategien sicher an.',
                ]]),
            ]),
            'uebungen' => collect([[
                'name' => 'Neue Aufgabe',
                'punkte' => 17,
                'fehler' => 3,
                'hoechstwert' => 20,
                'zeit' => '4:12 min',
            ]]),
            'bericht' => null,
            'berichtConfig' => [
                'titel' => 'Projektbezogene Auswertung',
                'untertitel' => 'Version 1',
                'uebungsergebnisse_anzeigen' => true,
                'selbsteinschaetzung_anzeigen' => true,
                'staerkenprofil_anzeigen' => true,
            ],
            'zeitraum' => '01.08.2026 - 05.08.2026',
            'erstelltAm' => '24.08.2026',
        ])->setPaper('a4', 'portrait');

        $output = $pdf->output();

        $this->assertStringStartsWith('%PDF-', $output);
        $this->assertGreaterThan(1000, strlen($output));
    }

    public function test_group_export_merges_thirty_original_four_page_reports(): void
    {
        $singleReport = Pdf::loadView('pdf.berichtPA', [
            'beurteilungen' => config('beurteilungen'),
            'teilnehmer' => $this->participantFixture(),
        ])
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setPaper('a4', 'portrait')
            ->output();

        $gruppe = new Gruppe(['id' => 10]);
        $gruppe->setRelation('bereich', null);
        $gruppe->setRelation('teilnehmer', collect(range(1, 30))->map(fn (int $id) => new Personen([
            'id' => $id,
            'vorname' => 'Person '.str_pad((string) $id, 2, '0', STR_PAD_LEFT),
            'nachname' => 'Test',
        ])));

        $service = new class($singleReport) extends PotenzialanalyseReportService
        {
            public function __construct(private readonly string $singleReport)
            {
            }

            public function renderPdf(Gruppe $gruppe, Personen $person): string
            {
                return $this->singleReport;
            }
        };

        $result = $service->createGroupPdf($gruppe);

        try {
            $merged = new Fpdi();
            $this->assertSame(30, $result['count']);
            $this->assertSame(120, $merged->setSourceFile($result['path']));
        } finally {
            File::delete($result['path']);
        }
    }

    private function participantFixture(): object
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

        return (object) [
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
