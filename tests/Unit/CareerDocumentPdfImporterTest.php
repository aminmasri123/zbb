<?php

namespace Tests\Unit;

use App\Services\CareerDocumentPdfImporter;
use PHPUnit\Framework\TestCase;

class CareerDocumentPdfImporterTest extends TestCase
{
    public function test_resume_maps_identity_contact_profile_and_multiple_stations(): void
    {
        $content = (new CareerDocumentPdfImporter)->mapText("Lebenslauf\nMina Muster\nmina@example.test\nTelefon: 01234 567890\nKurzprofil\nFreude am Verkauf.\nBerufserfahrung\n2022 - heute Verkäuferin\nMuster GmbH\nKundenberatung\n2020 - 2022 Aushilfe\nBeispiel GmbH\nWarenpflege\nSprachen\nDeutsch B2", 'resume');
        $this->assertSame('Mina Muster', $content['full_name']);
        $this->assertSame("mina@example.test\nTelefon: 01234 567890", $content['contact']);
        $this->assertSame('Freude am Verkauf.', $content['summary']);
        $this->assertCount(3, $content['entries']);
        $this->assertSame('Muster GmbH', $content['entries'][0]['subtitle']);
        $this->assertSame('Warenpflege', $content['entries'][1]['description']);
        $this->assertSame('Deutsch B2', $content['entries'][2]['title']);
    }

    public function test_unstructured_letter_is_retained_without_inventing_fields(): void
    {
        $text = "Mina Muster\nmina@example.test\nEin Anschreiben ohne erkennbare Anrede.\n\nMit eigener Formatierung.";
        $content = (new CareerDocumentPdfImporter)->mapText($text, 'cover_letter');
        $this->assertSame($text, $content['body']);
        $this->assertSame('', $content['full_name']);
        $this->assertSame('', $content['contact']);
        $this->assertSame('', $content['subject']);
    }

    public function test_long_unassigned_resume_text_is_split_without_loss(): void
    {
        $text = str_repeat('Unbekannter Inhalt mit Umlauten äöü. ', 400);
        $content = (new CareerDocumentPdfImporter)->mapText($text, 'resume');
        $this->assertGreaterThan(1, count($content['entries']));
        $result = '';
        foreach ($content['entries'] as $entry) {
            $this->assertLessThanOrEqual(5000, mb_strlen($entry['description']));
            $result .= $entry['description'];
        }
        $this->assertSame(preg_replace('/\s+/u', '', $text), preg_replace('/\s+/u', '', $result));
    }
}
