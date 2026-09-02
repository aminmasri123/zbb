<?php

namespace App\Services\Documents;

use App\Models\ProjektHasTeilnehmerLuv;
use Barryvdh\DomPDF\Facade\Pdf;

final class LuvPdfService
{
    public function render(ProjektHasTeilnehmerLuv $luv): string
    {
        $luv->loadMissing([
            'projektHasTeilnehmer.projekt',
            'projektHasTeilnehmer.teilnehmer.sozialedaten',
            'projektHasTeilnehmer.meta.betreuer',
            'projektHasTeilnehmer.meta.projektbegleiter',
            'template',
            'creator',
            'reviewer',
            'approver',
        ]);

        return Pdf::loadView('pdf.luv-report', [
            'luv' => $luv,
            'participation' => $luv->projektHasTeilnehmer,
            'participant' => $luv->projektHasTeilnehmer?->teilnehmer,
            'project' => $luv->projektHasTeilnehmer?->projekt,
            'sections' => collect(data_get($luv->payload, 'sections', [])),
        ])->setPaper('a4')->output();
    }
}
