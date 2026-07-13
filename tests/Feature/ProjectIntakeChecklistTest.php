<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Personen;
use App\Models\ProjectIntakeChecklistItem;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectIntakeChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_definition_and_completion_are_bound_to_the_active_project_participation(): void
    {
        $user = User::factory()->create();
        $this->grantParticipantAccess($user);
        $this->givePermission($user, 'projekt.update');
        $this->givePermission($user, 'teilnehmer.update');

        $location = Standort::factory()->create();
        $activeProject = Projekt::factory()->create();
        $foreignProject = Projekt::factory()->create();
        $this->assign($activeProject, $user->person, $location);
        $this->assign($foreignProject, $user->person, $location);
        $user->update(['current_team_id' => $activeProject->id]);

        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $activeParticipation = $this->assign($activeProject, $participant, $location);
        $foreignParticipation = $this->assign($foreignProject, $participant, $location);

        $this->actingAs($user)->putJson(route('projekt.intake-checklist.update', $activeProject), [
            'items' => [[
                'label' => 'Stammdaten geprüft',
                'description' => 'Kontaktdaten und Zuständigkeit vollständig',
                'required' => true,
                'sort_order' => 0,
            ]],
        ])->assertOk()->assertJsonCount(1, 'items');

        $item = ProjectIntakeChecklistItem::query()->firstOrFail();

        $this->actingAs($user)->putJson(route('teilnehmer.intake-checklist.update', [$activeParticipation, $item]), [
            'completed' => true,
        ])->assertOk()->assertJsonPath('completion.completed', true);

        $this->assertDatabaseHas('participation_intake_checklist_completions', [
            'project_person_id' => $activeParticipation->id,
            'checklist_item_id' => $item->id,
            'completed' => true,
            'completed_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)->putJson(route('teilnehmer.intake-checklist.update', [$foreignParticipation, $item]), [
            'completed' => true,
        ])->assertNotFound();

        $this->actingAs($user)->putJson(route('projekt.intake-checklist.update', $foreignProject), ['items' => []])
            ->assertForbidden();

        $this->actingAs($user)->putJson(route('projekt.intake-checklist.update', $activeProject), ['items' => []])
            ->assertOk();

        $this->assertDatabaseHas('project_intake_checklist_items', ['id' => $item->id, 'active' => false]);
        $this->assertDatabaseHas('participation_intake_checklist_completions', ['checklist_item_id' => $item->id]);
    }

    private function assign(Projekt $project, Personen $person, Standort $location): ProjektHasPersonen
    {
        return ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $person->id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);
    }

    private function givePermission(User $user, string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(['name' => 'Aufnahmecheckliste'], ['beschreibung' => '']);
        $permission = Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo($permission);
    }

    private function grantParticipantAccess(User $user): void
    {
        $role = Role::query()->create(['name' => 'Aufnahme-Test-' . uniqid(), 'guard_name' => 'web', 'color' => '#2563eb']);
        RoleDataAccessSetting::query()->create([
            'role_id' => $role->id,
            'team_scope' => 'own_projects',
            'participant_scope' => 'all',
        ]);
        $user->assignRole($role);
    }
}
