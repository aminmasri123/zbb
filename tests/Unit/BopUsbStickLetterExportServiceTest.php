<?php

namespace Tests\Unit;

use App\Models\Partner;
use App\Services\Bop\UsbStickLetterExportService;
use Tests\TestCase;
use ZipArchive;

class BopUsbStickLetterExportServiceTest extends TestCase
{
    public function test_it_fills_school_date_and_password_without_changing_saarbruecken(): void
    {
        $partner = new Partner(['name' => 'Max-Planck-Schule']);
        $path = (new UsbStickLetterExportService)->export($partner, '2025/2026', '2026-07-14');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);

        $text = strip_tags($xml);
        $this->assertStringContainsString('Max-Planck-Schule Saarbrücken, den 14.07.2026', $text);
        $this->assertStringContainsString('BOP@25-26.Max-Planck-Schule', $text);
        $this->assertStringNotContainsString('Güdingen', $text);
    }
}
