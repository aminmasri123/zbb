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

class ParticipantPortalNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_portal_features_create_deduplicated_notifications(): void
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
            'portal_feature_settings' => [
                'profile' => false,
                'attendance_self_service' => false,
                'tasks_and_appointments' => true,
                'job_search' => false,
                'application_management' => false,
                'learning' => false,
                'messaging' => false,
                'consents_and_approvals' => false,
            ],
        ]);
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $participation = ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $participant->id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);
        $portalUser = User::factory()->create(['person_id' => $participant->id]);

        AppTask::query()->create([
            'owner_user_id' => $staff->id,
            'project_id' => $project->id,
            'project_person_id' => $participation->id,
            'title' => 'Unterlagen einreichen',
            'status' => 'open',
            'due_at' => now()->addDay(),
            'visibility' => 'project',
            'visible_to_participant' => true,
        ]);

        $this->actingAs($portalUser)
            ->get(route('participant-portal.notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('notifications.data', 1)
                ->where('notifications.data.0.message', 'Unterlagen einreichen')
                ->where('unreadCount', 1)
                ->where('participantPortalNavigation.tasks_and_appointments', true)
                ->where('participantPortalNavigation.profile', false));

        $this->get(route('participant-portal.notifications.index'))->assertOk();
        $this->assertDatabaseCount('notifications', 1);

        $notificationId = $portalUser->notifications()->firstOrFail()->id;
        $this->post(route('participant-portal.notifications.read', $notificationId))->assertRedirect();
        $this->assertNotNull($portalUser->notifications()->firstOrFail()->read_at);

        $project->update(['portal_feature_settings' => ['tasks_and_appointments' => false]]);
        $this->get(route('participant-portal.self-service.index'))->assertNotFound();
        $this->get(route('participant-portal.notifications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('notifications.data', 0)->where('unreadCount', 0));
        $this->assertDatabaseCount('notifications', 0);
    }
}
