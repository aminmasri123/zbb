<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PaAttendanceListDraft;
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
        DB::table('projekt_has_personens')->insert([
            'projekt_id' => $project->id,
            'personen_id' => $user->person_id,
            'status' => 'aktiv',
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

    public function test_pa_merges_class_schedules_without_losing_legacy_dates_or_signatures(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');

        $project = Projekt::factory()->create();
        $school = Partner::query()->create(['name' => 'Testschule Klassentermine']);

        DB::table('projekt_has_partners')->insert([
            'projekt_id' => $project->id,
            'partner_id' => $school->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('projekt_has_personens')->insert([
            'projekt_id' => $project->id,
            'personen_id' => $user->person_id,
            'status' => 'aktiv',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user->update(['current_team_id' => $project->id]);

        $scope = [
            'schuleId' => $school->id,
            'schuljahr' => '2026/2027',
            'teil' => '1',
            'listType' => 'pa',
            'exportMode' => 'alle',
        ];
        $signature = 'data:image/png;base64,aGVsbG8=';
        $legacyDay = [
            'id' => 'pa-tag-1-2026-08-12',
            'date' => '2026-08-12',
            'type' => 'pa_day',
            'selected' => true,
        ];

        $this->actingAs($user)->putJson(route('anwesenheitsliste.PA.digital.draft.store'), $scope + [
            'payload' => [
                'version' => 1,
                'form' => ['startDate' => '2026-08-12'],
                'days' => [$legacyDay],
                'selectedDayId' => $legacyDay['id'],
                'signatures' => ['legacy:1' => $signature],
            ],
        ])->assertOk();

        foreach ([
            '7.1' => ['2026-08-15', 'class-71:2'],
            '7.2' => ['2026-08-18', 'class-72:3'],
        ] as $className => [$date, $signatureKey]) {
            $day = [
                'id' => 'pa-' . $date,
                'date' => $date,
                'type' => 'pa_day',
                'selected' => true,
            ];

            $this->actingAs($user->fresh())->putJson(route('anwesenheitsliste.PA.digital.draft.store'), $scope + [
                'payload' => [
                    'version' => 2,
                    'form' => ['startDate' => '2026-08-12'],
                    'days' => [$legacyDay],
                    'selectedDayId' => $legacyDay['id'],
                    'classSchedules' => [
                        $className => [
                            'form' => ['startDate' => $date],
                            'days' => [$day],
                            'selectedDayId' => $day['id'],
                        ],
                    ],
                    'signatures' => [$signatureKey => $signature],
                ],
            ])->assertOk();
        }

        // Ein noch geöffnetes altes Browserfenster darf die neuen Klassenpläne
        // und bereits gespeicherten Unterschriften nicht wieder entfernen.
        $this->actingAs($user->fresh())->putJson(route('anwesenheitsliste.PA.digital.draft.store'), $scope + [
            'payload' => [
                'version' => 1,
                'form' => ['startDate' => '2026-08-12'],
                'days' => [$legacyDay],
                'selectedDayId' => $legacyDay['id'],
                'signatures' => [],
            ],
        ])->assertOk();

        $response = $this->actingAs($user->fresh())
            ->postJson(route('anwesenheitsliste.PA.digital.draft.show'), $scope)
            ->assertOk();
        $payload = $response->json('payload');

        $this->assertSame(2, $payload['version']);
        $this->assertSame('2026-08-12', $payload['form']['startDate']);
        $this->assertSame($legacyDay['id'], $payload['days'][0]['id']);
        $this->assertSame('2026-08-15', $payload['classSchedules']['7.1']['form']['startDate']);
        $this->assertSame('2026-08-18', $payload['classSchedules']['7.2']['form']['startDate']);
        $this->assertSame($signature, $payload['signatures']['legacy:1']);
        $this->assertSame($signature, $payload['signatures']['class-71:2']);
        $this->assertSame($signature, $payload['signatures']['class-72:3']);

        $storedPayload = PaAttendanceListDraft::query()->firstOrFail()->payload;
        $this->assertStringStartsWith('enc:v1:', $storedPayload['signatures']['legacy:1']);
        $this->assertSame('2026-08-15', $storedPayload['classSchedules']['7.1']['form']['startDate']);
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
        DB::table('projekt_has_personens')->insert([
            'projekt_id' => $project->id,
            'personen_id' => $user->person_id,
            'status' => 'aktiv',
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
