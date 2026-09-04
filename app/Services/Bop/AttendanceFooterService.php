<?php

namespace App\Services\Bop;

use DOMDocument;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\HeaderFooter;
use PhpOffice\PhpSpreadsheet\Worksheet\HeaderFooterDrawing;
use RuntimeException;
use ZipArchive;

class AttendanceFooterService
{
    private const IMAGE_PATH = 'img/bop/kooperationspartner.png';
    private const WORD_IMAGE_ENTRY = 'word/media/bop-attendance-footer.png';
    private const SPREADSHEET_IMAGE_WIDTH = 600;
    private const SPREADSHEET_BOTTOM_MARGIN = 1.15;

    public function applyToSpreadsheet(Spreadsheet $spreadsheet): void
    {
        $imagePath = $this->imagePath();

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $footer = $sheet->getHeaderFooter();
            $footer->setOddFooter('&C&G');
            $footer->setEvenFooter('&C&G');
            $footer->setFirstFooter('&C&G');

            foreach ([
                HeaderFooter::IMAGE_FOOTER_CENTER,
                HeaderFooter::IMAGE_FOOTER_CENTER_EVEN,
                HeaderFooter::IMAGE_FOOTER_CENTER_FIRST,
            ] as $location) {
                $drawing = new HeaderFooterDrawing();
                $drawing->setName('BOP Kooperationspartner');
                $drawing->setPath($imagePath);
                $drawing->setWidth(self::SPREADSHEET_IMAGE_WIDTH);
                $footer->addImage($drawing, $location);
            }

            $margins = $sheet->getPageMargins();
            $margins->setFooter(0.1);
            if ($margins->getBottom() < self::SPREADSHEET_BOTTOM_MARGIN) {
                $margins->setBottom(self::SPREADSHEET_BOTTOM_MARGIN);
            }
        }
    }

    public function applyToWordDocument(string $documentPath): void
    {
        $zip = new ZipArchive();
        $openResult = $zip->open($documentPath);

        if ($openResult !== true) {
            throw new RuntimeException('Die Word-Anwesenheitsliste konnte nicht fuer den Footer geoeffnet werden.');
        }

        try {
            $relationshipEntries = [];
            $footerEntries = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entryName = $zip->getNameIndex($index);
                if ($entryName && preg_match('~^word/_rels/footer\d+\.xml\.rels$~', $entryName)) {
                    $relationshipEntries[] = $entryName;
                }
                if ($entryName && preg_match('~^word/footer\d+\.xml$~', $entryName)) {
                    $footerEntries[] = $entryName;
                }
            }

            $footerImageFound = false;
            $footerEntriesWithImage = [];
            $sourceFooterEntry = null;

            foreach ($relationshipEntries as $entryName) {
                $xml = $zip->getFromName($entryName);
                if ($xml === false) {
                    continue;
                }

                $document = new DOMDocument();
                $document->preserveWhiteSpace = true;
                if (!$document->loadXML($xml)) {
                    continue;
                }

                $changed = false;
                foreach ($document->getElementsByTagNameNS('*', 'Relationship') as $relationship) {
                    if (!str_ends_with($relationship->getAttribute('Type'), '/image')) {
                        continue;
                    }

                    $relationship->setAttribute('Target', 'media/bop-attendance-footer.png');
                    $footerImageFound = true;
                    $changed = true;
                }

                if ($changed) {
                    $relationshipsXml = $document->saveXML();
                    $zip->addFromString($entryName, $relationshipsXml);

                    $footerEntry = 'word/' . preg_replace(
                        '~\.xml\.rels$~',
                        '.xml',
                        basename($entryName)
                    );
                    $footerEntriesWithImage[$footerEntry] = true;

                    if ($sourceFooterEntry === null) {
                        $sourceFooterEntry = $footerEntry;
                    }
                }
            }

            if (!$footerImageFound) {
                throw new RuntimeException('Die Word-Vorlage enthaelt keinen Bildplatzhalter im Footer.');
            }

            if ($sourceFooterEntry !== null) {
                $this->useImageFooterForEmptyReferences(
                    $zip,
                    $sourceFooterEntry,
                    $footerEntries,
                    $footerEntriesWithImage
                );
            }

            $this->ensurePngContentType($zip);
            $zip->addFile($this->imagePath(), self::WORD_IMAGE_ENTRY);
        } finally {
            $zip->close();
        }
    }

    public function imagePath(): string
    {
        $path = public_path(self::IMAGE_PATH);

        if (!is_file($path)) {
            throw new RuntimeException('Die Grafik der BOP-Kooperationspartner wurde nicht gefunden.');
        }

        return $path;
    }

    private function ensurePngContentType(ZipArchive $zip): void
    {
        $entryName = '[Content_Types].xml';
        $xml = $zip->getFromName($entryName);

        if ($xml === false) {
            throw new RuntimeException('Die Word-Datei enthaelt keine Content-Type-Definitionen.');
        }

        $document = new DOMDocument();
        $document->preserveWhiteSpace = true;
        if (!$document->loadXML($xml)) {
            throw new RuntimeException('Die Content-Type-Definitionen der Word-Datei sind ungueltig.');
        }

        foreach ($document->getElementsByTagNameNS('*', 'Default') as $default) {
            if (strtolower($default->getAttribute('Extension')) === 'png') {
                return;
            }
        }

        $default = $document->createElementNS(
            'http://schemas.openxmlformats.org/package/2006/content-types',
            'Default'
        );
        $default->setAttribute('Extension', 'png');
        $default->setAttribute('ContentType', 'image/png');
        $document->documentElement->appendChild($default);

        $zip->addFromString($entryName, $document->saveXML());
    }

    private function useImageFooterForEmptyReferences(
        ZipArchive $zip,
        string $sourceFooterEntry,
        array $footerEntries,
        array $footerEntriesWithImage
    ): void
    {
        $relationshipsEntry = 'word/_rels/document.xml.rels';
        $documentEntry = 'word/document.xml';
        $relationshipsXml = $zip->getFromName($relationshipsEntry);
        $documentXml = $zip->getFromName($documentEntry);

        if ($relationshipsXml === false || $documentXml === false) {
            return;
        }

        $emptyFooterEntries = [];
        foreach ($footerEntries as $footerEntry) {
            if (isset($footerEntriesWithImage[$footerEntry])) {
                continue;
            }

            $footerXml = $zip->getFromName($footerEntry);
            if ($footerXml === false) {
                continue;
            }

            $footer = new DOMDocument();
            if (!$footer->loadXML($footerXml)) {
                continue;
            }

            if (
                trim($footer->textContent) === ''
                && $footer->getElementsByTagNameNS('*', 'drawing')->length === 0
                && $footer->getElementsByTagNameNS('*', 'pict')->length === 0
            ) {
                $emptyFooterEntries[$footerEntry] = true;
            }
        }

        if ($emptyFooterEntries === []) {
            return;
        }

        $relationships = new DOMDocument();
        $relationships->preserveWhiteSpace = true;
        if (!$relationships->loadXML($relationshipsXml)) {
            return;
        }

        $sourceRelationshipId = null;
        $emptyRelationshipIds = [];

        foreach ($relationships->getElementsByTagNameNS('*', 'Relationship') as $relationship) {
            if (!str_ends_with($relationship->getAttribute('Type'), '/footer')) {
                continue;
            }

            $footerEntry = 'word/' . ltrim($relationship->getAttribute('Target'), '/');
            if ($footerEntry === $sourceFooterEntry) {
                $sourceRelationshipId = $relationship->getAttribute('Id');
            } elseif (isset($emptyFooterEntries[$footerEntry])) {
                $emptyRelationshipIds[] = $relationship->getAttribute('Id');
            }
        }

        if ($sourceRelationshipId === null || $emptyRelationshipIds === []) {
            return;
        }

        $document = new DOMDocument();
        $document->preserveWhiteSpace = true;
        if (!$document->loadXML($documentXml)) {
            return;
        }

        $relationshipNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        foreach ($document->getElementsByTagNameNS('*', 'footerReference') as $footerReference) {
            $relationshipId = $footerReference->getAttributeNS($relationshipNamespace, 'id');
            if (in_array($relationshipId, $emptyRelationshipIds, true)) {
                $footerReference->setAttributeNS($relationshipNamespace, 'r:id', $sourceRelationshipId);
            }
        }

        $zip->addFromString($documentEntry, $document->saveXML());
    }
}
