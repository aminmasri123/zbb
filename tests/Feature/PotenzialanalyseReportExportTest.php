<?php

namespace Tests\Feature;

use App\Models\Gruppe;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\PotenzialanalyseBericht;
use App\Support\RoutePermissionMap;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PotenzialanalyseReportExportTest extends TestCase
{
    public function test_pa_report_pdf_can_be_rendered_with_the_complete_report_payload(): void
    {
        $person = new Personen([
            'id' => 42,
            'vorname' => 'Mia',
            'nachname' => 'Beispiel',
            'geburtsdatum' => '2012-03-04',
        ]);
        $person->id = 42;

        $school = new Partner(['name' => 'GemS Beispiel']);
        $student = new PersonenIstSchueler(['klasse' => '7.1']);
        $report = new PotenzialanalyseBericht([
            'status' => 'fertig',
            'staerken' => 'Teamfähigkeit',
            'entwicklungsfelder' => 'Arbeitsplanung',
            'empfehlung' => 'Weitere praktische Erprobung.',
            'bericht_text' => 'Mia hat engagiert an der Potenzialanalyse teilgenommen.',
        ]);
        $report->updated_at = Carbon::parse('2026-08-10');

        $pdf = Pdf::loadView('pdf.potenzialanalyse-bericht', [
            'person' => $person,
            'gruppe' => new Gruppe(),
            'student' => $student,
            'school' => $school,
            'merkmale' => collect([
                'Soziale Kompetenzen' => collect([[
                    'label' => 'Teamfähigkeit',
                    'selbst' => 4,
                    'anleiter' => 5,
                    'selbst_bemerkung' => null,
                    'anleiter_bemerkung' => 'Arbeitet konstruktiv mit.',
                ]]),
            ]),
            'uebungen' => collect([[
                'name' => 'Hammerwerk',
                'tag' => 1,
                'punkte' => 18,
                'hoechstwert' => 20,
                'zeit' => '12:30 min',
            ]]),
            'kriterien' => collect(),
            'bericht' => $report,
            'statusLabel' => 'Fertig',
            'zeitraum' => '08.06.2026 – 09.06.2026',
            'erstelltAm' => '10.08.2026',
        ])->setPaper('a4')->output();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_pa_report_routes_use_the_existing_pa_export_permission(): void
    {
        $this->assertSame(
            ['gruppe.bop.export.auswertungsbogen-pa'],
            RoutePermissionMap::permissionsFor('potenzialanalyse.gruppe.teilnehmer.bericht')
        );
        $this->assertSame(
            ['gruppe.bop.export.auswertungsbogen-pa'],
            RoutePermissionMap::permissionsFor('potenzialanalyse.gruppe.berichte')
        );
    }
}
