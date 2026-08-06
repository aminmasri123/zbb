<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaPreparationAttendanceClassTest extends TestCase
{
    use RefreshDatabase;

    public function test_pa_preparation_requires_a_class_and_filters_participants_by_it(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');

        $project = Projekt::factory()->create();
        $school = Partner::query()->create(['name' => 'Testschule']);

        DB::table('projekt_has_partners')->insert([
            'projekt_id' => $project->id,
            'partner_id' => $school->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user->update(['current_team_id' => $project->id]);

        foreach ([['7a', 'Anna'], ['8b', 'Ben']] as [$class, $firstName]) {
            $person = Personen::factory()->create([
                'typ' => 'teilnehmer',
                'vorname' => $firstName,
            ]);

            PersonenIstSchueler::query()->create([
                'person_id' => $person->id,
                'klasse' => $class,
                'schule_id' => $school->id,
                'schuljahr' => '2026/2027',
                'teil' => '1',
            ]);
        }

        $basePayload = [
            'schuleId' => $school->id,
            'schuljahr' => '2026/2027',
            'teil' => '1',
            'listType' => 'pa_preparation',
        ];

        $this->actingAs($user)
            ->postJson(route('anwesenheitsliste.PA.digital.preview'), $basePayload + [
                'exportMode' => 'alle',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('klasse');

        $this->actingAs($user->fresh())
            ->postJson(route('anwesenheitsliste.PA.digital.draft.show'), $basePayload + [
                'exportMode' => 'alle',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('klasse');

        $this->actingAs($user->fresh())
            ->postJson(route('anwesenheitsliste.PA.digital.preview'), $basePayload + [
                'exportMode' => 'klasse',
                'klasse' => '7a',
            ])
            ->assertOk()
            ->assertJsonPath('context.export_mode', 'klasse')
            ->assertJsonPath('context.klasse', '7a')
            ->assertJsonCount(1, 'participants')
            ->assertJsonPath('participants.0.vorname', 'Anna')
            ->assertJsonPath('participants.0.klasse', '7a');
    }
}
