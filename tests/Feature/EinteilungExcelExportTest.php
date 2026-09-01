<?php

namespace Tests\Feature;

use App\Models\Bereich;
use App\Models\EinteilungBereiche;
use App\Models\EinteilungSetting;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class EinteilungExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_rounds_are_exported_as_matrix_with_logos_and_without_total_student_count(): void
    {
        [$user, $partner] = $this->exportContext();

        $response = $this->actingAs($user)->post(route('einteilung.export.excel'), [
            'partner_id' => $partner->id,
            'schuljahr' => '2026/2027',
            'teil' => 'Teil 1',
            'runde' => 'alle',
        ])->assertOk();

        $this->assertStringContainsString('Alle_Runden.xlsx', (string) $response->headers->get('content-disposition'));
        $workbook = IOFactory::load($response->getFile()->getPathname());
        $sheet = $workbook->getActiveSheet();

        $this->assertSame('RUNDE', $sheet->getCell('A3')->getValue());
        $this->assertSame('HOLZTECHNIK', $sheet->getCell('B3')->getValue());
        $this->assertStringNotContainsString('Kapazität', (string) $sheet->getCell('B3')->getValue());
        $this->assertStringContainsString('Runde 1', (string) $sheet->getCell('A4')->getValue());
        $this->assertStringContainsString('Runde 2', (string) $sheet->getCell('A5')->getValue());
        $this->assertStringNotContainsString('TN', (string) $sheet->getCell('A4')->getValue());
        $this->assertStringContainsString('Mustermann, Mia', (string) $sheet->getCell('B4')->getValue());
        $this->assertStringNotContainsString('1 / 15', (string) $sheet->getCell('B4')->getValue());
        $this->assertCount(5, $sheet->getDrawingCollection());
        $this->assertSame(
            ['ZBB', 'Berufsorientierung', 'Ministerium Saarland', 'Bundesministerium', 'BIBB'],
            collect($sheet->getDrawingCollection())->map->getName()->values()->all()
        );

    }

    public function test_a_single_round_can_be_exported(): void
    {
        [$user, $partner] = $this->exportContext();

        $response = $this->actingAs($user)->post(route('einteilung.export.excel'), [
            'partner_id' => $partner->id,
            'schuljahr' => '2026/2027',
            'teil' => 'Teil 1',
            'runde' => 2,
        ])->assertOk();

        $this->assertStringContainsString('Runde_2.xlsx', (string) $response->headers->get('content-disposition'));
        $sheet = IOFactory::load($response->getFile()->getPathname())->getActiveSheet();

        $this->assertStringContainsString('Runde 2', (string) $sheet->getCell('A4')->getValue());
        $this->assertSame('', (string) $sheet->getCell('A5')->getValue());
        $this->assertGreaterThan(4, $sheet->getHighestDataRow());
        $this->assertStringNotContainsString('Runde 1', implode(' ', $sheet->toArray()[3]));
    }

    private function exportContext(): array
    {
        $user = User::factory()->create();
        $projekt = Projekt::factory()->create(['name' => 'BOP']);
        $partner = Partner::query()->create(['name' => 'Testschule']);
        $user->projekte()->attach($projekt->id);
        $user->update(['current_team_id' => $projekt->id]);
        $projekt->partners()->attach($partner->id);
        $this->grantTestPermission($user, 'einteilung.export');

        $holz = Bereich::query()->create(['name' => 'Holztechnik']);
        $metall = Bereich::query()->create(['name' => 'Metalltechnik']);
        $projekt->bereiche()->attach([$holz->id, $metall->id]);

        $person = Personen::factory()->create([
            'typ' => 'teilnehmer',
            'vorname' => 'Mia',
            'nachname' => 'Mustermann',
        ]);
        $student = PersonenIstSchueler::query()->create([
            'person_id' => $person->id,
            'klasse' => '7.1',
            'schuljahr' => '2026/2027',
            'teil' => 'Teil 1',
            'schule_id' => $partner->id,
        ]);
        $projekt->teilnehmer()->attach($person->id);
        foreach ([[1, $holz->id], [2, $metall->id]] as [$round, $areaId]) {
            EinteilungBereiche::query()->create([
                'teilnehmende_id' => $student->id,
                'teilnehmende_type' => PersonenIstSchueler::class,
                'bereich_id' => $areaId,
                'runde' => $round,
            ]);
        }

        $setting = EinteilungSetting::query()->create([
            'projekt_id' => $projekt->id,
            'partner_id' => $partner->id,
            'schuljahr' => '2026/2027',
            'teil' => 'Teil 1',
            'runden_anzahl' => 2,
            'standard_kapazitaet' => 15,
            'user_create' => $user->id,
        ]);
        $setting->rundentermine()->createMany([
            ['runde' => 1, 'anfangsdatum' => '2026-09-01', 'enddatum' => '2026-09-03', 'startzeit' => '08:00', 'endzeit' => '15:00'],
            ['runde' => 2, 'anfangsdatum' => '2026-09-04', 'enddatum' => '2026-09-08', 'startzeit' => '08:00', 'endzeit' => '15:00'],
        ]);

        return [$user, $partner];
    }
}
