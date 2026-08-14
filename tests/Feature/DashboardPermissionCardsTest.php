<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Bereich;
use App\Models\Gruppe;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Raeume;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardPermissionCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_does_not_send_cards_or_counts_without_domain_permissions(): void
    {
        $user = $this->userWithPermissions(['dashboard.index']);

        Projekt::factory()->create();
        Personen::factory()->count(3)->create(['typ' => 'teilnehmer']);
        Partner::query()->create(['name' => 'Nicht sichtbare Schule']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardCards', [])
                ->where('apps.participants', 0)
            );
    }

    public function test_dashboard_lists_only_authorized_scoped_resources_and_limits_recent_participants(): void
    {
        $user = $this->userWithPermissions([
            'dashboard.index',
            'projekt.index',
            'kooperationspartner.index',
            'gruppe.index',
            'gruppe.view.all',
            'gruppeHasTeilnehmer.show',
            'teilnehmer.index',
            'teilnehmer.update',
        ], 'all');
        $project = Projekt::factory()->create(['name' => 'BOP Dashboard']);
        $this->assignToProject($user->person, $project);
        $user->update(['current_team_id' => $project->id]);

        $school = Partner::query()->create(['name' => 'Schule Alpha']);
        Partner::query()->create(['name' => 'Fremde Schule']);
        $project->partners()->attach($school->id);

        $area = Bereich::query()->create(['name' => 'Verkauf']);
        $group = $this->group($project, $user->person, $area, '2026-08-14');
        $group->partners()->attach($school->id);

        $participants = collect();
        foreach (range(1, 55) as $index) {
            $participants->push(Personen::factory()->create([
                'typ' => 'teilnehmer',
                'vorname' => 'Person',
                'nachname' => str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            ]));
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboardCards.projects')
                ->where('dashboardCards.participants.value', 55)
                ->has('dashboardCards.partners.items', 1)
                ->where('dashboardCards.partners.items.0.id', $school->id)
                ->where('dashboardCards.partners.can_open', true)
                ->has('dashboardCards.groups.items', 1)
                ->where('dashboardCards.groups.items.0.id', $group->id)
                ->where('dashboardCards.groups.can_open', true)
                ->has('dashboardCards.recent_participants.items', 50)
                ->where('dashboardCards.recent_participants.items.0.id', $participants->last()->id)
                ->where('dashboardCards.recent_participants.can_open', true)
                ->missing('dashboardCards.rooms')
                ->missing('dashboardCards.vehicles')
                ->missing('dashboardCards.devices')
            );
    }

    public function test_partner_filter_on_group_page_returns_only_groups_of_that_school(): void
    {
        $user = $this->userWithPermissions([
            'gruppe.index',
            'gruppe.view.all',
        ]);
        $project = Projekt::factory()->create();
        $this->assignToProject($user->person, $project);
        $user->update(['current_team_id' => $project->id]);

        $schoolA = Partner::query()->create(['name' => 'Schule A']);
        $schoolB = Partner::query()->create(['name' => 'Schule B']);
        $project->partners()->attach([$schoolA->id, $schoolB->id]);
        $area = Bereich::query()->create(['name' => 'Technik']);

        $groupA = $this->group($project, $user->person, $area, '2026-08-14');
        $groupB = $this->group($project, $user->person, $area, '2026-08-15');
        $groupA->partners()->attach($schoolA->id);
        $groupB->partners()->attach($schoolB->id);

        $this->actingAs($user)
            ->get(route('gruppe.index', ['partner_id' => $schoolA->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('gruppen', 1)
                ->where('gruppen.0.id', $groupA->id)
                ->where('filters.partner_id', $schoolA->id)
                ->where('filters.partner.name', 'Schule A')
            );
    }

    public function test_new_list_cards_can_be_saved_as_hidden_preferences(): void
    {
        $user = $this->userWithPermissions(['dashboard.index']);

        $this->actingAs($user)
            ->put(route('dashboard.preferences.update'), [
                'hidden_cards' => ['partners', 'groups', 'recent_participants'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('dashboard_preferences', [
            'user_id' => $user->id,
            'hidden_cards' => json_encode(['partners', 'groups', 'recent_participants']),
        ]);
    }

    private function userWithPermissions(array $permissions, string $participantScope = 'none'): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Dashboard-'.uniqid(),
            'guard_name' => 'web',
            'color' => '#2563eb',
        ]);
        RoleDataAccessSetting::query()->create([
            'role_id' => $role->id,
            'team_scope' => 'own_projects',
            'participant_scope' => $participantScope,
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
            ['name' => 'Dashboard'],
            ['beschreibung' => ''],
        );
        $permission = Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null],
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

    private function group(Projekt $project, Personen $instructor, Bereich $area, string $date): Gruppe
    {
        $location = Standort::factory()->create();
        $room = Raeume::query()->create([
            'standort_id' => $location->id,
            'name' => 'Dashboard Testraum',
            'typ' => 'Büro',
        ]);

        return Gruppe::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $instructor->id,
            'bereich_id' => $area->id,
            'raum_id' => $room->id,
            'standort_id' => $location->id,
            'anfangsdatum' => $date,
            'enddatum' => $date,
            'startzeit' => '08:00',
            'endzeit' => '12:00',
        ]);
    }
}
