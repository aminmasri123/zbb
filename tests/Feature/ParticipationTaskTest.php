<?php

namespace Tests\Feature;

use App\Models\AppTask;
use App\Models\Berechtigungskategorie;
use App\Models\Personen;
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

class ParticipationTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_and_assignees_are_bound_to_the_active_project_participation(): void
    {
        $user = User::factory()->create();
        $this->grantParticipantAccess($user);
        $this->givePermission($user, 'teilnehmer.update');
        $location = Standort::factory()->create();
        $activeProject = Projekt::factory()->create();
        $foreignProject = Projekt::factory()->create();
        $this->assign($activeProject, $user->person, $location);
        $this->assign($foreignProject, $user->person, $location);
        $user->update(['current_team_id' => $activeProject->id]);

        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $activeParticipation = $this->assign($activeProject, $participant, $location);
        $activeAssignee = Personen::factory()->create(['typ' => 'mitarbeiter']);
        $foreignAssignee = Personen::factory()->create(['typ' => 'mitarbeiter']);
        $this->assign($activeProject, $activeAssignee, $location);
        $this->assign($foreignProject, $foreignAssignee, $location);

        $payload = [
            'title' => 'Unterlagen prüfen',
            'description' => 'Nur sachliche Unterlagenprüfung',
            'assignee_person_id' => $activeAssignee->id,
            'status' => 'open',
            'priority' => 'high',
            'due_at' => now()->addDay()->toDateString(),
            'visible_to_participant' => true,
        ];

        $this->actingAs($user)->postJson(route('teilnehmer.tasks.store', $activeParticipation), $payload)
            ->assertCreated()
            ->assertJsonPath('task.project_person_id', $activeParticipation->id)
            ->assertJsonPath('task.project_id', $activeProject->id);

        $task = AppTask::query()->firstOrFail();
        $this->assertSame('project', $task->visibility);

        $this->actingAs($user)->postJson(route('teilnehmer.tasks.store', $activeParticipation), [
            ...$payload,
            'assignee_person_id' => $foreignAssignee->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('assignee_person_id');

        $this->actingAs($user)->putJson(route('teilnehmer.tasks.update', $task), [
            ...$payload,
            'status' => 'done',
        ])->assertOk()->assertJsonPath('task.status', 'done');
        $this->assertNotNull($task->fresh()->completed_at);

        $user->update(['current_team_id' => $foreignProject->id]);
        $this->actingAs($user)->putJson(route('teilnehmer.tasks.update', $task), $payload)->assertNotFound();
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
        $category = Berechtigungskategorie::query()->firstOrCreate(['name' => 'Teilnahmeaufgaben'], ['beschreibung' => '']);
        $permission = Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo($permission);
    }

    private function grantParticipantAccess(User $user): void
    {
        $role = Role::query()->create(['name' => 'Aufgaben-Test-' . uniqid(), 'guard_name' => 'web', 'color' => '#2563eb']);
        RoleDataAccessSetting::query()->create([
            'role_id' => $role->id,
            'team_scope' => 'own_projects',
            'participant_scope' => 'all',
        ]);
        $user->assignRole($role);
    }
}
