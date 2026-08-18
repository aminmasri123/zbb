<?php

namespace App\Services\Bop;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Throwable;

class RolandEvaluationPdfService
{
    private const PARTICIPANTS_PER_CHUNK = 5;

    public function create(Collection $participants, array $viewData): string
    {
        $temporaryRoot = storage_path('app/tmp');
        $identifier = Str::uuid()->toString();
        $workDirectory = $temporaryRoot . DIRECTORY_SEPARATOR . 'roland-evaluation-' . $identifier;
        $outputPath = $temporaryRoot . DIRECTORY_SEPARATOR . 'roland-evaluation-' . $identifier . '.pdf';

        File::ensureDirectoryExists($workDirectory);

        try {
            $chunkPaths = $this->renderChunks($participants, $viewData, $workDirectory);
            $this->mergeChunks($chunkPaths, $outputPath);

            return $outputPath;
        } catch (Throwable $exception) {
            File::delete($outputPath);

            throw $exception;
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    private function renderChunks(Collection $participants, array $viewData, string $workDirectory): array
    {
        $chunkPaths = [];

        foreach ($participants->chunk(self::PARTICIPANTS_PER_CHUNK)->values() as $index => $chunk) {
            $pdf = Pdf::loadView('pdf.auswertungsbogenPA-roland', [
                ...$viewData,
                'teilnehmer' => $chunk->values(),
            ])->setPaper('A4', 'landscape');

            $chunkPath = $workDirectory . DIRECTORY_SEPARATOR . sprintf('chunk-%03d.pdf', $index + 1);
            $contents = $pdf->output();

            if (File::put($chunkPath, $contents) === false) {
                throw new RuntimeException('Ein PDF-Teilstueck konnte nicht gespeichert werden.');
            }

            $chunkPaths[] = $chunkPath;
            unset($contents, $pdf);
            gc_collect_cycles();
        }

        if ($chunkPaths === []) {
            throw new RuntimeException('Es wurden keine PDF-Seiten erzeugt.');
        }

        return $chunkPaths;
    }

    private function mergeChunks(array $chunkPaths, string $outputPath): void
    {
        $mergedPdf = new Fpdi('L', 'mm', 'A4');
        $mergedPdf->SetAutoPageBreak(false);

        foreach ($chunkPaths as $chunkPath) {
            $pageCount = $mergedPdf->setSourceFile($chunkPath);

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $template = $mergedPdf->importPage($pageNumber);
                $size = $mergedPdf->getTemplateSize($template);

                $mergedPdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $mergedPdf->useTemplate($template);
            }
        }

        $mergedPdf->Output('F', $outputPath);

        if (!File::exists($outputPath) || File::size($outputPath) === 0) {
            throw new RuntimeException('Die zusammengefuegte PDF-Datei ist leer.');
        }
    }
}
