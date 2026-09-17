<?php

namespace Tests\Feature;

use App\Models\Bereich;
use App\Models\Bereichsauswahl;
use App\Models\BereichsauswahlSetting;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BereichsauswahlSchoolYearTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The legacy nullable-column migrations run only on MySQL. Match that schema in SQLite tests.
        Schema::table('bereichsauswahls', function (Blueprint $table) {
            foreach ([1, 2, 3, 4] as $field) {
                $table->unsignedBigInteger('bereich_id' . $field)->nullable()->change();
            }
        });
    }

    #[DataProvider('schoolYearsAndCounts')]
    public function test_configured_count_is_used_for_every_participant_school_year_format(string $year, int $count): void
    {
        [$user, $project, $school, $areas] = $this->context();
        $student = $this->student($school, $year);
        $this->actingAs($user)->postJson(route('bereichsauswahl.setting.update'), [
            'partner_id' => $school->id, 'schuljahr' => '2026', 'teil' => '1',
            'auswahl_anzahl' => $count, 'zugang_aktiv' => true,
        ])->assertOk();

        $choices = array_slice($areas, 0, $count);
        $this->postJson(route('bereichsauswahl.bop.radio.update'), [
            'teilnehmer_id' => $student->id, 'choices' => $choices,
        ])->assertOk()->assertJsonPath('choices', $choices);

        $this->assertDatabaseCount('bereichsauswahl_settings', 1);
        $this->assertDatabaseHas('bereichsauswahls', [
            'teilnehmer_id' => $student->id, 'bereich_id1' => $choices[0], 'bereich_id2' => $choices[1],
            'bereich_id3' => $choices[2] ?? null, 'bereich_id4' => $choices[3] ?? null,
        ]);
    }

    public static function schoolYearsAndCounts(): array
    {
        return [
            'two, short' => ['2026', 2],
            'two, slash' => ['2026/2027', 2],
            'two, hyphen' => ['2026-2027', 2],
            'three, short end year' => ['2026/27', 3],
            'four, hyphen and short end year' => ['2026-27', 4],
        ];
    }

    public function test_existing_configured_alias_wins_over_a_newer_automatic_default(): void
    {
        [$user, $project, $school, $areas] = $this->context();
        $configured = $this->setting($project, $school, '2026', 2, $user);
        $configured->forceFill(['updated_at' => now()->subDay()])->save();
        $automatic = $this->setting($project, $school, '2026/2027', 4);
        $student = $this->student($school, '2026/2027');

        $this->actingAs($user)->get(route('bereichsauswahl.index', [$school->id, '2026', '1']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Bereichsauswahl/Index')->where('setting.auswahl_anzahl', 2));
        $this->postJson(route('bereichsauswahl.bop.radio.update'), [
            'teilnehmer_id' => $student->id, 'choices' => array_slice($areas, 0, 2),
        ])->assertOk();
        $this->assertSame($automatic->public_token, $automatic->fresh()->public_token);
        $this->assertDatabaseCount('bereichsauswahl_settings', 2);
    }

    public function test_updating_a_short_year_reuses_the_existing_long_year_setting_and_clears_hidden_choices(): void
    {
        [$user, $project, $school, $areas] = $this->context();
        $setting = $this->setting($project, $school, '2026/2027', 4, $user);
        $student = $this->student($school, '2026/27');
        $wahl = Bereichsauswahl::create([
            'teilnehmer_id' => $student->id, 'user_create' => $user->id,
            'bereich_id1' => $areas[0], 'bereich_id2' => $areas[1],
            'bereich_id3' => $areas[2], 'bereich_id4' => $areas[3],
        ]);
        $this->actingAs($user)->postJson(route('bereichsauswahl.setting.update'), [
            'partner_id' => $school->id, 'schuljahr' => '2026', 'teil' => '1',
            'auswahl_anzahl' => 2, 'zugang_aktiv' => true,
        ])->assertOk()->assertJsonPath('setting.id', $setting->id);
        $this->assertSame(2, $setting->fresh()->auswahl_anzahl);
        $this->assertNull($wahl->fresh()->bereich_id3);
        $this->assertNull($wahl->fresh()->bereich_id4);
        $this->assertDatabaseCount('bereichsauswahl_settings', 1);
    }

    public function test_public_code_uses_the_same_year_and_count_as_staff_selection(): void
    {
        [$user, $project, $school, $areas] = $this->context();
        $this->setting($project, $school, '2026', 2, $user);
        $legacy = $this->setting($project, $school, '2026/2027', 4);
        $student = $this->student($school, '2026/27');
        Bereichsauswahl::create([
            'teilnehmer_id' => $student->id, 'user_create' => $user->id, 'access_code' => 'BA-BE-22-BI',
        ]);

        $this->get(route('bereichsauswahl.self.show', $legacy->public_token))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Bereichsauswahl/Selbstwahl')->where('context.auswahl_anzahl', 2));
        $this->postJson(route('bereichsauswahl.self.verify', $legacy->public_token), [
            'access_code' => 'BA-BE-22-BI',
        ])->assertOk()->assertJsonPath('teilnehmer.id', $student->id)->assertJsonPath('teilnehmer.auswahl_anzahl', 2);
        $this->postJson(route('bereichsauswahl.self.store', $legacy->public_token), [
            'access_code' => 'BA-BE-22-BI', 'choices' => array_slice($areas, 0, 2),
        ])->assertOk();
        $this->assertNotNull($student->fresh()->bereichsauswahl->submitted_at);
    }

    public function test_disabled_public_access_cannot_be_bypassed_with_an_old_year_alias_token(): void
    {
        [$user, $project, $school] = $this->context();
        $canonical = $this->setting($project, $school, '2026', 2, $user);
        $legacy = $this->setting($project, $school, '2026/2027', 4);
        $canonical->update(['zugang_aktiv' => false]);
        $this->get(route('bereichsauswahl.self.show', $legacy->public_token))->assertNotFound();

        $canonical->update(['zugang_aktiv' => true]);
        $legacy->update(['zugang_aktiv' => false]);
        $this->get(route('bereichsauswahl.self.show', $legacy->public_token))->assertNotFound();
    }

    public function test_count_uniqueness_and_project_area_validation_remain_enforced(): void
    {
        [$user, $project, $school, $areas] = $this->context();
        $this->setting($project, $school, '2026', 2, $user);
        $student = $this->student($school, '2026/2027');
        $foreign = Bereich::create(['name' => 'Foreign project area']);
        $this->actingAs($user);
        foreach ([[$areas[0]], array_slice($areas, 0, 3), [$areas[0], $areas[0]], [$areas[0], $foreign->id]] as $choices) {
            $this->postJson(route('bereichsauswahl.bop.radio.update'), [
                'teilnehmer_id' => $student->id, 'choices' => $choices,
            ])->assertUnprocessable()->assertJsonValidationErrors('choices');
        }
        $this->assertDatabaseCount('bereichsauswahls', 0);
    }

    public function test_the_most_recent_explicit_setting_is_shared_with_assignment(): void
    {
        [$user, $project, $school, $areas] = $this->context();
        $old = $this->setting($project, $school, '2026/2027', 2, $user);
        $old->forceFill(['updated_at' => now()->subDay()])->save();
        $this->setting($project, $school, '2026', 3, $user);
        $this->setting($project, $school, '2026-2027', 4);
        $student = $this->student($school, '2026/2027');

        $this->actingAs($user)->postJson(route('bereichsauswahl.bop.radio.update'), [
            'teilnehmer_id' => $student->id, 'choices' => array_slice($areas, 0, 3),
        ])->assertOk();
        $this->get(route('einteilung.show', [$school->id, '2026-27', '1']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Teilnehmer/Einteilung/Index')->where('parameter.auswahl_anzahl', 3));
        $this->assertDatabaseCount('bereichsauswahl_settings', 3);
    }

    public function test_settings_from_other_projects_schools_years_and_parts_stay_separate(): void
    {
        [$user, $project, $school, $areas] = $this->context();
        $setting = $this->setting($project, $school, '2026', 2, $user);
        $setting->forceFill(['updated_at' => now()->subDay()])->save();
        foreach ([
            ['projekt_id' => Projekt::factory()->create()->id],
            ['partner_id' => Partner::create(['name' => 'Other school'])->id],
            ['schuljahr' => '2027/2028'],
            ['teil' => '2'],
        ] as $otherContext) {
            BereichsauswahlSetting::create(array_replace([
                'projekt_id' => $project->id, 'partner_id' => $school->id, 'schuljahr' => '2026/2027',
                'teil' => '1', 'auswahl_anzahl' => 4, 'zugang_aktiv' => true, 'user_update' => $user->id,
                'public_token' => \Illuminate\Support\Str::random(40),
            ], $otherContext));
        }
        $student = $this->student($school, '2026/2027');
        $this->actingAs($user)->postJson(route('bereichsauswahl.bop.radio.update'), [
            'teilnehmer_id' => $student->id, 'choices' => array_slice($areas, 0, 2),
        ])->assertOk();
        $this->assertDatabaseCount('bereichsauswahl_settings', 5);
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $school = Partner::create(['name' => 'Testschule']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $project->partners()->attach($school->id);
        foreach (['bereichsauswahl.index', 'bereichsauswahl.planning', 'bereichsauswahl.store', 'bereichsauswahl.update', 'einteilung.index'] as $permission) {
            $this->grantTestPermission($user, $permission);
        }
        $areas = [];
        foreach (['IT', 'Holz', 'Metall', 'Farbe'] as $name) {
            $areas[] = Bereich::create(['name' => $name])->id;
        }
        $project->bereiche()->attach($areas);

        return [$user, $project, $school, $areas];
    }

    private function student(Partner $school, string $year, string $part = '1'): PersonenIstSchueler
    {
        return PersonenIstSchueler::create([
            'person_id' => Personen::factory()->create(['typ' => 'teilnehmer'])->id,
            'schule_id' => $school->id, 'schuljahr' => $year, 'teil' => $part, 'klasse' => '8',
        ]);
    }

    private function setting(Projekt $project, Partner $school, string $year, int $count, ?User $editor = null): BereichsauswahlSetting
    {
        return BereichsauswahlSetting::create([
            'projekt_id' => $project->id, 'partner_id' => $school->id, 'schuljahr' => $year, 'teil' => '1',
            'auswahl_anzahl' => $count, 'zugang_aktiv' => true,
            'public_token' => \Illuminate\Support\Str::random(40), 'user_update' => $editor?->id,
        ]);
    }
}
