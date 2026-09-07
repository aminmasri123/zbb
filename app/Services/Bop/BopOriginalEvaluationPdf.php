<?php

namespace App\Services\Bop;

use App\Services\BerufsorientierungAuswertungService;
use Illuminate\Support\Collection;
use setasign\Fpdi\Fpdi;

class BopOriginalEvaluationPdf
{
    public function render(Collection $entries): string
    {
        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(false);
        $pdf->setSourceFile(resource_path('documents/bop/Auswertungsbogen_BOP.pdf'));
        $page = $pdf->importPage(1);
        // Coordinates in millimetres in the original A4 form. Scores run 5 -> 1.
        $rowY = [52.5, 73.8, 93.8, 113.7, 133.8, 153.8, 173.9, 193.9, 213.9, 233.9, 266.1];
        foreach ($entries as $entry) {
            $pdf->AddPage('P', 'A4');
            $pdf->useTemplate($page, 0, 0, 210, 297);
            $this->field($pdf, 24.2, 30.4, 44.5, $entry['vorname']);
            $this->field($pdf, 71.5, 30.4, 44.5, $entry['nachname']);
            $this->field($pdf, 119, 30.4, 44.5, $entry['anleiter_name']);
            $this->field($pdf, 165.3, 30.4, 26, $entry['datum']);
            $this->field($pdf, 36.4, 40.1, 14.5, $entry['klasse']);
            $this->field($pdf, 71.5, 40.1, 44.5, $entry['schule_name']);
            $this->field($pdf, 141, 40.1, 50.2, $entry['bereich_name']);
            $pdf->SetFont('Helvetica', 'B', 12);
            foreach (BerufsorientierungAuswertungService::BOP_CRITERIA as $index => $criterion) {
                $score = (int) ($entry['ratings']->get($criterion['key'])?->bewertung ?? 0);
                if ($score < 1 || $score > 5) continue;
                $x = ($index === 10 ? 127 : 127.5) + (5 - $score) * 12;
                $pdf->SetXY($x, $rowY[$index]);
                $pdf->Cell(5, 5, 'X', 0, 0, 'C');
            }
        }
        return $pdf->Output('S');
    }

    public function download(Collection $entries, string $filename)
    {
        return response($this->render($entries), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function field(Fpdi $pdf, float $x, float $y, float $width, string $value): void
    {
        $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', preg_replace('/\s+/u', ' ', trim($value)));
        $size = 11;
        do {
            $pdf->SetFont('Helvetica', '', $size);
            if ($pdf->GetStringWidth($value) <= $width - 3) break;
            $size -= 0.5;
        } while ($size >= 7);
        if ($pdf->GetStringWidth($value) > $width - 3) {
            while ($value !== '' && $pdf->GetStringWidth($value.'...') > $width - 3) $value = substr($value, 0, -1);
            $value .= '...';
        }
        $pdf->SetXY($x + 1, $y);
        $pdf->Cell($width - 2, 8.1, $value);
    }
}
