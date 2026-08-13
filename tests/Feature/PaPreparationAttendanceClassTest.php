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

    public function test_pa_uses_one_shared_signature_draft_for_all_classes_and_class_views(): void
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

        $students = [];
        foreach ([['7.1', 'Anna'], ['7.2', 'Ben']] as [$class, $firstName]) {
            $person = Personen::factory()->create([
                'typ' => 'teilnehmer',
                'vorname' => $firstName,
            ]);

            $students[$class] = PersonenIstSchueler::query()->create([
                'person_id' => $person->id,
                'klasse' => $class,
                'schule_id' => $school->id,
                'schuljahr' => '2026/2027',
                'teil' => '1',
            ]);
        }

        $baseScope = [
            'schuleId' => $school->id,
            'schuljahr' => '2026/2027',
            'teil' => '1',
            'listType' => 'pa',
        ];
        $allScope = $baseScope + ['exportMode' => 'alle'];
        $classScope = $baseScope + ['exportMode' => 'klasse', 'klasse' => '7.1'];
        $firstSignatureKey = 'pa-2026-08-12:' . $students['7.2']->person_id;
        $secondSignatureKey = 'pa-2026-08-12:' . $students['7.1']->person_id;
        $signature = 'data:image/png;base64,aGVsbG8=';
        $payload = [
            'version' => 1,
            'form' => ['startDate' => '2026-08-12', 'exportMode' => 'alle', 'klasse' => ''],
            'days' => [],
            'selectedDayId' => null,
            'signatures' => [$firstSignatureKey => $signature],
        ];

        $this->actingAs($user)
            ->putJson(route('anwesenheitsliste.PA.digital.draft.store'), $allScope + ['payload' => $payload])
            ->assertOk()
            ->assertJsonPath('revision', 1);

        $this->actingAs($user->fresh())
            ->postJson(route('anwesenheitsliste.PA.digital.draft.show'), $classScope)
            ->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonPath("payload.signatures.{$firstSignatureKey}", $signature);

        $payload['form']['exportMode'] = 'klasse';
        $payload['form']['klasse'] = '7.1';
        $payload['signatures'] = [$secondSignatureKey => $signature];

        $this->actingAs($user->fresh())
            ->putJson(route('anwesenheitsliste.PA.digital.draft.store'), $classScope + ['payload' => $payload])
            ->assertOk()
            ->assertJsonPath('revision', 2);

        $this->actingAs($user->fresh())
            ->postJson(route('anwesenheitsliste.PA.digital.draft.show'), $allScope)
            ->assertOk()
            ->assertJsonPath("payload.signatures.{$firstSignatureKey}", $signature)
            ->assertJsonPath("payload.signatures.{$secondSignatureKey}", $signature);

        $this->actingAs($user->fresh())
            ->postJson(route('anwesenheitsliste.PA.digital.preview'), $classScope)
            ->assertOk()
            ->assertJsonCount(1, 'participants')
            ->assertJsonPath('participants.0.klasse', '7.1');

        $this->actingAs($user->fresh())
            ->postJson(route('anwesenheitsliste.PA.digital.preview'), $allScope)
            ->assertOk()
            ->assertJsonCount(2, 'participants');
    }

    public function test_pa_preparation_supports_the_whole_school_or_one_class(): void
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
            ->assertOk()
            ->assertJsonPath('context.export_mode', 'alle')
            ->assertJsonCount(2, 'participants');

        $this->actingAs($user->fresh())
            ->postJson(route('anwesenheitsliste.PA.digital.draft.show'), $basePayload + [
                'exportMode' => 'alle',
            ])
            ->assertOk();

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

        $scope = $basePayload + [
            'exportMode' => 'klasse',
            'klasse' => '7a',
        ];
        $draftPayload = [
            'version' => 1,
            'form' => [
                'exportFormat' => 'A4',
                'startDate' => '2026-09-01',
                'exportMode' => 'klasse',
                'klasse' => '7a',
            ],
            'days' => [],
            'selectedDayId' => null,
            'signatures' => [],
        ];

        $this->actingAs($user->fresh())
            ->putJson(route('anwesenheitsliste.PA.digital.draft.store'), $scope + ['payload' => $draftPayload])
            ->assertOk()
            ->assertJsonPath('revision', 1);

        $this->actingAs($user->fresh())
            ->putJson(route('anwesenheitsliste.PA.digital.draft.store'), $scope + ['payload' => $draftPayload])
            ->assertOk()
            ->assertJsonPath('revision', 1);

        $draftPayload['form']['startDate'] = '2026-09-02';

        $this->actingAs($user->fresh())
            ->putJson(route('anwesenheitsliste.PA.digital.draft.store'), $scope + ['payload' => $draftPayload])
            ->assertOk()
            ->assertJsonPath('revision', 2);
    }
}
