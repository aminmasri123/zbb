<?php

namespace App\Services\Bop;

use DOMDocument;
use DOMXPath;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use ZipArchive;

class BibbAttendanceWordTemplate
{
    public function processor(string $source, int $participants): TemplateProcessor
    {
        $temporary = tempnam(sys_get_temp_dir(), 'bibb-word-');
        if ($temporary === false) throw new RuntimeException('Temporäre Word-Vorlage konnte nicht erstellt werden.');
        try {
            if (!copy($source, $temporary)) throw new RuntimeException('Word-Vorlage konnte nicht kopiert werden.');
            $zip = new ZipArchive;
            if ($zip->open($temporary) !== true) throw new RuntimeException('Word-Vorlage konnte nicht geöffnet werden.');
            try {
                $document = new DOMDocument;
                if (!$document->loadXML($zip->getFromName('word/document.xml'))) throw new RuntimeException('Ungültige Word-Vorlage.');
                $xpath = new DOMXPath($document);
                $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                foreach ($xpath->query('//w:body/w:tbl') as $table) {
                    $rows = [];
                    foreach ($xpath->query('./w:tr', $table) as $row) {
                        if (preg_match('/\$\{nachname\d+\}/u', $row->textContent)) $rows[] = $row;
                    }
                    if (!$rows) {
                        // Keep each instructor confirmation together on one page.
                        $paragraphs = $xpath->query('.//w:p', $table);
                        foreach ($paragraphs as $index => $paragraph) {
                            if ($index === $paragraphs->length - 1) continue;
                            $properties = $xpath->query('./w:pPr', $paragraph)->item(0);
                            if (!$properties) {
                                $properties = $document->createElementNS($paragraph->namespaceURI, 'w:pPr');
                                $paragraph->insertBefore($properties, $paragraph->firstChild);
                            }
                            $properties->appendChild($document->createElementNS($paragraph->namespaceURI, 'w:keepNext'));
                        }
                        continue;
                    }
                    $prototype = end($rows)->cloneNode(true);
                    foreach ($rows as $index => $row) {
                        if ($index >= $participants) {
                            $table->removeChild($row);
                        } else {
                            foreach ($xpath->query('./w:tc[1]//w:t', $row) as $part => $text) {
                                $text->nodeValue = $part === 0 ? (string) ($index + 1) : '';
                            }
                        }
                    }
                    for ($number = count($rows) + 1; $number <= $participants; $number++) {
                        $row = $prototype->cloneNode(true);
                        $table->appendChild($row);
                        foreach ($xpath->query('./w:tc', $row) as $cell) {
                            if (!preg_match('/\$\{(?:nachname|vorname|klasse)\d+\}/', $cell->textContent)) continue;
                            // Word can split a placeholder across several differently formatted runs.
                            $replacement = preg_replace('/(nachname|vorname|klasse)\d+/', '${1}'.$number, $cell->textContent);
                            foreach ($xpath->query('.//w:t', $cell) as $index => $text) {
                                $text->nodeValue = $index === 0 ? $replacement : '';
                            }
                        }
                        $numberText = $xpath->query('./w:tc[1]//w:t', $row)->item(0);
                        if ($numberText) $numberText->nodeValue = (string) $number;
                    }
                }
                $zip->addFromString('word/document.xml', $document->saveXML());
            } finally {
                $zip->close();
            }
            return new TemplateProcessor($temporary);
        } finally {
            unlink($temporary);
        }
    }
}
