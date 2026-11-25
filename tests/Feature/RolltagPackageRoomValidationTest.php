<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolltagPackageRoomValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_preview_reports_participants_and_rejects_insufficient_room_capacity(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $school = Partner::create(['name' => 'Raum-Testschule']);
        $project->partners()->attach($school);
        $project->teilnehmer()->attach($user->person_id, ['status' => 'aktiv']);
        $user->update(['current_team_id' => $project->id]);

        foreach (['Anna', 'Ben'] as $firstName) {
            $person = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => $firstName]);
            $project->teilnehmer()->attach($person, ['status' => 'aktiv']);
            PersonenIstSchueler::create([
                'person_id' => $person->id,
                'schule_id' => $school->id,
                'schuljahr' => '2026/2027',
                'teil' => '1',
                'klasse' => '7.1',
            ]);
        }

        $url = route('partner.bop.packages.download', ['partner' => $school->id, 'package' => 'rolltag']);
        $selection = ['schuljahr' => '2026', 'teil' => '1'];

        $this->actingAs($user->fresh())->postJson($url, $selection + ['preview' => true])
            ->assertOk()
            ->assertJsonPath('hasPlannedGroups', false)
            ->assertJsonPath('participantCount', 2);

        $this->postJson($url, $selection + [
            'termin' => '2026-11-24',
            'rolltag_mode' => 'rooms',
            'rooms' => [['name' => 'Werkstatt', 'capacity' => 1]],
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Die Raumkapazitäten reichen nicht für alle Teilnehmenden aus.');
    }
}
