<?php

namespace App\Services\Bop;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Throwable;

class RolandEvaluationPdfService
{
    public function create(Collection $participants): string
    {
        $temporaryRoot = storage_path('app/tmp');
        $identifier = Str::uuid()->toString();
        $outputPath = $temporaryRoot . DIRECTORY_SEPARATOR . 'roland-evaluation-' . $identifier . '.pdf';
        // Versionierter Dateiname verhindert, dass ein Server nach einem Deployment
        // weiterhin eine alte, bereits gecachte PDF-Vorlage verwendet.
        $templatePath = resource_path('pdf/auswertungsbogen-pa-roland-template-v3.pdf');

        File::ensureDirectoryExists($temporaryRoot);

        try {
            if ($participants->isEmpty()) {
                throw new RuntimeException('Es wurden keine PDF-Seiten erzeugt.');
            }

            if (!File::exists($templatePath)) {
                throw new RuntimeException('Die Roland-PDF-Vorlage wurde nicht gefunden.');
            }

            $pdf = new Fpdi('L', 'mm', 'A4');
            $pdf->SetAutoPageBreak(false);

            if ($pdf->setSourceFile($templatePath) !== 1) {
                throw new RuntimeException('Die Roland-PDF-Vorlage muss genau eine Seite enthalten.');
            }

            $template = $pdf->importPage(1);
            $size = $pdf->getTemplateSize($template);

            foreach ($participants as $participant) {
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
                $this->writeParticipantData($pdf, (array) $participant);
            }

            $pdf->Output('F', $outputPath);

            if (!File::exists($outputPath) || File::size($outputPath) === 0) {
                throw new RuntimeException('Die erzeugte PDF-Datei ist leer.');
            }

            return $outputPath;
        } catch (Throwable $exception) {
            File::delete($outputPath);

            throw $exception;
        }
    }

    private function writeParticipantData(Fpdi $pdf, array $participant): void
    {
        $pdf->SetTextColor(17, 17, 17);

        $this->writeFittedText($pdf, 23.2, 16.5, 112.5, (string) ($participant['name'] ?? ''));
        $this->writeFittedText($pdf, 149.0, 16.5, 52.5, (string) ($participant['geburtsdatum'] ?? ''));
        $this->writeFittedText($pdf, 223.0, 16.5, 61.5, (string) ($participant['geschlecht'] ?? ''));
        $this->writeFittedText($pdf, 23.2, 23.5, 112.5, (string) ($participant['schule'] ?? ''));
        $this->writeFittedText($pdf, 151.0, 23.5, 28.0, (string) ($participant['klasse'] ?? ''));
    }

    private function writeFittedText(Fpdi $pdf, float $x, float $y, float $width, string $text): void
    {
        $encodedText = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', trim($text));
        $encodedText = $encodedText === false ? '' : $encodedText;
        $fontSize = 8.6;

        do {
            $pdf->SetFont('Helvetica', '', $fontSize);
            $fontSize -= 0.2;
        } while ($fontSize >= 6.2 && $pdf->GetStringWidth($encodedText) > $width);

        if ($pdf->GetStringWidth($encodedText) > $width) {
            while ($encodedText !== '' && $pdf->GetStringWidth($encodedText . '...') > $width) {
                $encodedText = substr($encodedText, 0, -1);
            }
            $encodedText .= '...';
        }

        $pdf->SetXY($x, $y);
        $pdf->Cell($width, 4.8, $encodedText, 0, 0, 'L');
    }
}
