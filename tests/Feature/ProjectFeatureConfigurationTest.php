<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectFeatureConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_projects_receive_safe_feature_defaults(): void
    {
        $project = Projekt::factory()->create([
            'feature_settings' => null,
            'klassenbuch_aktiv' => false,
            'potenzialanalyse_aktiv' => true,
        ]);

        $this->assertTrue($project->featureEnabled('participant_management'));
        $this->assertTrue($project->featureEnabled('group_management'));
        $this->assertFalse($project->featureEnabled('classbook_management'));
        $this->assertTrue($project->featureEnabled('potential_analysis'));
    }

    public function test_project_features_are_saved_directly_on_the_project(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'projekt.update');
        $project = Projekt::factory()->create();

        $this->actingAs($user)->putJson(route('projekt.features.update', $project), [
            'features' => $this->features([
                'group_management' => true,
                'classbook_management' => true,
                'potential_analysis' => true,
            ]),
            'potenzialanalyse_tage' => 4,
        ])->assertOk()
            ->assertJsonPath('features.group_management', true)
            ->assertJsonPath('features.classbook_management', true);

        $project->refresh();
        $this->assertTrue($project->featureEnabled('group_management'));
        $this->assertTrue($project->klassenbuch_aktiv);
        $this->assertTrue($project->potenzialanalyse_aktiv);
        $this->assertSame(4, $project->potenzialanalyse_tage);
    }

    public function test_disabled_project_feature_blocks_backend_for_active_project(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'gruppe.index');
        $project = Projekt::factory()->create([
            'feature_settings' => $this->features(['group_management' => false]),
        ]);
        $this->assignUser($user, $project);
        $user->update(['current_team_id' => $project->id]);

        $this->actingAs($user)->get(route('gruppe.index'))->assertNotFound();
    }

    public function test_feature_dependencies_are_enforced(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'projekt.update');
        $project = Projekt::factory()->create();
        $features = $this->features([
            'participant_management' => false,
            'group_management' => true,
        ]);

        $this->actingAs($user)->putJson(route('projekt.features.update', $project), [
            'features' => $features,
            'potenzialanalyse_tage' => null,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('features.group_management');

        $project->update(['feature_settings' => $features]);
        $this->assertFalse($project->fresh()->featureEnabled('group_management'));
    }

    public function test_feature_state_is_exposed_in_active_header_project(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'dashboard.index');
        $project = Projekt::factory()->create([
            'feature_settings' => $this->features(['attendance_management' => false]),
        ]);
        $this->assignUser($user, $project);
        $user->update(['current_team_id' => $project->id]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('currentProjekt.features.attendance_management', false)
                ->where('currentProjekt.features.participant_management', true));
    }

    private function features(array $overrides = []): array
    {
        return array_replace(Projekt::FEATURE_DEFAULTS, [
            'classbook_management' => false,
            'potential_analysis' => false,
        ], $overrides);
    }

    private function assignUser(User $user, Projekt $project): void
    {
        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $user->person_id,
            'standort_id' => Standort::factory()->create()->id,
            'status' => 'aktiv',
        ]);
    }

    private function givePermission(User $user, string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Projektfunktionen'],
            ['beschreibung' => '']
        );
        $permission = Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo($permission);
    }
}
