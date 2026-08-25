<?php

namespace App\Services\Documents;

use RuntimeException;
use setasign\Fpdi\Fpdi;
use Throwable;

class PdfMerger
{
    public function merge(array $sourceFiles, string $outputFile): string
    {
        if ($sourceFiles === []) {
            throw new RuntimeException('Es wurden keine PDF-Dateien zum Zusammenführen übergeben.');
        }

        try {
            $pdf = new Fpdi;
            $pdf->SetAutoPageBreak(false);

            foreach ($sourceFiles as $sourceFile) {
                if (! is_file($sourceFile)) {
                    throw new RuntimeException('Eine PDF-Datei des Pakets wurde nicht gefunden.');
                }

                $pageCount = $pdf->setSourceFile($sourceFile);
                for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                    $templateId = $pdf->importPage($pageNumber);
                    $size = $pdf->getTemplateSize($templateId);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            }

            $pdf->Output('F', $outputFile);
        } catch (Throwable $exception) {
            if (is_file($outputFile)) {
                @unlink($outputFile);
            }

            throw new RuntimeException(
                'Die PDFs konnten nicht zu einer Datei verbunden werden. Bitte verwenden Sie den ZIP-Export. '.$exception->getMessage(),
                0,
                $exception
            );
        }

        return $outputFile;
    }
}
