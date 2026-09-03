<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Anwesenheitsstatuten;
use App\Models\Bereich;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Raeume;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Standort;
use App\Models\Tage;
use App\Models\User;
use App\Models\Zeiten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ParticipantListEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_filter_only_returns_participants_of_selected_partner(): void
    {
        $user = $this->userWithParticipantAccess(['teilnehmer.index']);
        $project = Projekt::factory()->create();
        $this->assignToProject($user->person, $project);
        $user->update(['current_team_id' => $project->id]);

        $schoolA = Partner::query()->create(['name' => 'Schule Alpha']);
        $schoolB = Partner::query()->create(['name' => 'Schule Beta']);
        $project->partners()->attach([$schoolA->id, $schoolB->id]);

        $alpha = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Anna', 'nachname' => 'Alpha']);
        $beta = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Berta', 'nachname' => 'Beta']);
        $this->assignToProject($alpha, $project);
        $this->assignToProject($beta, $project);
        $this->assignSchool($alpha, $schoolA);
        $this->assignSchool($beta, $schoolB);

        $this->actingAs($user)
            ->get(route('teilnehmer.index', ['schule' => $schoolA->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.schule', $schoolA->id)
                ->has('teilnehmers.data', 1)
                ->where('teilnehmers.data.0.id', $alpha->id)
                ->has('participantSchools', 2)
            );
    }

    public function test_authorized_user_can_swap_names_for_visible_project_participants(): void
    {
        $user = $this->userWithParticipantAccess(['teilnehmer.update']);
        $project = Projekt::factory()->create();
        $this->assignToProject($user->person, $project);
        $user->update(['current_team_id' => $project->id]);

        $participant = Personen::factory()->create([
            'typ' => 'teilnehmer',
            'vorname' => 'Mustermann',
            'nachname' => 'Max',
        ]);
        $this->assignToProject($participant, $project);

        $this->actingAs($user)
            ->patchJson(route('teilnehmer.names.swap'), ['ids' => [$participant->id]])
            ->assertOk()
            ->assertJsonPath('participants.0.vorname', 'Max')
            ->assertJsonPath('participants.0.nachname', 'Mustermann');

        $this->assertDatabaseHas('personens', [
            'id' => $participant->id,
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
        ]);
    }

    public function test_authorized_group_filters_return_participants_for_selected_instructor_and_area(): void
    {
        $user = $this->userWithParticipantAccess(['teilnehmer.index', 'gruppe.view.all']);
        $project = Projekt::factory()->create();
        $this->assignToProject($user->person, $project);
        $user->update(['current_team_id' => $project->id]);

        $instructor = Personen::factory()->create([
            'typ' => 'mitarbeiter',
            'vorname' => 'Ada',
            'nachname' => 'Anleitung',
        ]);
        $this->assignToProject($instructor, $project);
        $area = Bereich::query()->create(['name' => 'Verkauf']);
        $group = $this->group($project, $instructor, $area);

        $matching = Personen::factory()->create(['typ' => 'teilnehmer', 'nachname' => 'Passend']);
        $other = Personen::factory()->create(['typ' => 'teilnehmer', 'nachname' => 'Andere']);
        $this->assignToProject($matching, $project);
        $this->assignToProject($other, $project);
        $this->assignToGroup($matching, $group, $user);

        $this->actingAs($user)
            ->get(route('teilnehmer.index', [
                'anleiter' => $instructor->id,
                'bereich' => $area->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canUseAdvancedGroupFilters', true)
                ->where('filters.anleiter', $instructor->id)
                ->where('filters.bereich', $area->id)
                ->has('teilnehmers.data', 1)
                ->where('teilnehmers.data.0.id', $matching->id)
                ->where('anleiter.0.id', $instructor->id)
                ->where('bereiche.0.id', $area->id)
            );
    }

    public function test_group_page_orders_all_participants_by_last_name(): void
    {
        $user = $this->userWithParticipantAccess(['anwesenheit.index']);
        $project = Projekt::factory()->create();
        $this->assignToProject($user->person, $project);
        $user->update(['current_team_id' => $project->id]);
        $area = Bereich::query()->create(['name' => 'Technik']);
        $group = $this->group($project, $user->person, $area);

        $zebra = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Zora', 'nachname' => 'Zebra']);
        $adler = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Anton', 'nachname' => 'Adler']);
        $this->assignToProject($zebra, $project);
        $this->assignToProject($adler, $project);
        $this->assignToGroup($zebra, $group, $user);
        $this->assignToGroup($zebra, $group, $user, '2026-08-15');
        $this->assignToGroup($adler, $group, $user);

        $this->actingAs($user)
            ->get(route('gruppeHasTeilnehmer.show', $group->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('gruppe.teilnehmer', 2)
                ->where('gruppe.teilnehmer.0.id', $adler->id)
                ->where('gruppe.teilnehmer.1.id', $zebra->id)
                ->has('gruppe.teilnehmer.0.anwesenheit_eintraege', 1)
                ->has('gruppe.teilnehmer.1.anwesenheit_eintraege', 2)
            );
    }

    public function test_bvb_reha_group_exposes_pa_only_when_luv_and_pa_are_enabled(): void
    {
        $user = $this->userWithParticipantAccess(['potenzialanalyse.index']);
        $project = Projekt::factory()->create([
            'name' => 'BvB Reha',
            'potenzialanalyse_aktiv' => true,
        ]);
        $this->assignToProject($user->person, $project);
        $user->update(['current_team_id' => $project->id]);
        $group = $this->group(
            $project,
            $user->person,
            Bereich::query()->create(['name' => 'Allgemeine BvB-Gruppe']),
        );

        $this->actingAs($user)
            ->get(route('gruppeHasTeilnehmer.show', $group->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('potenzialanalyse.aktiv', true)
                ->where('potenzialanalyse.luv_foerderbedarf_aktiv', true)
            );

        $project->update([
            'participant_profile_settings' => [
                'enabled_tabs' => ['stammdaten'],
                'tab_order' => Projekt::participantProfileTabKeys(),
            ],
        ]);

        $this->actingAs($user)
            ->get(route('gruppeHasTeilnehmer.show', $group->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('potenzialanalyse.aktiv', false)
                ->where('potenzialanalyse.luv_foerderbedarf_aktiv', false)
            );
    }

    public function test_group_overview_orders_newest_start_date_first(): void
    {
        $user = $this->userWithParticipantAccess(['gruppe.index', 'gruppe.view.all']);
        $project = Projekt::factory()->create();
        $this->assignToProject($user->person, $project);
        $user->update(['current_team_id' => $project->id]);

        $oldest = $this->group(
            $project,
            $user->person,
            Bereich::query()->create(['name' => 'Alt']),
            '2026-08-12'
        );
        $newest = $this->group(
            $project,
            $user->person,
            Bereich::query()->create(['name' => 'Neu']),
            '2026-08-14'
        );
        $middle = $this->group(
            $project,
            $user->person,
            Bereich::query()->create(['name' => 'Mitte']),
            '2026-08-13'
        );

        $this->actingAs($user)
            ->get(route('gruppe.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('gruppen', 3)
                ->where('gruppen.0.id', $newest->id)
                ->where('gruppen.1.id', $middle->id)
                ->where('gruppen.2.id', $oldest->id)
            );
    }

    public function test_name_swap_is_atomic_when_selection_contains_foreign_project_participant(): void
    {
        $user = $this->userWithParticipantAccess(['teilnehmer.update']);
        $activeProject = Projekt::factory()->create();
        $foreignProject = Projekt::factory()->create();
        $this->assignToProject($user->person, $activeProject);
        $user->update(['current_team_id' => $activeProject->id]);

        $visible = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Vor', 'nachname' => 'Nach']);
        $foreign = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Fremd', 'nachname' => 'Person']);
        $this->assignToProject($visible, $activeProject);
        $this->assignToProject($foreign, $foreignProject);

        $this->actingAs($user)
            ->patchJson(route('teilnehmer.names.swap'), ['ids' => [$visible->id, $foreign->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('personens', [
            'id' => $visible->id,
            'vorname' => 'Vor',
            'nachname' => 'Nach',
        ]);
    }

    private function userWithParticipantAccess(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Teilnehmerliste-'.uniqid(),
            'guard_name' => 'web',
            'color' => '#2563eb',
        ]);
        RoleDataAccessSetting::query()->create([
            'role_id' => $role->id,
            'team_scope' => 'own_projects',
            'participant_scope' => 'all',
        ]);
        $user->assignRole($role);

        foreach ($permissions as $permission) {
            $this->givePermission($user, $permission);
        }

        return $user;
    }

    private function givePermission(User $user, string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Teilnehmerliste'],
            ['beschreibung' => '']
        );
        $permission = Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo($permission);
    }

    private function assignToProject(Personen $person, Projekt $project): void
    {
        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $person->id,
            'standort_id' => Standort::factory()->create()->id,
            'status' => 'aktiv',
        ]);
    }

    private function assignSchool(Personen $person, Partner $school): void
    {
        PersonenIstSchueler::query()->create([
            'person_id' => $person->id,
            'schule_id' => $school->id,
            'klasse' => '7.1',
            'schuljahr' => '2026/2027',
            'teil' => '1',
        ]);
    }

    private function group(
        Projekt $project,
        Personen $instructor,
        Bereich $area,
        string $startDate = '2026-08-14',
        string $startTime = '08:00'
    ): Gruppe
    {
        $location = Standort::factory()->create();
        $room = Raeume::query()->create([
            'standort_id' => $location->id,
            'name' => 'Testraum',
            'typ' => 'Büro',
        ]);

        return Gruppe::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $instructor->id,
            'bereich_id' => $area->id,
            'raum_id' => $room->id,
            'standort_id' => $location->id,
            'anfangsdatum' => $startDate,
            'enddatum' => $startDate,
            'startzeit' => $startTime,
            'endzeit' => '12:00',
        ]);
    }

    private function assignToGroup(Personen $person, Gruppe $group, User $user, string $date = '2026-08-14'): void
    {
        $day = Tage::query()->firstOrCreate([
            'datum' => $date,
            'wochentag' => 'Freitag',
        ]);
        $time = Zeiten::query()->firstOrCreate([
            'startzeit' => '08:00',
            'endzeit' => '12:00',
        ]);
        $status = Anwesenheitsstatuten::query()->firstOrCreate([
            'status' => 'anwesend',
        ], [
            'farben' => '#16a34a',
            'abkuerzung' => 'A',
        ]);

        GruppeHasPersonen::query()->create([
            'personen_id' => $person->id,
            'gruppe_id' => $group->id,
            'user_id' => $user->id,
            'tage_id' => $day->id,
            'zeitgeplant_id' => $time->id,
            'anwesenheitsstatuten_id' => $status->id,
        ]);
    }
}
