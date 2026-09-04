<?php

namespace Tests\Unit\Services\Bop;

use App\Services\Bop\AttendanceFooterService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\HeaderFooter;
use Tests\TestCase;
use ZipArchive;

class AttendanceFooterServiceTest extends TestCase
{
    public function test_it_adds_the_partner_image_to_every_spreadsheet_footer(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet();

        (new AttendanceFooterService())->applyToSpreadsheet($spreadsheet);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $footer = $sheet->getHeaderFooter();
            $this->assertSame('&C&G', $footer->getOddFooter());
            $this->assertArrayHasKey(HeaderFooter::IMAGE_FOOTER_CENTER, $footer->getImages());
            $this->assertArrayHasKey(HeaderFooter::IMAGE_FOOTER_CENTER_EVEN, $footer->getImages());
            $this->assertArrayHasKey(HeaderFooter::IMAGE_FOOTER_CENTER_FIRST, $footer->getImages());
            $this->assertSame(
                600,
                $footer->getImages()[HeaderFooter::IMAGE_FOOTER_CENTER]->getWidth()
            );
            $this->assertGreaterThanOrEqual(1.15, $sheet->getPageMargins()->getBottom());
        }

        $target = storage_path('framework/testing/attendance-footer-' . uniqid() . '.xlsx');

        try {
            (new Xlsx($spreadsheet))->save($target);

            $zip = new ZipArchive();
            $this->assertTrue($zip->open($target) === true);

            try {
                $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                $this->assertIsString($sheetXml);
                $this->assertStringContainsString('&amp;C&amp;G', $sheetXml);

                $expectedImage = file_get_contents((new AttendanceFooterService())->imagePath());
                $embeddedImageFound = false;

                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $entryName = $zip->getNameIndex($index);
                    if ($entryName && str_starts_with($entryName, 'xl/media/')) {
                        $embeddedImageFound = $embeddedImageFound || $zip->getFromIndex($index) === $expectedImage;
                    }
                }

                $this->assertTrue($embeddedImageFound);
            } finally {
                $zip->close();
            }
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    public function test_it_replaces_the_existing_word_footer_image(): void
    {
        $source = storage_path('vorlage/projekte/bop/word/bo/Teilnehmendenliste_zum_Nachweis_der_praktischen_Berufsorientierung_A4.docx');
        $target = storage_path('framework/testing/attendance-footer-' . uniqid() . '.docx');

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        copy($source, $target);

        try {
            $service = new AttendanceFooterService();
            $service->applyToWordDocument($target);

            $zip = new ZipArchive();
            $this->assertTrue($zip->open($target) === true);

            try {
                $image = $zip->getFromName('word/media/bop-attendance-footer.png');

                $relationships = $zip->getFromName('word/_rels/footer2.xml.rels');
                $document = $zip->getFromName('word/document.xml');

                $this->assertIsString($relationships);
                $this->assertStringContainsString('media/bop-attendance-footer.png', $relationships);
                $this->assertIsString($document);
                $this->assertSame(3, substr_count($document, 'r:id="rId10"'));
                $this->assertSame(file_get_contents($service->imagePath()), $image);
            } finally {
                $zip->close();
            }
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }
}
