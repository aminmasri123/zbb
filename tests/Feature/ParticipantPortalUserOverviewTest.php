<?php

namespace Tests\Feature;

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
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ParticipantPortalUserOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_portal_login_records_only_the_latest_successful_login(): void
    {
        $participant = Personen::factory()->create(['typ' => 'teilnehmer', 'aktiv' => true]);
        $portalUser = User::factory()->create([
            'person_id' => $participant->id,
            'email' => 'portal@example.test',
            'password' => Hash::make('StrongPortal123'),
            'portal_last_login_at' => null,
        ]);
        app(ModuleStateResolver::class)->set(
            SystemModule::query()->where('key', 'participant_portal')->firstOrFail(),
            true,
            null,
            $portalUser->id
        );

        $this->post(route('participant-portal.login.store'), [
            'email' => 'portal@example.test',
            'password' => 'falsch',
        ])->assertSessionHasErrors('email');
        $this->assertNull($portalUser->fresh()->portal_last_login_at);

        $loginAt = now()->startOfSecond();
        $this->travelTo($loginAt);
        $this->post(route('participant-portal.login.store'), [
            'email' => 'portal@example.test',
            'password' => 'StrongPortal123',
        ])->assertRedirect(route('participant-portal.dashboard'));

        $this->assertTrue($portalUser->fresh()->portal_last_login_at->equalTo($loginAt));
    }

    public function test_portal_user_overview_is_limited_to_visible_participants_in_the_active_project(): void
    {
        $staff = User::factory()->create();
        $this->grantTestPermission($staff, 'teilnehmer.index');
        $this->grantParticipantAccess($staff);
        app(ModuleStateResolver::class)->set(
            SystemModule::query()->where('key', 'participant_portal')->firstOrFail(),
            true,
            null,
            $staff->id
        );

        $location = Standort::factory()->create();
        $project = Projekt::factory()->create(['feature_settings' => ['participant_management' => true]]);
        $otherProject = Projekt::factory()->create(['feature_settings' => ['participant_management' => true]]);
        $this->assign($project, $staff->person, $location);
        $staff->update(['current_team_id' => $project->id]);

        $accountParticipant = Personen::factory()->create([
            'typ' => 'teilnehmer',
            'aktiv' => true,
            'vorname' => 'Ada',
            'nachname' => 'Portal',
        ]);
        $this->assign($project, $accountParticipant, $location);
        User::factory()->create([
            'person_id' => $accountParticipant->id,
            'email' => 'ada@example.test',
            'portal_last_login_at' => '2026-08-25 09:30:00',
        ]);

        $invitedParticipant = Personen::factory()->create([
            'typ' => 'teilnehmer',
            'aktiv' => true,
            'vorname' => 'Berta',
            'nachname' => 'Einladung',
        ]);
        $invitedParticipation = $this->assign($project, $invitedParticipant, $location);
        ParticipantPortalInvitation::query()->create([
            'project_person_id' => $invitedParticipation->id,
            'email' => 'berta@example.test',
            'token_hash' => hash('sha256', 'portal-overview-token'),
            'expires_at' => now()->addDay(),
            'invited_by_user_id' => $staff->id,
        ]);

        $foreignParticipant = Personen::factory()->create([
            'typ' => 'teilnehmer',
            'aktiv' => true,
            'vorname' => 'Nicht',
            'nachname' => 'Sichtbar',
        ]);
        $this->assign($otherProject, $foreignParticipant, $location);
        User::factory()->create([
            'person_id' => $foreignParticipant->id,
            'email' => 'fremd@example.test',
        ]);

        $this->actingAs($staff)
            ->get(route('teilnehmer.portal-users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teilnehmer/PortalUsers')
                ->where('project.id', $project->id)
                ->where('stats.accounts', 1)
                ->where('stats.used', 1)
                ->where('stats.pending_invitations', 1)
                ->has('portalUsers', 2)
                ->where('portalUsers', fn ($rows) => collect($rows)->pluck('email')->sort()->values()->all() === [
                    'ada@example.test',
                    'berta@example.test',
                ]));
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

    private function grantParticipantAccess(User $user): void
    {
        $role = Role::query()->create([
            'name' => 'Portal-Übersicht-'.uniqid(),
            'guard_name' => 'web',
            'color' => '#2563eb',
        ]);
        RoleDataAccessSetting::query()->create([
            'role_id' => $role->id,
            'team_scope' => 'own_projects',
            'participant_scope' => 'all',
        ]);
        $user->assignRole($role);
    }
}
