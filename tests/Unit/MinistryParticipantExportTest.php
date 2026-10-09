<?php

namespace Tests\Unit;

use App\Services\Bop\MinistryParticipantExport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class MinistryParticipantExportTest extends TestCase
{
    public function test_export_preserves_the_original_package_except_filled_cells_and_formula_caches(): void
    {
        $template = dirname(__DIR__, 2).'/storage/vorlage/projekte/bop/excel/Vorlage.TN-Liste.26-27.xlsx';
        $path = tempnam(sys_get_temp_dir(), 'ministry_');
        try {
            (new MinistryParticipantExport)->write($template, $path, [
                ['B' => 'Schule & Partner <Test>', 'C' => '07.1', 'D' => 'Frau', 'E' => '=Test, Mia', 'G' => 40544, 'M' => 'Ja', 'N' => 9],
                ['B' => 'Schule', 'C' => '07.2', 'D' => 'Herr', 'E' => 'Muster, Noah', 'M' => 'Nein', 'N' => 4],
            ]);
            $original = new ZipArchive;
            $export = new ZipArchive;
            $this->assertTrue($original->open($template));
            $this->assertTrue($export->open($path));
            $this->assertSame($original->numFiles, $export->numFiles);
            for ($index = 0; $index < $original->numFiles; $index++) {
                $name = $original->getNameIndex($index);
                if ($name !== 'xl/worksheets/sheet1.xml') {
                    $this->assertSame($original->getFromName($name), $export->getFromName($name), $name);
                }
            }
            $before = $original->getFromName('xl/worksheets/sheet1.xml');
            $after = $export->getFromName('xl/worksheets/sheet1.xml');
            // Everything outside the explicitly written cells must remain byte-identical.
            $cells = '/<c\b[^>]*\br="(?:[BCDEGMN][67]|A[67]|O[567])"[^>]*?(?:\/>|>.*?<\/c>)/s';
            $this->assertSame(hash('sha256', preg_replace($cells, '', $before)), hash('sha256', preg_replace($cells, '', $after)));
            preg_match_all('/<f\b[^>]*?(?:\/>|>.*?<\/f>)/s', $before, $formulasBefore);
            preg_match_all('/<f\b[^>]*?(?:\/>|>.*?<\/f>)/s', $after, $formulasAfter);
            $this->assertSame($formulasBefore, $formulasAfter);
            $original->close();
            $export->close();

            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertSame('Tabelle1', $sheet->getTitle());
            $this->assertSame('Schule & Partner <Test>', (string) $sheet->getCell('B6')->getValue());
            $this->assertSame('07.1', (string) $sheet->getCell('C6')->getValue());
            $this->assertSame('=Test, Mia', (string) $sheet->getCell('E6')->getValue());
            $this->assertNotSame('f', $sheet->getCell('E6')->getDataType());
            $this->assertSame(9, $sheet->getCell('N6')->getValue());
            $this->assertSame(40544, $sheet->getCell('G6')->getValue());
            $this->assertSame(99, $sheet->getCell('O6')->getCalculatedValue());
            $this->assertSame(0, $sheet->getCell('O7')->getCalculatedValue());
            $this->assertSame(99, $sheet->getCell('O5')->getOldCalculatedValue());
            $this->assertSame(99, $sheet->getCell('O5')->getCalculatedValue());
            $this->assertSame(2, $sheet->getCell('A7')->getCalculatedValue());
            $this->assertSame('"Ja,Nein"', $sheet->getDataValidation('M6')->getFormula1());
            $this->assertSame('landscape', $sheet->getPageSetup()->getOrientation());
        } finally {
            @unlink($path);
        }
    }

    public function test_export_rejects_overflow_without_changing_the_template(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maximal 403 Teilnehmer');
        (new MinistryParticipantExport)->write('unused', 'unused', array_fill(0, 404, []));
    }
}
