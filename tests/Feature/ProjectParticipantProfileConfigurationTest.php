<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectParticipantProfileConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_settings_migration_is_safe_to_run_again(): void
    {
        $this->assertTrue(Schema::hasColumn('projekts', 'participant_profile_settings'));

        $migration = require database_path(
            'migrations/2026_08_13_231000_add_participant_profile_settings_to_projects.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumn('projekts', 'participant_profile_settings'));
    }

    public function test_project_profile_tabs_are_saved_and_stammdaten_remains_required(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'projekt.update');
        $project = Projekt::factory()->create();
        $keys = Projekt::participantProfileTabKeys();
        $order = ['stammdaten', 'kontaktdaten', 'adresse', ...array_values(array_diff($keys, ['stammdaten', 'kontaktdaten', 'adresse']))];

        $this->actingAs($user)->putJson(route('projekt.participant-profile.update', $project), [
            'enabled_tabs' => ['stammdaten', 'kontaktdaten', 'adresse'],
            'tab_order' => $order,
        ])->assertOk()
            ->assertJsonPath('participant_profile.enabled_tabs.0', 'stammdaten')
            ->assertJsonPath('participant_profile.enabled_tabs.1', 'kontaktdaten');

        $this->assertSame(
            ['stammdaten', 'kontaktdaten', 'adresse'],
            $project->fresh()->participantProfileSettings()['enabled_tabs']
        );

        $this->actingAs($user)->putJson(route('projekt.participant-profile.update', $project), [
            'enabled_tabs' => ['adresse'],
            'tab_order' => $order,
        ])->assertUnprocessable()->assertJsonValidationErrors('enabled_tabs');
    }

    public function test_participant_edit_receives_only_the_active_projects_profile_configuration(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'teilnehmer.update');
        $location = Standort::factory()->create();
        $project = Projekt::factory()->create([
            'participant_profile_settings' => [
                'enabled_tabs' => ['stammdaten', 'notizen'],
                'tab_order' => Projekt::participantProfileTabKeys(),
            ],
        ]);
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $this->assign($project, $user->person, $location);
        $this->assign($project, $participant, $location);
        $user->update(['current_team_id' => $project->id]);

        $this->actingAs($user)->get(route('teilnehmer.edit', $participant->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('participantProfile.enabled_tabs', ['stammdaten', 'notizen'])
                ->has('participantProfile.definitions', count(Projekt::participantProfileTabKeys())));
    }

    private function assign(Projekt $project, Personen $person, Standort $location): void
    {
        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $person->id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);
    }

    private function givePermission(User $user, string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Projektkonfiguration'],
            ['beschreibung' => '']
        );
        $permission = Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        if (! $user->roles()->exists()) {
            $role = Role::query()->create([
                'name' => 'Teilnehmerprofil-'.uniqid(),
                'guard_name' => 'web',
                'color' => '#123456',
            ]);
            RoleDataAccessSetting::query()->create([
                'role_id' => $role->id,
                'team_scope' => 'own_projects',
                'participant_scope' => 'all',
            ]);
            $user->assignRole($role);
        }
        $user->givePermissionTo($permission);
    }
}
