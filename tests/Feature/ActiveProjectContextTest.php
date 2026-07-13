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
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ActiveProjectContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_to_assigned_project(): void
    {
        $user = $this->userWithParticipantAccess();
        $project = $this->project('BvB Reha 2027');
        $this->assignUser($user, $project);

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('projekt.switch'), ['projekt_id' => $project->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertSame($project->id, $user->fresh()->current_team_id);

        $this->actingAs($user->fresh())
            ->get(route('teilnehmer.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentProjekt.id', $project->id)
                ->where('currentProjekt.name', 'BvB Reha 2027')
                ->has('auth.user.projekte', 1)
            );
    }

    public function test_switch_rejects_project_not_assigned_to_user(): void
    {
        $user = $this->userWithParticipantAccess();
        $current = $this->project('Eigenes Projekt');
        $foreign = $this->project('Fremdes Projekt');
        $this->assignUser($user, $current);
        $user->update(['current_team_id' => $current->id]);

        $this->actingAs($user)
            ->post(route('projekt.switch'), ['projekt_id' => $foreign->id])
            ->assertForbidden();

        $this->assertSame($current->id, $user->fresh()->current_team_id);
    }

    public function test_participant_overview_uses_only_active_header_project(): void
    {
        $user = $this->userWithParticipantAccess();
        $active = $this->project('BvB Reha aktiv');
        $other = $this->project('BOP anderes Projekt');
        $this->assignUser($user, $active);
        $this->assignUser($user, $other);
        $user->update(['current_team_id' => $active->id]);

        $visible = Personen::factory()->create([
            'typ' => 'teilnehmer',
            'vorname' => 'BvB',
            'nachname' => 'Sichtbar',
        ]);
        $hidden = Personen::factory()->create([
            'typ' => 'teilnehmer',
            'vorname' => 'BOP',
            'nachname' => 'Versteckt',
        ]);
        $this->participation($active, $visible);
        $this->participation($other, $hidden);

        $this->actingAs($user)
            ->get(route('teilnehmer.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('defaultProjekt', $active->id)
                ->has('teilnehmers.data', 1)
                ->where('teilnehmers.data.0.id', $visible->id)
            );

        $this->actingAs($user)
            ->get(route('teilnehmer.index', ['projekt_id' => $other->id]))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('teilnehmer.projekt.index', $other->id))
            ->assertForbidden();
    }

    public function test_participant_creation_cannot_override_active_header_project(): void
    {
        Notification::fake();
        $user = $this->userWithParticipantAccess();
        $this->givePermission($user, 'teilnehmer.store');
        $active = $this->project('Aktives Projekt');
        $other = $this->project('Anderes Projekt');
        $this->assignUser($user, $active);
        $this->assignUser($user, $other);
        $user->update(['current_team_id' => $active->id]);
        $location = Standort::factory()->create();

        $this->actingAs($user)->postJson(route('teilnehmer.store'), [
            'vorname' => 'Kontext',
            'nachname' => 'Gebunden',
            'geschlecht' => 'd',
            'projekt' => $other->id,
            'standort' => $location->id,
        ])->assertCreated();

        $participant = Personen::query()
            ->where('vorname', 'Kontext')
            ->where('nachname', 'Gebunden')
            ->firstOrFail();

        $this->assertDatabaseHas('projekt_has_personens', [
            'personen_id' => $participant->id,
            'projekt_id' => $active->id,
        ]);
        $this->assertDatabaseMissing('projekt_has_personens', [
            'personen_id' => $participant->id,
            'projekt_id' => $other->id,
        ]);
    }

    private function project(string $name): Projekt
    {
        return Projekt::factory()->create(['name' => $name]);
    }

    private function assignUser(User $user, Projekt $project): void
    {
        $this->participation($project, $user->person);
    }

    private function participation(Projekt $project, Personen $person): ProjektHasPersonen
    {
        return ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $person->id,
            'standort_id' => Standort::factory()->create()->id,
            'status' => 'aktiv',
        ]);
    }

    private function userWithParticipantAccess(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Projektkontext-' . uniqid(),
            'guard_name' => 'web',
            'color' => '#2563eb',
        ]);
        RoleDataAccessSetting::query()->create([
            'role_id' => $role->id,
            'team_scope' => 'own_projects',
            'participant_scope' => 'all',
        ]);
        $user->assignRole($role);
        $this->givePermission($user, 'teilnehmer.index');

        return $user;
    }

    private function givePermission(User $user, string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Projektkontext'],
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
