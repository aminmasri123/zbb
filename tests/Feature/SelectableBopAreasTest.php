<?php

namespace Tests\Feature;

use App\Models\{Bereich, Bereichsauswahl, BereichsauswahlSetting, EinteilungBereiche, EinteilungSetting, Partner, Personen, PersonenIstSchueler, Projekt, User};
use App\Services\Bop\SelectableAreas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SelectableBopAreasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Match the nullable choice columns from the MySQL-only legacy migrations.
        \Illuminate\Support\Facades\Schema::table('bereichsauswahls', function (\Illuminate\Database\Schema\Blueprint $table) {
            foreach ([1, 2, 3, 4] as $field) {
                $table->unsignedBigInteger('bereich_id'.$field)->nullable()->change();
            }
        });
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $school = Partner::create(['name' => 'Testschule']);
        $project->partners()->attach($school);
        $user->projekte()->attach($project);
        $user->update(['current_team_id' => $project->id]);
        foreach (['bereichsauswahl.index', 'bereichsauswahl.planning', 'bereichsauswahl.store', 'bereichsauswahl.update', 'einteilung.index', 'einteilung.store', 'einteilung.update', 'einteilung.export'] as $permission) {
            $this->grantTestPermission($user, $permission);
        }
        $areas = collect(['Holz', 'IT', 'Metall', 'Farbe', 'Potenzialanalyse', 'Rolltag'])
            ->map(fn ($name) => Bereich::create(['name' => $name]));
        $project->bereiche()->attach($areas->pluck('id'));
        $student = PersonenIstSchueler::create([
            'person_id' => Personen::factory()->create(['typ' => 'teilnehmer'])->id,
            'schule_id' => $school->id, 'schuljahr' => '2026/2027', 'teil' => '1', 'klasse' => '8',
        ]);
        $selection = Bereichsauswahl::create(['teilnehmer_id' => $student->id, 'access_code' => 'TEST-1234', 'user_create' => $user->id]);
        $setting = BereichsauswahlSetting::create([
            'projekt_id' => $project->id, 'partner_id' => $school->id, 'schuljahr' => '2026', 'teil' => '1',
            'auswahl_anzahl' => 2, 'public_token' => 'test-area-token', 'zugang_aktiv' => true,
        ]);
        $assignment = EinteilungSetting::create([
            'projekt_id' => $project->id, 'partner_id' => $school->id, 'schuljahr' => '2026/2027', 'teil' => '1',
            'runden_anzahl' => 2, 'standard_kapazitaet' => 15,
        ]);
        foreach ([1, 2] as $round) {
            $assignment->rundentermine()->create([
                'runde' => $round, 'anfangsdatum' => '2026-09-0'.$round, 'enddatum' => '2026-09-0'.$round,
                'startzeit' => '08:00', 'endzeit' => '15:00',
            ]);
        }
        \App\Models\Anwesenheitsstatuten::create(['status' => 'unentschuldigt', 'abkuerzung' => 'U', 'farben' => '#ef4444']);
        $this->actingAs($user);

        return [$user, $project, $school, $areas, $student, $setting, $selection];
    }

    private function payload(Partner $school): array
    {
        return ['partner_id' => $school->id, 'schuljahr' => '2026/2027', 'teil' => '1'];
    }

    public function test_project_stages_are_excluded_by_default_without_removing_project_membership(): void
    {
        [, $project, $school] = $this->context();
        $this->get(route('bereichsauswahl.index', [$school->id, '2026', '1']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Bereichsauswahl/Index')
                ->has('verfuegbare_bereiche', 4)->has('waehlbare_projektbereiche', 4));
        $this->get(route('bereichsauswahl.self.show', 'test-area-token'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Bereichsauswahl/Selbstwahl')->has('bereiche', 4));
        $this->get(route('einteilung.show', [$school->id, '2026', '1']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Teilnehmer/Einteilung/Index')->has('alle_bereiche', 4));
        $this->assertCount(6, $project->fresh()->bereiche);
        $project->bereiche()->attach(Bereich::create(['name' => 'PA'])->id);
        $project->bereiche()->attach(Bereich::create(['name' => 'Analyse', 'code' => 'PA'])->id);
        $this->assertCount(4, app(SelectableAreas::class)->eligible($project->fresh()));
    }

    public function test_saved_areas_are_shared_and_scoped_to_school_year_part_and_project(): void
    {
        [, $project, $school, $areas, , $setting] = $this->context();
        $ids = $areas->take(2)->pluck('id')->all();
        $this->postJson(route('bereichsauswahl.setting.update'), $this->payload($school) + [
            'auswahl_anzahl' => 2, 'bereich_ids' => $ids, 'zugang_aktiv' => true,
        ])->assertOk();
        $this->assertSame($ids, $setting->fresh()->bereich_ids);
        $this->get(route('bereichsauswahl.self.show', 'test-area-token'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Bereichsauswahl/Selbstwahl')->where('bereiche', fn ($value) => collect($value)->pluck('id')->all() === $ids));
        $this->get(route('einteilung.show', [$school->id, '2026', '1']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Teilnehmer/Einteilung/Index')->has('alle_bereiche', 2));
        $service = app(SelectableAreas::class);
        $this->assertCount(2, $service->forContext($project, $school->id, '2026-27', '1'));
        $this->assertCount(4, $service->forContext($project, $school->id, '2027', '1'));
        $this->assertCount(4, $service->forContext($project, $school->id, '2026', '2'));
        $this->assertCount(4, $service->forContext($project, Partner::create(['name' => 'Other'])->id, '2026', '1'));
        $otherProject = Projekt::factory()->create();
        $otherProject->bereiche()->attach($areas->pluck('id'));
        $this->assertCount(4, $service->forContext($otherProject, $school->id, '2026', '1'));
    }

    public function test_disabled_and_stage_choices_are_rejected_for_staff_public_and_manual_assignment(): void
    {
        [, , $school, $areas, $student, $setting] = $this->context();
        $setting->update(['bereich_ids' => $areas->take(2)->pluck('id')->all()]);
        foreach ([$areas[2]->id, $areas[4]->id, $areas[5]->id] as $invalid) {
            $choices = [$areas[0]->id, $invalid];
            $this->postJson(route('bereichsauswahl.bop.radio.update'), ['teilnehmer_id' => $student->id, 'choices' => $choices])->assertUnprocessable();
            $this->postJson(route('bereichsauswahl.self.store', 'test-area-token'), ['access_code' => 'TEST-1234', 'choices' => $choices])->assertUnprocessable();
            $this->postJson(route('einteilung.create'), $this->payload($school) + [
                'schueler_id' => $student->id, 'runde_1' => $areas[0]->id, 'runde_2' => $invalid,
            ])->assertUnprocessable()->assertJsonValidationErrors('runde_1');
        }
        $this->postJson(route('bereichsauswahl.self.store', 'test-area-token'), [
            'access_code' => 'TEST-1234', 'choices' => $areas->take(2)->pluck('id')->all(),
        ])->assertOk();
        $this->postJson(route('einteilung.create'), $this->payload($school) + [
            'schueler_id' => $student->id, 'runde_1' => $areas[0]->id, 'runde_2' => $areas[1]->id,
        ])->assertOk();
        $this->postJson(route('einteilung.update'), $this->payload($school) + [
            'schueler_id' => $student->id, 'runde_1' => $areas[0]->id, 'runde_2' => $areas[5]->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('runde_1');
        $this->assertSame($areas[1]->id, EinteilungBereiche::where('teilnehmende_id', $student->id)->where('runde', 2)->value('bereich_id'));
    }

    public function test_settings_reject_stages_foreign_areas_and_too_few_choices(): void
    {
        [, , $school, $areas, , $setting] = $this->context();
        $foreign = Bereich::create(['name' => 'Other']);
        foreach ([$areas[4]->id, $areas[5]->id, $foreign->id] as $invalid) {
            $this->postJson(route('bereichsauswahl.setting.update'), $this->payload($school) + [
                'auswahl_anzahl' => 2, 'bereich_ids' => [$areas[0]->id, $invalid],
            ])->assertUnprocessable()->assertJsonValidationErrors('bereich_ids');
        }
        $this->postJson(route('bereichsauswahl.setting.update'), $this->payload($school) + [
            'auswahl_anzahl' => 3, 'bereich_ids' => $areas->take(2)->pluck('id')->all(),
        ])->assertUnprocessable()->assertJsonValidationErrors('bereich_ids');
        $this->assertNull($setting->fresh()->bereich_ids);
        $reader = User::factory()->create(['current_team_id' => $setting->projekt_id]);
        $this->actingAs($reader)->postJson(route('bereichsauswahl.setting.update'), $this->payload($school) + [
            'auswahl_anzahl' => 2, 'bereich_ids' => $areas->take(2)->pluck('id')->all(),
        ])->assertForbidden();
    }

    public function test_deactivation_preserves_old_choices_assignments_and_shows_review_warnings(): void
    {
        [, , $school, $areas, $student, , $selection] = $this->context();
        $selection->update(['bereich_id1' => $areas[2]->id, 'bereich_id2' => $areas[0]->id]);
        $assignment = EinteilungBereiche::create([
            'teilnehmende_id' => $student->id, 'teilnehmende_type' => PersonenIstSchueler::class,
            'bereich_id' => $areas[2]->id, 'runde' => 1,
        ]);
        $this->postJson(route('bereichsauswahl.setting.update'), $this->payload($school) + [
            'auswahl_anzahl' => 2, 'bereich_ids' => $areas->take(2)->pluck('id')->all(),
        ])->assertOk();
        $this->assertSame($areas[2]->id, $selection->fresh()->bereich_id1);
        $this->assertSame($areas[2]->id, $assignment->fresh()->bereich_id);
        $this->get(route('bereichsauswahl.index', [$school->id, '2026', '1']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Bereichsauswahl/Index')->where('bereich_warnungen.0.id', $student->id));
        $this->get(route('einteilung.show', [$school->id, '2026', '1']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Teilnehmer/Einteilung/Index')->where('bereich_warnungen.0.id', $student->id));
    }

    public function test_automatic_assignment_and_export_only_use_available_areas(): void
    {
        [, , $school, $areas, $student, $setting, $selection] = $this->context();
        $ids = $areas->take(2)->pluck('id')->all();
        $setting->update(['bereich_ids' => $ids]);
        $selection->update(['bereich_id1' => $areas[4]->id, 'bereich_id2' => $areas[2]->id]);
        $this->postJson(route('einteilung.store'), $this->payload($school))->assertOk();
        $assigned = EinteilungBereiche::where('teilnehmende_id', $student->id)->pluck('bereich_id')->all();
        $this->assertEqualsCanonicalizing($ids, $assigned);
        $response = $this->post(route('einteilung.export.excel'), $this->payload($school) + ['runde' => 'alle'])->assertOk();
        $path = $response->getFile()->getPathname();
        try {
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();
            $this->assertSame(['RUNDE', 'HOLZ', 'IT'], $sheet->rangeToArray('A3:C3')[0]);
            $this->assertNull($sheet->getCell('D3')->getValue());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }
}
