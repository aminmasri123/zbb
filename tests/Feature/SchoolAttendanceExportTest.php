<?php

namespace Tests\Feature;

use App\Models\Anwesenheitsstatuten;
use App\Models\Bereich;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\Tage;
use App\Models\User;
use App\Models\Zeiten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SchoolAttendanceExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_export_uses_saved_group_attendance_and_calculates_delay(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $partner = Partner::query()->create(['name' => 'Gesamtschule Test']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $project->partners()->attach($partner->id);
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');

        $participant = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Mia', 'nachname' => 'Muster']);
        PersonenIstSchueler::query()->create([
            'person_id' => $participant->id,
            'schule_id' => $partner->id,
            'schuljahr' => '2026/2027',
            'teil' => 'Teil 1',
            'klasse' => '7.1',
        ]);
        $project->teilnehmer()->attach($participant->id);
        $staff = Personen::factory()->create(['typ' => 'mitarbeiter']);
        $area = Bereich::query()->create(['name' => 'Holztechnik']);
        $location = Standort::factory()->create();
        $room = Raeume::query()->create(['name' => 'Werkstatt', 'standort_id' => $location->id, 'typ' => 'Werkstatt']);
        $group = Gruppe::query()->create([
            'personen_id' => $staff->id,
            'bereich_id' => $area->id,
            'projekt_id' => $project->id,
            'raum_id' => $room->id,
            'standort_id' => $location->id,
            'anfangsdatum' => '2026-09-01',
            'enddatum' => '2026-09-03',
        ]);
        $day = Tage::query()->create(['datum' => '2026-09-01', 'wochentag' => 'Dienstag']);
        $planned = Zeiten::query()->create(['startzeit' => '08:00', 'endzeit' => '14:00']);
        $actual = Zeiten::query()->create(['startzeit' => '08:15', 'endzeit' => '14:00']);
        $status = Anwesenheitsstatuten::query()->create(['status' => 'anwesend', 'abkuerzung' => 'A', 'farben' => '#22c55e']);
        GruppeHasPersonen::query()->create([
            'personen_id' => $participant->id,
            'user_id' => $user->id,
            'gruppe_id' => $group->id,
            'tage_id' => $day->id,
            'zeitgeplant_id' => $planned->id,
            'zeittatsaechlich_id' => $actual->id,
            'anwesenheitsstatuten_id' => $status->id,
            'bemerkung' => 'Bus verspätet',
        ]);

        $response = $this->actingAs($user)->post(route('export.schulanwesenheit.excel', [
            'schulId' => $partner->id,
            'schuljahr' => '2026-2027',
            'teil' => 'Teil 1',
        ]), ['von' => '2026-09-01', 'bis' => '2026-09-01'])->assertOk();

        $sheet = IOFactory::load($response->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('Muster', $sheet->getCell('C5')->getValue());
        $this->assertSame('anwesend', $sheet->getCell('F5')->getValue());
        $this->assertSame('08:00', $sheet->getCell('G5')->getValue());
        $this->assertSame('08:15', $sheet->getCell('H5')->getValue());
        $this->assertSame('15 Min.', $sheet->getCell('I5')->getValue());
        $this->assertSame('Bus verspätet', $sheet->getCell('J5')->getValue());
        $this->assertSame(
            ['ZBB', 'Berufsorientierung', 'Ministerium Saarland', 'Bundesministerium', 'BIBB'],
            collect($sheet->getDrawingCollection())->map->getName()->values()->all()
        );
        $this->assertSame('A1:J7', $sheet->getPageSetup()->getPrintArea());

    }

    public function test_school_export_rejects_an_invalid_period(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create();
        $partner = Partner::query()->create(['name' => 'Schule']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $project->partners()->attach($partner->id);
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');

        $this->actingAs($user)->post(route('export.schulanwesenheit.excel', [
            'schulId' => $partner->id,
            'schuljahr' => '2026-2027',
            'teil' => 'Teil 1',
        ]), ['von' => '2026-09-02', 'bis' => '2026-09-01'])->assertSessionHasErrors('bis');
    }
}
