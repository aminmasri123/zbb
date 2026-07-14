<?php

namespace App\Services\Bop;

use App\Models\Partner;
use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class UsbStickLetterExportService
{
    public function export(Partner $school, string $schoolYear, string $date): string
    {
        $template = storage_path('vorlage/projekte/bop/word/USB-Stick-Brief.docx');

        if (! is_file($template)) {
            throw new RuntimeException('Die Vorlage für den USB-Stick-Brief wurde nicht gefunden.');
        }

        $output = tempnam(sys_get_temp_dir(), 'bop-usb-brief-');

        if ($output === false || ! copy($template, $output)) {
            throw new RuntimeException('Der USB-Stick-Brief konnte nicht vorbereitet werden.');
        }

        $zip = new ZipArchive;
        if ($zip->open($output) !== true) {
            @unlink($output);
            throw new RuntimeException('Die Word-Vorlage konnte nicht geöffnet werden.');
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();
            @unlink($output);
            throw new RuntimeException('Die Word-Vorlage enthält keinen Dokumenttext.');
        }

        $document = new DOMDocument;
        $document->preserveWhiteSpace = true;
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $letterDate = date_create_immutable($date)?->format('d.m.Y');
        if ($letterDate === null) {
            throw new RuntimeException('Das Briefdatum ist ungültig.');
        }

        $year = $this->shortSchoolYear($schoolYear);
        $name = trim($school->name);
        $this->replaceParagraph($xpath, 'Saarbrücken, den', "{$name} Saarbrücken, den {$letterDate}");
        $this->replaceParagraph($xpath, 'Passwort zum Entsperren des USB-Sticks:', "Passwort zum Entsperren des USB-Sticks: BOP@{$year}.{$name}", true);

        $zip->addFromString('word/document.xml', $document->saveXML());
        $zip->close();

        return $output;
    }

    private function replaceParagraph(DOMXPath $xpath, string $needle, string $replacement, bool $keepPrefixFormatting = false): void
    {
        foreach ($xpath->query('//w:p') as $paragraph) {
            $textNodes = $xpath->query('.//w:t', $paragraph);
            $current = '';
            foreach ($textNodes as $textNode) {
                $current .= $textNode->textContent;
            }

            if (! str_contains($current, $needle)) {
                continue;
            }

            $target = $textNodes->item(0);
            if ($keepPrefixFormatting) {
                foreach ($textNodes as $textNode) {
                    if (str_contains($textNode->textContent, 'BOP@')) {
                        $target = $textNode;
                        break;
                    }
                }
                $replacement = 'BOP@'.explode('BOP@', $replacement, 2)[1];
            }

            $started = false;
            foreach ($textNodes as $textNode) {
                if ($textNode === $target) {
                    $started = true;
                    $textNode->nodeValue = $replacement;
                    $textNode->setAttribute('xml:space', 'preserve');
                } elseif (! $keepPrefixFormatting || $started) {
                    $textNode->nodeValue = '';
                }
            }

            if (! $keepPrefixFormatting) {
                foreach ($textNodes as $textNode) {
                    if ($textNode !== $target) {
                        $textNode->nodeValue = '';
                    }
                }
            }

            return;
        }

        throw new RuntimeException("Die Textstelle '{$needle}' fehlt in der Word-Vorlage.");
    }

    private function shortSchoolYear(string $schoolYear): string
    {
        if (preg_match('/(\d{2,4})\D+(\d{2,4})/', $schoolYear, $matches) !== 1) {
            throw new RuntimeException('Das Schuljahr hat ein ungültiges Format.');
        }

        return substr($matches[1], -2).'-'.substr($matches[2], -2);
    }
}
