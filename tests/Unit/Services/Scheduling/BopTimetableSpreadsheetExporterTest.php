<?php

namespace Tests\Unit\Services\Scheduling;

use App\Services\Scheduling\BopTimetableSpreadsheetExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PHPUnit\Framework\TestCase;

class BopTimetableSpreadsheetExporterTest extends TestCase
{
    public function test_it_creates_a_coloured_horizontal_xlsx_timeline(): void
    {
        $path = (new BopTimetableSpreadsheetExporter())->create([
            'schedule_date' => '2026-08-31',
            'config' => [
                'start_time' => '08:30',
                'end_time' => '10:00',
                'groups' => ['G1', 'G2'],
            ],
            'entries' => [
                [
                    'group_key' => null,
                    'type' => 'shared',
                    'title' => 'Begrüßung',
                    'start_time' => '08:30',
                    'end_time' => '08:45',
                    'bereich_id' => null,
                    'meta' => ['group_labels' => ['G1', 'G2']],
                ],
                [
                    'group_key' => 'G1',
                    'type' => 'area',
                    'title' => 'IT und Medien',
                    'start_time' => '08:45',
                    'end_time' => '09:15',
                    'bereich_id' => 2,
                    'meta' => ['supervisor_name' => 'Erika Beispiel'],
                ],
            ],
        ], 'Testschule');

        try {
            $this->assertFileExists($path);
            $this->assertSame('PK', file_get_contents($path, false, null, 0, 2));

            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $this->assertSame('Zeitplan', $sheet->getTitle());
            $this->assertStringContainsString('Testschule', $sheet->getCell('A1')->getValue());
            $this->assertSame('Gruppe', $sheet->getCell('A4')->getValue());
            $this->assertSame('G1', $sheet->getCell('A5')->getValue());
            $this->assertStringContainsString('IT und Medien', $sheet->getCell('Q5')->getValue());
            $this->assertSame('CFFAFE', $sheet->getStyle('Q5')->getFill()->getStartColor()->getRGB());
            $this->assertSame(PageSetup::ORIENTATION_LANDSCAPE, $sheet->getPageSetup()->getOrientation());
            $this->assertSame(PageSetup::PAPERSIZE_A3, $sheet->getPageSetup()->getPaperSize());
            $spreadsheet->disconnectWorksheets();
        } finally {
            @unlink($path);
        }
    }
}
