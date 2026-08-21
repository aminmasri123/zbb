<?php

namespace Tests\Unit\Services\Bop;

use App\Models\Gruppe;
use App\Models\Personen;
use App\Services\Bop\PotenzialanalyseReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use setasign\Fpdi\Fpdi;
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

        $service = new PotenzialanalyseReportService();
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

    public function test_group_export_renders_participants_separately_and_merges_the_pdf_pages(): void
    {
        $gruppe = new Gruppe(['id' => 10]);
        $gruppe->setRelation('bereich', null);
        $gruppe->setRelation('teilnehmer', collect([
            new Personen(['id' => 3, 'vorname' => 'Zoe', 'nachname' => 'Zander']),
            new Personen(['id' => 1, 'vorname' => 'Anna', 'nachname' => 'Albrecht']),
            new Personen(['id' => 2, 'vorname' => 'Berta', 'nachname' => 'Müller']),
        ]));

        $service = new class extends PotenzialanalyseReportService
        {
            public array $renderedPersonIds = [];

            public function renderPdf(Gruppe $gruppe, Personen $person): string
            {
                $this->renderedPersonIds[] = $person->id;

                return Pdf::loadHTML('<html><body>Teilnehmer '.(int) $person->id.'</body></html>')
                    ->setPaper('a4', 'portrait')
                    ->output();
            }
        };

        $result = $service->createGroupPdf($gruppe);

        try {
            $this->assertSame(3, $result['count']);
            $this->assertSame([1, 2, 3], $service->renderedPersonIds);
            $this->assertFileExists($result['path']);

            $merged = new Fpdi();
            $this->assertSame(3, $merged->setSourceFile($result['path']));
        } finally {
            File::delete($result['path']);
        }
    }
}
