<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\PersonenHasBildungsmassnahmen;
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

class ProjectParticipationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_participation_creation_update_and_detail_are_bound_to_active_project(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->grantAllParticipantAccess($user);
        foreach (['teilnehmer.store', 'projekthasteilnehmer.update', 'teilnehmer.update'] as $permission) {
            $this->givePermission($user, $permission);
        }

        $location = Standort::factory()->create();
        $active = Projekt::factory()->create([
            'name' => 'BvB Reha 2027',
            'rule_settings' => ['participation_initial_status' => 'angefragt'],
        ]);
        $foreign = Projekt::factory()->create(['name' => 'BOP 2027']);
        $this->assign($active, $user->person, $location);
        $this->assign($foreign, $user->person, $location);
        $user->update(['current_team_id' => $active->id]);

        $this->actingAs($user)->postJson(route('teilnehmer.store'), [
            'vorname' => 'Lifecycle',
            'nachname' => 'Teilnehmer',
            'geschlecht' => 'd',
            'projekt' => $foreign->id,
            'standort' => $location->id,
        ])->assertCreated();
        $participant = Personen::query()
            ->where('vorname', 'Lifecycle')
            ->where('nachname', 'Teilnehmer')
            ->firstOrFail();
        $foreignParticipation = $this->assign($foreign, $participant, $location);

        $activeParticipation = ProjektHasPersonen::query()
            ->where('projekt_id', $active->id)
            ->where('personen_id', $participant->id)
            ->firstOrFail();
        $this->assertSame('angefragt', $activeParticipation->status);

        $this->actingAs($user)->putJson(route('projekthasteilnehmer.update'), [
            'id' => $activeParticipation->id,
            'status' => 'aufgenommen',
        ])->assertOk()->assertJsonPath('status', 'aufgenommen');

        $this->actingAs($user)->putJson(route('projekthasteilnehmer.update'), [
            'id' => $foreignParticipation->id,
            'status' => 'aktiv',
        ])->assertNotFound();

        $this->actingAs($user)->postJson(route('teilnehmer.praktikum.store'), [
            'teilnehmer_id' => $participant->id,
            'typ' => 'Praktikum',
            'traeger' => 'Aktiver Betrieb',
            'start' => '2027-03-01',
            'end' => '2027-03-31',
            'status' => 'geplant',
        ])->assertCreated();
        PersonenHasBildungsmassnahmen::query()->create([
            'person_id' => $participant->id,
            'projekt_person_id' => $foreignParticipation->id,
            'typ' => 'Praktikum',
            'traeger' => 'Fremder Betrieb',
            'start' => '2027-04-01',
            'end' => '2027-04-30',
            'status' => 'geplant',
        ]);

        $this->actingAs($user)->get(route('teilnehmer.edit', $participant->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('teilnehmer.projekte', 1)
                ->where('teilnehmer.projekte.0.id', $active->id)
                ->where('teilnehmer.projekte.0.pivot_model.status', 'aufgenommen')
                ->has('teilnehmer.praktika', 1)
                ->where('teilnehmer.praktika.0.traeger', 'Aktiver Betrieb'));
    }

    public function test_participation_cannot_be_written_to_non_active_project(): void
    {
        $user = User::factory()->create();
        $this->grantAllParticipantAccess($user);
        $this->givePermission($user, 'projekthasteilnehmer.store');
        $location = Standort::factory()->create();
        $active = Projekt::factory()->create();
        $foreign = Projekt::factory()->create();
        $this->assign($active, $user->person, $location);
        $this->assign($foreign, $user->person, $location);
        $user->update(['current_team_id' => $active->id]);
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $this->assign($foreign, $participant, $location);

        $this->actingAs($user)->post(route('projekthasteilnehmer.store'), [
            'teilnehmer_id' => $participant->id,
            'projekt_id' => $foreign->id,
            'model_type' => ProjektHasPersonen::class,
        ])->assertForbidden();

        $this->assertDatabaseMissing('projekt_has_personens', [
            'projekt_id' => $active->id,
            'personen_id' => $participant->id,
        ]);
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
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Teilnahme-Lebenszyklus'],
            ['beschreibung' => '']
        );
        $permission = Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo($permission);
    }

    private function grantAllParticipantAccess(User $user): void
    {
        $role = Role::query()->create([
            'name' => 'Teilnahme-Test-' . uniqid(),
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
