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
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class BopDailyEvaluationExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_selected_bo_day_is_exported_for_every_group_participant(): void
    {
        [$user, $group] = $this->context();
        $this->assertSame('BOP', $group->projekt?->name);

        $response = $this->actingAs($user)->get(route('gruppe.bop.export.tagesauswertung', [
            'gruppe' => $group->id,
            'bo_tag' => 2,
        ]))->assertOk();

        $this->assertStringContainsString('BO_Tag_2.pdf', (string) $response->headers->get('content-disposition'));
        $pdf = new Fpdi;
        $this->assertSame(2, $pdf->setSourceFile($response->getFile()->getPathname()));
    }

    public function test_all_three_bo_days_are_exported_for_every_group_participant(): void
    {
        [$user, $group] = $this->context();

        $response = $this->actingAs($user)->get(route('gruppe.bop.export.tagesauswertung', [
            'gruppe' => $group->id,
            'bo_tag' => 'alle',
        ]))->assertOk();

        $pdf = new Fpdi;
        $this->assertSame(6, $pdf->setSourceFile($response->getFile()->getPathname()));
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $partner = Partner::query()->create(['name' => 'Gemeinschaftsschule Test']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $project->partners()->attach($partner->id);
        $this->grantTestPermission($user, 'gruppe.bop.export.auswertungsbogen-bop');

        $area = Bereich::query()->create(['name' => 'IT- und Mediengestaltung']);
        $staff = $user->person;
        $staff->update(['typ' => 'mitarbeiter']);
        $location = Standort::factory()->create();
        $room = Raeume::query()->create(['name' => 'IT-Raum', 'standort_id' => $location->id, 'typ' => 'Werkstatt']);
        $group = Gruppe::query()->create([
            'personen_id' => $staff->id,
            'bereich_id' => $area->id,
            'projekt_id' => $project->id,
            'partner_id' => $partner->id,
            'standort_id' => $location->id,
            'raum_id' => $room->id,
            'anfangsdatum' => '2026-09-01',
            'enddatum' => '2026-09-03',
            'bemerkung' => 'BOP Einteilung Schule '.$partner->id.' Schuljahr 2026/2027 Teil Teil 1 Runde 1',
        ]);
        $day = Tage::query()->create(['datum' => '2026-09-01', 'wochentag' => 'Dienstag']);
        $time = Zeiten::query()->create(['startzeit' => '08:00', 'endzeit' => '14:00']);
        $status = Anwesenheitsstatuten::query()->create(['status' => 'anwesend', 'abkuerzung' => 'A', 'farben' => '#22c55e']);

        foreach ([['Mia', 'Muster', '7.1'], ['Noah', 'Beispiel', '7.2']] as [$firstName, $lastName, $class]) {
            $person = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => $firstName, 'nachname' => $lastName]);
            PersonenIstSchueler::query()->create([
                'person_id' => $person->id,
                'schule_id' => $partner->id,
                'schuljahr' => '2026/2027',
                'teil' => 'Teil 1',
                'klasse' => $class,
            ]);
            $project->teilnehmer()->attach($person->id);
            GruppeHasPersonen::query()->create([
                'personen_id' => $person->id,
                'user_id' => $user->id,
                'gruppe_id' => $group->id,
                'tage_id' => $day->id,
                'zeitgeplant_id' => $time->id,
                'zeittatsaechlich_id' => $time->id,
                'anwesenheitsstatuten_id' => $status->id,
            ]);
        }

        return [$user, $group];
    }
}
