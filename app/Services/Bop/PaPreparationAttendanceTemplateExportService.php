<?php

namespace App\Services\Bop;

use App\Models\Partner;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class PaPreparationAttendanceTemplateExportService
{
    public const TEMPLATE_PATH = 'vorlage/projekte/bop/excel/Anwesenheitsliste-Vorbereitung-BO-Tage.xlsx';

    public function create(
        Partner $school,
        Collection $participants,
        array $day,
        array $signatures,
        string $exportMode,
        ?string $className,
        string $paperFormat,
        string $outputPath,
        bool $forPdf = false,
    ): void {
        $template = storage_path(self::TEMPLATE_PATH);
        if (! is_file($template)) {
            throw new RuntimeException('Die Excel-Vorlage für die Anwesenheitsliste Vorbereitung PA wurde nicht gefunden.');
        }

        $workbook = IOFactory::load($template);
        $sheet = $workbook->getActiveSheet();
        $images = [];
        try {
            $classLabel = $exportMode === 'klasse' && filled($className)
                ? $className
                : $participants->pluck('klasse')->filter()->unique()->implode(', ');
            $specialNeeds = $participants->filter(fn ($p) => (bool) ($p->foerderschueler ?? $p->foederschueler ?? false))->count();
            $schoolForm = $participants->isNotEmpty() && $specialNeeds / $participants->count() > 0.5
                ? 'Förderschule' : 'Gemeinschaftsschule';

            $sheet->setCellValueExplicit('B2', (string) $school->name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B4', $schoolForm, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B5', $classLabel ?: 'alle', DataType::TYPE_STRING);
            $sheet->setCellValue('E6', Date::PHPToExcel(new \DateTimeImmutable($day['date'])));
            $sheet->getStyle('E6')->getNumberFormat()->setFormatCode('dd.mm.yyyy');

            foreach ($participants->values() as $index => $participant) {
                $row = 8 + $index;
                $sheet->duplicateStyle($sheet->getStyle('A8:E8'), 'A'.$row.':E'.$row);
                $nameLines = max(1, (int) ceil(mb_strlen((string) $participant->person?->nachname) / 17), (int) ceil(mb_strlen((string) $participant->person?->vorname) / 17));
                $sheet->getRowDimension($row)->setRowHeight(20.45 * $nameLines);
                $sheet->getStyle('B'.$row.':C'.$row)->getAlignment()->setWrapText(true);
                $sheet->getStyle('A'.$row.':E'.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->setCellValue('A'.$row, $index + 1);
                $sheet->setCellValueExplicit('B'.$row, (string) ($participant->person?->nachname ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C'.$row, (string) ($participant->person?->vorname ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D'.$row, (string) ($participant->person?->geschlecht ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValue('E'.$row, null);

                $signature = $this->signatureFor($day, (int) $participant->person_id, $signatures);
                if ($signature === null || ! preg_match('/^data:image\/png;base64,(.+)$/s', $signature, $match)) {
                    continue;
                }
                $bytes = base64_decode($match[1], true);
                $image = $bytes !== false ? @imagecreatefromstring($bytes) : false;
                if ($image === false) {
                    continue;
                }
                $images[] = $image;
                $drawing = new MemoryDrawing;
                $drawing->setName('Unterschrift '.$participant->person_id);
                $drawing->setImageResource($image);
                $drawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
                $drawing->setMimeType(MemoryDrawing::MIMETYPE_PNG);
                $drawing->setCoordinates('E'.$row);
                $drawing->setOffsetX(4)->setOffsetY(2);
                $drawing->setHeight(22);
                if ($drawing->getWidth() > 180) {
                    $drawing->setWidth(180);
                }
                $drawing->setWorksheet($sheet);
            }

            // Retain the template's heading, column styles and footer graphic. Limit printing
            // to the populated list and repeat the original heading on continuation pages.
            $sheet->getPageSetup()
                ->setPaperSize($paperFormat === 'A3' ? PageSetup::PAPERSIZE_A3 : PageSetup::PAPERSIZE_A4)
                ->setFitToWidth(1)->setFitToHeight(0)
                ->setRowsToRepeatAtTopByStartAndEnd(1, 7)
                ->setPrintArea('A1:E'.max(8, 7 + $participants->count()));

            if ($forPdf) {
                // Calc does not reliably render Excel footer images. The PDF receives the
                // original template graphic separately, without a second printed copy.
                $footer = $sheet->getHeaderFooter();
                $footer->setOddFooter(str_replace('&G', '', $footer->getOddFooter()));
                $footer->setEvenFooter(str_replace('&G', '', $footer->getEvenFooter()));
                $footer->setFirstFooter(str_replace('&G', '', $footer->getFirstFooter()));
            }

            File::ensureDirectoryExists(dirname($outputPath));
            (new Xlsx($workbook))->save($outputPath);
        } finally {
            $workbook->disconnectWorksheets();
            foreach ($images as $image) {
                imagedestroy($image);
            }
        }
    }

    public function applyPdfFooter(string $pdfPath, bool $addPageNumbers = false): void
    {
        $workbook = IOFactory::load(storage_path(self::TEMPLATE_PATH));
        $sheet = $workbook->getActiveSheet();
        $footerImage = $sheet->getHeaderFooter()->getImages()['LF'] ?? null;
        if ($footerImage === null) {
            $workbook->disconnectWorksheets();
            return;
        }
        $imagePath = $pdfPath.'.footer.png';
        $renderedPath = $pdfPath.'.rendered.pdf';
        try {
            File::put($imagePath, file_get_contents($footerImage->getPath()));
            $pdf = new Fpdi;
            $pdf->SetAutoPageBreak(false);
            $pageCount = $pdf->setSourceFile($pdfPath);
            for ($page = 1; $page <= $pageCount; $page++) {
                $templateId = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($templateId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
                if ($addPageNumbers) {
                    $pdf->SetFont('Helvetica', '', 8);
                    $pdf->SetXY($size['width'] - 50, 5);
                    $pdf->Cell(30, 5, 'Seite '.$page.' von '.$pageCount, 0, 0, 'R');
                }
                $left = $sheet->getPageMargins()->getLeft() * 25.4;
                $right = $sheet->getPageMargins()->getRight() * 25.4;
                $width = min($footerImage->getWidth() * 25.4 / 96, $size['width'] - $left - $right);
                if ($addPageNumbers) {
                    $left = ($size['width'] - $width) / 2;
                }
                $height = $width * $footerImage->getHeight() / max(1, $footerImage->getWidth());
                $bottom = max(3, $sheet->getPageMargins()->getFooter() * 25.4);
                $pdf->Image($imagePath, $left, $size['height'] - $bottom - $height, $width, $height, 'PNG');
            }
            $pdf->Output('F', $renderedPath);
            File::move($renderedPath, $pdfPath);
        } finally {
            File::delete([$imagePath, $renderedPath]);
            $workbook->disconnectWorksheets();
        }
    }

    public function createPdf(string $xlsxPath): string
    {
        $pdfPath = substr($xlsxPath, 0, -5).'.pdf';
        $workbook = IOFactory::load($xlsxPath);
        try {
            // Read content from the filled Excel template, but use explicit print layout
            // instead of inheriting spreadsheet column widths and overflow behaviour.
            $sheet = $workbook->getActiveSheet();
            $signatures = [];
            foreach ($sheet->getDrawingCollection() as $drawing) {
                if ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\Drawing) {
                    $signatures[$drawing->getCoordinates()] = 'data:image/png;base64,'.base64_encode(file_get_contents($drawing->getPath()));
                }
            }
            $lastRow = Coordinate::rangeBoundaries($sheet->getPageSetup()->getPrintArea())[1][1];
            $rows = [];
            for ($row = 8; $row <= $lastRow; $row++) {
                $rows[] = [
                    'number' => $sheet->getCell('A'.$row)->getValue(),
                    'lastName' => $sheet->getCell('B'.$row)->getValue(),
                    'firstName' => $sheet->getCell('C'.$row)->getValue(),
                    'gender' => $sheet->getCell('D'.$row)->getValue(),
                    'signature' => $signatures['E'.$row] ?? null,
                ];
            }
            $paper = $sheet->getPageSetup()->getPaperSize() === PageSetup::PAPERSIZE_A3 ? 'A3' : 'A4';
            $pdf = new Dompdf(new Options(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]));
            $pdf->setPaper($paper, 'landscape');
            $pdf->loadHtml(view('pdf.bop.pa-preparation-attendance', [
                'title' => $sheet->getCell('A1')->getValue(),
                'school' => $sheet->getCell('B2')->getValue(),
                'schoolForm' => $sheet->getCell('B4')->getValue(),
                'classes' => $sheet->getCell('B5')->getValue(),
                'date' => $sheet->getCell('E6')->getFormattedValue(),
                'paper' => $paper,
                'rows' => $rows,
            ])->render());
            $pdf->render();
            File::put($pdfPath, $pdf->output());
            $this->applyPdfFooter($pdfPath, true);
            return $pdfPath;
        } catch (\Throwable $exception) {
            File::delete($pdfPath);
            throw $exception;
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    private function signatureFor(array $day, int $personId, array $signatures): ?string
    {
        $key = ($day['id'] ?? '').':'.$personId;
        // An explicitly cleared signature must stay empty.
        if (array_key_exists($key, $signatures)) {
            return is_string($signatures[$key]) ? $signatures[$key] : null;
        }
        $suffix = ':'.$personId;
        foreach ($signatures as $storedKey => $signature) {
            if (! is_string($storedKey) || ! is_string($signature) || ! str_ends_with($storedKey, $suffix)) {
                continue;
            }
            $dayId = substr($storedKey, 0, -strlen($suffix));
            if (! empty($day['date']) && str_contains($dayId, $day['date'])
                && (str_contains($dayId, 'vorbereitung') || str_contains($dayId, 'preparation'))) {
                return $signature;
            }
        }

        return null;
    }
}
