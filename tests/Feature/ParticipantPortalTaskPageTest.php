<?php

namespace Tests\Feature;

use App\Models\AppTask;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Standort;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Modules\ModuleStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantPortalTaskPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_sees_only_released_tasks_from_own_active_enabled_participations(): void
    {
        $staff = User::factory()->create();
        app(ModuleStateResolver::class)->set(
            SystemModule::query()->where('key', 'participant_portal')->firstOrFail(),
            true,
            null,
            $staff->id,
        );

        $location = Standort::factory()->create();
        $project = Projekt::factory()->create([
            'feature_settings' => ['participant_management' => true, 'participant_portal' => true],
            'portal_feature_settings' => ['tasks_and_appointments' => true],
        ]);
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $participation = $this->assign($project, $participant, $location);
        $portalUser = User::factory()->create(['person_id' => $participant->id]);

        $openTask = $this->task($staff, $project, $participation, [
            'title' => 'Unterlagen einreichen',
            'description' => 'Bitte zum nächsten Termin mitbringen.',
            'status' => 'open',
            'priority' => 'high',
            'due_at' => today()->addDay(),
            'visible_to_participant' => true,
        ]);
        $doneTask = $this->task($staff, $project, $participation, [
            'title' => 'Fragebogen ausfüllen',
            'status' => 'done',
            'visible_to_participant' => true,
        ]);
        $this->task($staff, $project, $participation, [
            'title' => 'Interne Aufgabe',
            'visible_to_participant' => false,
        ]);

        $otherParticipant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $otherParticipation = $this->assign($project, $otherParticipant, $location);
        $this->task($staff, $project, $otherParticipation, [
            'title' => 'Fremde Aufgabe',
            'visible_to_participant' => true,
        ]);

        $this->actingAs($portalUser)
            ->get(route('participant-portal.tasks.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ParticipantPortal/Tasks')
                ->has('tasks', 2)
                ->where('tasks.0.id', $openTask->id)
                ->where('tasks.0.participation.projekt.name', $project->name)
                ->where('tasks.1.id', $doneTask->id)
                ->where('participantPortalNavigation.tasks_and_appointments', true));
    }

    public function test_task_page_requires_the_project_feature(): void
    {
        $staff = User::factory()->create();
        app(ModuleStateResolver::class)->set(
            SystemModule::query()->where('key', 'participant_portal')->firstOrFail(),
            true,
            null,
            $staff->id,
        );

        $project = Projekt::factory()->create([
            'feature_settings' => ['participant_management' => true, 'participant_portal' => true],
            'portal_feature_settings' => ['tasks_and_appointments' => false],
        ]);
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $this->assign($project, $participant, Standort::factory()->create());
        $portalUser = User::factory()->create(['person_id' => $participant->id]);

        $this->actingAs($portalUser)->get(route('participant-portal.tasks.index'))->assertNotFound();
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

    private function task(User $owner, Projekt $project, ProjektHasPersonen $participation, array $attributes): AppTask
    {
        return AppTask::query()->create([
            'owner_user_id' => $owner->id,
            'project_id' => $project->id,
            'project_person_id' => $participation->id,
            'title' => 'Aufgabe',
            'status' => 'open',
            'priority' => 'normal',
            'visibility' => 'project',
            'visible_to_participant' => false,
            ...$attributes,
        ]);
    }
}
