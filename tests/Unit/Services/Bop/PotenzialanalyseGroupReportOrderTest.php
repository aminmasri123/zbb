<?php

namespace Tests\Unit\Services\Bop;

use App\Models\Gruppe;
use App\Models\Personen;
use App\Services\Bop\PotenzialanalyseReportService;
use App\Services\Documents\HtmlPdfDocumentCombiner;
use Tests\TestCase;

class PotenzialanalyseGroupReportOrderTest extends TestCase
{
    public function test_group_participants_are_sorted_by_last_name_then_first_name(): void
    {
        $gruppe = new Gruppe(['id' => 10]);
        $gruppe->setRelation('teilnehmer', collect([
            new Personen(['id' => 3, 'vorname' => 'Zoe', 'nachname' => 'Zander']),
            new Personen(['id' => 2, 'vorname' => 'Berta', 'nachname' => 'Müller']),
            new Personen(['id' => 4, 'vorname' => 'Adam', 'nachname' => 'Müller']),
            new Personen(['id' => 1, 'vorname' => 'Anna', 'nachname' => 'Albrecht']),
        ]));

        $service = new PotenzialanalyseReportService(new HtmlPdfDocumentCombiner());
        $orderedNames = $service->orderedParticipants($gruppe)
            ->map(fn (Personen $person) => "{$person->nachname}, {$person->vorname}")
            ->all();

        $this->assertSame([
            'Albrecht, Anna',
            'Müller, Adam',
            'Müller, Berta',
            'Zander, Zoe',
        ], $orderedNames);
    }
}
