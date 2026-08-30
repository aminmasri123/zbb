<?php

namespace Tests\Feature;

use App\Models\AppCalendarEvent;
use App\Models\Berechtigungskategorie;
use App\Models\Bereich;
use App\Models\BopPhaseSchedule;
use App\Models\BopRun;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BopRunWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_bop_run_saves_phase_dates_scopes_participants_and_group_keys(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $partner = Partner::query()->create(['name' => 'Testschule']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $project->partners()->attach($partner->id);
        $this->givePermission($user, 'einteilung.planning');

        $studentA = $this->student($partner, '7.1', 'Alpha');
        $studentB = $this->student($partner, '7.1', 'Beta');
        $studentC = $this->student($partner, '7.2', 'Gamma');

        $phases = [
            $this->phase('pa_preparation', ['2026-09-01'], 'classes', ['7.1'], [], 'class', false, true) + [
                'days_per_class' => 1,
                'class_date_assignments' => ['7.1' => ['2026-09-01']],
            ],
            $this->phase('pa', ['2026-09-02', '2026-09-03', '2026-09-04', '2026-09-07'], 'classes', ['7.1', '7.2'], [], 'class') + [
                'days_per_class' => 2,
                'class_date_assignments' => [
                    '7.1' => ['2026-09-02', '2026-09-03'],
                    '7.2' => ['2026-09-04', '2026-09-07'],
                ],
            ],
            $this->phase('pa_feedback', ['2026-09-10'], 'participants', [], [$studentA->id, $studentC->id], 'none'),
            $this->phase('roll_day', ['2026-10-01'], 'school', [], [], 'balanced', false, false, 2),
            $this->phase('workshop_days', ['2026-10-02', '2026-10-12'], 'school', [], [], 'existing_assignment') + [
                'part_date_assignments' => ['1' => ['2026-10-02'], '2' => ['2026-10-12']],
            ],
            $this->phase('wt_feedback', ['2026-10-20'], 'school', [], [], 'none'),
        ];

        $response = $this->actingAs($user)->putJson(route('bop.run.update', [
            'partner' => $partner,
            'schuljahr' => '2026/2027',
            'teil' => '_all',
        ]), [
            'school_type' => 'Gemeinschaftsschule',
            'status' => 'confirmed',
            'planned_classes' => [
                ['name' => '7.1', 'expected_participants' => 24, 'part' => '1'],
                ['name' => '7.a', 'expected_participants' => 18, 'part' => '2'],
            ],
            'parts' => ['1', '2'],
            'phases' => $phases,
        ]);

        $response->assertOk()->assertJsonPath('run.school_type', 'Gemeinschaftsschule');
        $run = BopRun::query()->firstOrFail();
        $this->assertSame('2026-09-01', $run->first_visit_date->toDateString());
        $this->assertSame('2026-10-20', $run->last_visit_date->toDateString());
        $this->assertSame(42, collect($run->planned_classes)->sum('expected_participants'));

        $preparation = BopPhaseSchedule::where('phase_type', 'pa_preparation')->firstOrFail();
        $this->assertCount(2, $preparation->participants);
        $this->assertSame(['7.1'], $preparation->participants->pluck('group_key')->unique()->values()->all());
        $this->assertNotNull($preparation->calendar_event_id);
        $preparationEvent = AppCalendarEvent::findOrFail($preparation->calendar_event_id);
        $this->assertSame('#6b7280', $preparationEvent->background_color);
        $this->assertSame('Vorb. PA Testschule', $preparationEvent->title);

        $pa = BopPhaseSchedule::where('phase_type', 'pa')->firstOrFail();
        $this->assertSame(2, $pa->days_per_class);
        $this->assertSame(['2026-09-02', '2026-09-03'], $pa->class_date_assignments['7.1']);
        $this->assertSame(['2026-09-04', '2026-09-07'], $pa->class_date_assignments['7.2']);

        $workshop = BopPhaseSchedule::where('phase_type', 'workshop_days')->firstOrFail();
        $this->assertSame(['2026-10-02'], $workshop->part_date_assignments['1']);
        $this->assertSame(['2026-10-12'], $workshop->part_date_assignments['2']);

        $feedback = BopPhaseSchedule::where('phase_type', 'pa_feedback')->firstOrFail();
        $this->assertEqualsCanonicalizing([$studentA->id, $studentC->id], $feedback->participants->pluck('personen_ist_schueler_id')->all());

        $rollDay = BopPhaseSchedule::where('phase_type', 'roll_day')->firstOrFail();
        $this->assertSame(['Gruppe 1', 'Gruppe 2'], $rollDay->participants->pluck('group_key')->unique()->sort()->values()->all());
        $this->assertSame(3, $rollDay->participants->count());
        $this->assertDatabaseHas('bop_phase_participants', ['personen_ist_schueler_id' => $studentB->id]);

        $renamePhases = collect($phases)->map(fn (array $phase) => [...$phase, 'participant_ids' => []])->all();
        $this->actingAs($user)->putJson(route('bop.run.update', [
            'partner' => $partner, 'schuljahr' => '2027/2028', 'teil' => '_all',
        ]), [
            'original_schuljahr' => '2026/2027',
            'school_type' => 'Gemeinschaftsschule', 'status' => 'confirmed',
            'planned_classes' => [
                ['name' => '7.1', 'expected_participants' => 24, 'part' => '1'],
                ['name' => '7.a', 'expected_participants' => 18, 'part' => '2'],
            ],
            'parts' => ['1', '2'], 'phases' => $renamePhases,
        ])->assertOk()->assertJsonPath('previous_schuljahr', '2026/2027');
        $this->assertSame('2027/2028', $run->fresh()->schuljahr);
        $this->assertSame(1, BopRun::query()->count());

        $this->actingAs($user)->deleteJson(route('bop.run.reset', ['partner' => $partner]), [
            'schuljahr' => '2027/2028', 'teil' => '_all', 'mode' => 'dates',
        ])->assertOk()->assertJsonPath('reset_mode', 'dates');
        $this->assertSame([], $preparation->fresh()->dates);
        $this->assertDatabaseMissing('app_calendar_events', ['id' => $preparationEvent->id]);
        $this->assertSame('planning', $run->fresh()->status);

        $this->actingAs($user)->deleteJson(route('bop.run.reset', ['partner' => $partner]), [
            'schuljahr' => '2027/2028', 'teil' => '_all', 'mode' => 'full',
        ])->assertOk()->assertJsonPath('reset_mode', 'full');
        $this->assertDatabaseMissing('bop_runs', ['id' => $run->id]);
        $this->assertDatabaseHas('personen_ist_schuelers', ['id' => $studentA->id]);
    }

    public function test_bop_run_is_unavailable_in_non_bop_project(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'Anderes Projekt']);
        $partner = Partner::query()->create(['name' => 'Testschule']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $project->partners()->attach($partner->id);
        $this->givePermission($user, 'kooperationspartner.index');

        $this->actingAs($user)->getJson(route('bop.run.show', [
            'partner' => $partner, 'schuljahr' => '2026/2027', 'teil' => 'Teil 1',
        ]))->assertNotFound();
    }

    public function test_workshop_timetable_can_be_previewed_and_then_persisted(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $partner = Partner::query()->create(['name' => 'Planschule']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $project->partners()->attach($partner->id);
        $this->givePermission($user, 'einteilung.planning');

        $it = Bereich::query()->create(['name' => 'IT']);
        $kunst = Bereich::query()->create(['name' => 'Kunst']);
        $project->bereiche()->attach([$it->id => ['aktiv' => true], $kunst->id => ['aktiv' => true]]);

        $run = BopRun::query()->create([
            'projekt_id' => $project->id,
            'partner_id' => $partner->id,
            'schuljahr' => '2026/2027',
            'teil' => '_all',
            'school_type' => 'Gemeinschaftsschule',
            'status' => 'planning',
        ]);
        $run->phases()->create([
            'phase_type' => 'workshop_days',
            'dates' => ['2026-10-02'],
            'scope_type' => 'school',
            'group_mode' => 'existing_assignment',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $payload = [
            'schuljahr' => '2026/2027',
            'teil' => '_all',
            'schedule_date' => '2026-10-02',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_minutes' => 15,
            'groups' => ['G1', 'G2'],
            'areas' => [
                ['bereich_id' => $it->id, 'duration_minutes' => 45],
                ['bereich_id' => $kunst->id, 'duration_minutes' => 45],
            ],
            'events' => [
                ['title' => 'Begrüßung', 'type' => 'shared', 'start_time' => '09:00', 'end_time' => '09:30'],
            ],
        ];

        $this->actingAs($user)->postJson(route('bop.run.timetable.generate', ['partner' => $partner]), [
            ...$payload, 'persist' => false,
        ])->assertOk()
            ->assertJsonPath('persisted', false)
            ->assertJsonCount(5, 'timetable.entries');
        $this->assertDatabaseCount('bop_timetables', 0);

        $this->actingAs($user)->postJson(route('bop.run.timetable.generate', ['partner' => $partner]), [
            ...$payload, 'persist' => true,
        ])->assertOk()
            ->assertJsonPath('persisted', true)
            ->assertJsonPath('timetable.config.groups.0', 'G1');
        $this->assertDatabaseCount('bop_timetables', 1);
        $this->assertDatabaseCount('bop_timetable_entries', 5);
        $this->assertDatabaseHas('bop_timetable_entries', [
            'group_key' => null, 'type' => 'shared', 'title' => 'Begrüßung',
        ]);
    }

    public function test_partner_page_can_create_a_timetable_without_an_existing_bop_run(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $partner = Partner::query()->create(['name' => 'Neue Planschule']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $project->partners()->attach($partner->id);
        $this->givePermission($user, 'einteilung.planning');

        $it = Bereich::query()->create(['name' => 'IT']);
        $project->bereiche()->attach($it->id, ['aktiv' => true]);
        $payload = [
            'schuljahr' => '2026/2027', 'teil' => 'Teil 1',
            'schedule_date' => '2026-11-03', 'start_time' => '09:00', 'end_time' => '11:00',
            'slot_minutes' => 15, 'groups' => ['G1'],
            'areas' => [['bereich_id' => $it->id, 'duration_minutes' => 60]],
            'events' => [],
        ];

        $this->actingAs($user)->postJson(route('bop.run.timetable.generate', ['partner' => $partner]), [
            ...$payload, 'persist' => false,
        ])->assertOk()->assertJsonPath('persisted', false);
        $this->assertDatabaseCount('bop_runs', 0);

        $this->actingAs($user)->postJson(route('bop.run.timetable.generate', ['partner' => $partner]), [
            ...$payload, 'persist' => true,
        ])->assertOk()->assertJsonPath('persisted', true);
        $this->assertDatabaseHas('bop_runs', ['partner_id' => $partner->id, 'teil' => 'Teil 1']);
        $this->assertDatabaseHas('bop_phase_schedules', ['phase_type' => 'workshop_days']);
        $this->assertDatabaseHas('bop_timetables', ['schedule_date' => '2026-11-03']);
    }

    public function test_historical_school_year_without_plan_suggests_imported_classes_parts_and_counts(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $partner = Partner::query()->create(['name' => 'Historische Schule']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $project->partners()->attach($partner->id);
        $this->givePermission($user, 'kooperationspartner.index');

        $this->student($partner, '7.1', 'Alpha');
        $this->student($partner, '7.1', 'Beta');
        $person = Personen::factory()->create(['typ' => 'teilnehmer', 'aktiv' => true, 'nachname' => 'Gamma']);
        PersonenIstSchueler::query()->create([
            'person_id' => $person->id,
            'klasse' => '8a',
            'schuljahr' => '2026/2027',
            'teil' => '2',
            'schule_id' => $partner->id,
        ]);

        $this->actingAs($user)->getJson(route('bop.run.show', [
            'partner' => $partner,
            'schuljahr' => '2026/2027',
            'teil' => '_all',
        ]))->assertOk()
            ->assertJsonPath('run', null)
            ->assertJsonPath('suggested_parts', ['1', '2'])
            ->assertJsonPath('suggested_planned_classes.0.name', '7.1')
            ->assertJsonPath('suggested_planned_classes.0.expected_participants', 2)
            ->assertJsonPath('suggested_planned_classes.0.part', '1')
            ->assertJsonPath('suggested_planned_classes.1.name', '8a')
            ->assertJsonPath('suggested_planned_classes.1.expected_participants', 1)
            ->assertJsonPath('suggested_planned_classes.1.part', '2');
    }

    private function student(Partner $partner, string $class, string $lastName): PersonenIstSchueler
    {
        $person = Personen::factory()->create(['typ' => 'teilnehmer', 'aktiv' => true, 'nachname' => $lastName]);

        return PersonenIstSchueler::query()->create([
            'person_id' => $person->id,
            'klasse' => $class,
            'schuljahr' => '2026/2027',
            'teil' => 'Teil 1',
            'schule_id' => $partner->id,
        ]);
    }

    private function phase(string $type, array $dates, string $scope, array $classes, array $participants, string $groupMode, bool $generate = false, bool $calendar = false, int $groupCount = 1): array
    {
        return [
            'phase_type' => $type,
            'dates' => $dates,
            'scope_type' => $scope,
            'selected_classes' => $classes,
            'participant_ids' => $participants,
            'group_mode' => $groupMode,
            'group_count' => $groupCount,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'generate_groups' => $generate,
            'publish_to_calendar' => $calendar,
        ];
    }

    private function givePermission(User $user, string $name): void
    {
        $categoryId = Berechtigungskategorie::query()->firstOrCreate(['name' => 'BOP'], ['beschreibung' => ''])->id;
        Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $categoryId, 'beschreibung' => null]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo($name);
    }
}
