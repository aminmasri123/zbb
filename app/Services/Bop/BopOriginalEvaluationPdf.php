<?php

namespace App\Services\Bop;

use App\Services\BerufsorientierungAuswertungService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class BopOriginalEvaluationPdf
{
    public function render(Collection $entries): string
    {
        $limit = trim((string) ini_get('memory_limit'));
        $bytes = (float) $limit * match (strtolower(substr($limit, -1))) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
        };
        if ($limit !== '-1' && $bytes < 512 * 1024 ** 2) ini_set('memory_limit', '512M');
        @set_time_limit(300);
        // Keep the HTML renderer bounded for whole-school exports. Every batch
        // still uses the same Blade, and PDF pages are joined without reflow.
        if ($entries->count() > 10) {
            $merged = new Fpdi;
            foreach ($entries->chunk(10) as $batch) {
                $count = $merged->setSourceFile(StreamReader::createByString($this->renderBatch($batch)));
                for ($page = 1; $page <= $count; $page++) {
                    $template = $merged->importPage($page);
                    $size = $merged->getTemplateSize($template);
                    $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $merged->useTemplate($template);
                }
                gc_collect_cycles();
            }
            return $merged->Output('S');
        }
        return $this->renderBatch($entries);
    }

    private function renderBatch(Collection $entries): string
    {
        $people = $entries->map(function (array $entry) {
            $person = (object) $entry;
            $person->enddatum = $entry['datum'];
            foreach (BerufsorientierungAuswertungService::BOP_CRITERIA as $criterion) {
                $person->{$criterion['key']} = $entry['ratings']->get($criterion['key'])?->bewertung;
            }
            return $person;
        })->values();
        return Pdf::loadView('pdf.bop-bo-evaluation', ['alle_teilnehmer' => $people])
            ->setPaper('a4', 'portrait')->output();
    }

    public function download(Collection $entries, string $filename)
    {
        return response($this->render($entries), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

}
