<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\ParticipantPortalMessage;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Standort;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Modules\ModuleStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ParticipantPortalMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_and_staff_can_exchange_and_read_project_bound_messages(): void
    {
        $staff = User::factory()->create();
        $this->grantStaffPermission($staff);
        $this->enablePortal($staff);
        $location = Standort::factory()->create();
        $project = Projekt::factory()->create(['portal_feature_settings' => ['messaging' => true]]);
        $this->assign($project, $staff->person, $location);
        $staff->update(['current_team_id' => $project->id]);
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $participation = $this->assign($project, $participant, $location);
        $portal = User::factory()->create(['person_id' => $participant->id]);

        $participantResponse = $this->actingAs($portal)->postJson(route('participant-portal.messages.store'), [
            'project_person_id' => $participation->id,
            'body' => 'Ich habe eine Frage zu meinem Termin.',
        ])->assertCreated()->assertJsonPath('item.sender_kind', 'participant');

        $participantMessage = ParticipantPortalMessage::findOrFail($participantResponse->json('item.id'));
        $this->assertNull($participantMessage->staff_read_at);
        $this->actingAs($staff)->putJson(route('teilnehmer.messages.read', $participation))->assertOk();
        $this->assertNotNull($participantMessage->fresh()->staff_read_at);

        $staffResponse = $this->actingAs($staff)->postJson(route('teilnehmer.messages.store', $participation), [
            'body' => 'Wir besprechen das morgen gemeinsam.',
        ])->assertCreated()->assertJsonPath('item.sender_kind', 'staff');

        $staffMessage = ParticipantPortalMessage::findOrFail($staffResponse->json('item.id'));
        $this->assertNull($staffMessage->participant_read_at);
        $this->actingAs($portal)->get(route('participant-portal.dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('ParticipantPortal/Dashboard')
            ->where('unreadMessageCount', 1)
            ->has('reminders', 1)
            ->where('reminders.0.type', 'message'));
        $this->actingAs($portal)->putJson(route('participant-portal.messages.read', $participation))->assertOk();
        $this->assertNotNull($staffMessage->fresh()->participant_read_at);
    }

    public function test_foreign_participations_and_disabled_project_feature_are_hidden(): void
    {
        $staff = User::factory()->create();
        $this->grantStaffPermission($staff);
        $this->enablePortal($staff);
        $location = Standort::factory()->create();
        $project = Projekt::factory()->create(['portal_feature_settings' => ['messaging' => false]]);
        $this->assign($project, $staff->person, $location);
        $staff->update(['current_team_id' => $project->id]);
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $participation = $this->assign($project, $participant, $location);
        $portal = User::factory()->create(['person_id' => $participant->id]);

        $this->actingAs($portal)->postJson(route('participant-portal.messages.store'), [
            'project_person_id' => $participation->id,
            'body' => 'Nicht erlaubt',
        ])->assertNotFound();

        $other = Personen::factory()->create(['typ' => 'teilnehmer']);
        $otherPortal = User::factory()->create(['person_id' => $other->id]);
        $project->update(['portal_feature_settings' => ['messaging' => true]]);
        $this->actingAs($otherPortal)->postJson(route('participant-portal.messages.store'), [
            'project_person_id' => $participation->id,
            'body' => 'Fremdzugriff',
        ])->assertNotFound();
        $this->assertDatabaseCount('participant_portal_messages', 0);
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

    private function enablePortal(User $user): void
    {
        app(ModuleStateResolver::class)->set(
            SystemModule::query()->where('key', 'participant_portal')->firstOrFail(),
            true,
            null,
            $user->id
        );
    }

    private function grantStaffPermission(User $user): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(['name' => 'Portal-Nachrichten'], ['beschreibung' => '']);
        Permission::query()->updateOrCreate(
            ['name' => 'teilnehmer.update', 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::query()->create(['name' => 'Nachrichten-' . uniqid(), 'guard_name' => 'web', 'color' => '#123456']);
        RoleDataAccessSetting::query()->create(['role_id' => $role->id, 'team_scope' => 'own_projects', 'participant_scope' => 'all']);
        $user->assignRole($role);
        $user->givePermissionTo('teilnehmer.update');
    }
}
