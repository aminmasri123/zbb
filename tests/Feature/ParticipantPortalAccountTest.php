<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\ParticipantPortalInvitation;
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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ParticipantPortalAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_activation_dashboard_and_self_service_profile_are_participant_bound(): void
    {
        $staff = User::factory()->create();
        $this->givePermission($staff, 'teilnehmer.update');
        $this->grantParticipantAccess($staff);
        app(ModuleStateResolver::class)->set(SystemModule::where('key', 'participant_portal')->firstOrFail(), true, null, $staff->id);

        $location = Standort::factory()->create();
        $project = Projekt::factory()->create();
        $this->assign($project, $staff->person, $location);
        $staff->update(['current_team_id' => $project->id]);
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $participation = $this->assign($project, $participant, $location);

        $response = $this->actingAs($staff)->postJson(route('teilnehmer.portal.invite', $participation), [
            'email' => 'teilnehmer@example.test',
        ])->assertCreated();

        $url = $response->json('invitation_url');
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->assertNotSame('', $token);
        $this->assertDatabaseHas('participant_portal_invitations', [
            'project_person_id' => $participation->id,
            'email' => 'teilnehmer@example.test',
        ]);

        $this->get(route('participant-portal.invitation.show', $token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('participantName', $participant->vorname . ' ' . $participant->nachname));

        $this->post(route('participant-portal.invitation.accept', $token), [
            'password' => 'StrongPortal123',
            'password_confirmation' => 'StrongPortal123',
        ])->assertRedirect(route('participant-portal.dashboard'));

        $portalUser = User::query()->where('person_id', $participant->id)->firstOrFail();
        $this->assertAuthenticatedAs($portalUser);
        $this->assertNotNull(ParticipantPortalInvitation::query()->firstOrFail()->accepted_at);
        $this->assertDatabaseHas('participant_portal_profiles', ['person_id' => $participant->id]);

        $this->get(route('participant-portal.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('participant.id', $participant->id)
                ->has('participations', 1)
                ->where('participations.0.projekt_id', $project->id));

        $this->putJson(route('participant-portal.profile.update'), [
            'professional_headline' => 'Fachkraft im Gartenbau',
            'career_goal' => 'Ausbildung finden',
            'skills' => 'Teamarbeit, Pflanzenpflege',
            'interests' => 'Natur',
            'available_from' => '2026-08-01',
            'job_search_radius_km' => 25,
            'profile_visible_to_project_staff' => true,
        ])->assertOk();

        $this->assertDatabaseHas('participant_portal_profiles', [
            'person_id' => $participant->id,
            'professional_headline' => 'Fachkraft im Gartenbau',
            'job_search_radius_km' => 25,
        ]);

        $this->actingAs($staff)->get(route('participant-portal.dashboard'))->assertForbidden();
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
        $category = Berechtigungskategorie::query()->firstOrCreate(['name' => 'Portal'], ['beschreibung' => '']);
        Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo($name);
    }

    private function grantParticipantAccess(User $user): void
    {
        $role = Role::query()->create(['name' => 'Portal-Test-' . uniqid(), 'guard_name' => 'web', 'color' => '#2563eb']);
        RoleDataAccessSetting::query()->create(['role_id' => $role->id, 'team_scope' => 'own_projects', 'participant_scope' => 'all']);
        $user->assignRole($role);
    }
}
