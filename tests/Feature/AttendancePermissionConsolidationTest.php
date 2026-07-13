<?php

namespace Tests\Feature;

use App\Models\Anwesenheitsstatuten;
use App\Models\Bereich;
use App\Models\Berechtigungskategorie;
use App\Models\Gruppe;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Raeume;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Standort;
use App\Models\Tage;
use App\Models\User;
use App\Support\RoutePermissionMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendancePermissionConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_technical_attendance_routes_map_to_six_business_permissions(): void
    {
        $this->assertSame(['anwesenheit.manage'], RoutePermissionMap::permissionsFor('anwesenheit.store'));
        $this->assertSame(['anwesenheit.manage'], RoutePermissionMap::permissionsFor('anwesenheit.update'));
        $this->assertSame(['anwesenheit.export'], RoutePermissionMap::permissionsFor('export.anwesenheitslite_V1'));
        $this->assertSame(['anwesenheit.abrechnung'], RoutePermissionMap::permissionsFor('anwesenheitsliste.PA.digital.preview'));
        $this->assertSame(['anwesenheit.abrechnung'], RoutePermissionMap::permissionsFor('anwesenheitsliste.BoTag1.export'));
        $this->assertSame(['anwesenheit.archiv'], RoutePermissionMap::permissionsFor('anwesenheitsliste.POBO.bibb.pdf.store'));
    }

    public function test_attendance_management_is_limited_to_active_project_participants(): void
    {
        $user = $this->staffUser();
        $activeProject = Projekt::factory()->create();
        $foreignProject = Projekt::factory()->create();
        $location = Standort::factory()->create();
        $this->assign($activeProject, $user->person, $location);
        $user->update(['current_team_id' => $activeProject->id]);

        $activeParticipant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $foreignParticipant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $this->assign($activeProject, $activeParticipant, $location);
        $this->assign($foreignProject, $foreignParticipant, $location);

        $group = $this->group($activeProject, $user, $location);
        $day = Tage::query()->create(['datum' => '2026-07-12', 'wochentag' => 'Sonntag']);
        $status = Anwesenheitsstatuten::query()->create([
            'status' => 'anwesend',
            'abkuerzung' => 'A',
            'farben' => '#16a34a',
        ]);

        $payload = [
            'gruppe_id' => $group->id,
            'tag' => $day->datum,
            'startzeit' => '08:00',
            'endzeit' => '16:00',
            'anwesenheitsstatuten_id' => $status->id,
        ];

        $this->actingAs($user)
            ->post(route('anwesenheit.store'), $payload + ['personen_id' => $foreignParticipant->id])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('anwesenheit.store'), $payload + ['personen_id' => $activeParticipant->id])
            ->assertRedirect();

        $this->assertDatabaseHas('gruppe_has_personens', [
            'gruppe_id' => $group->id,
            'personen_id' => $activeParticipant->id,
            'tage_id' => $day->id,
            'anwesenheitsstatuten_id' => $status->id,
        ]);
    }

    private function staffUser(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Attendance test ' . uniqid(),
            'guard_name' => 'web',
            'color' => '#2563eb',
        ]);
        RoleDataAccessSetting::query()->create([
            'role_id' => $role->id,
            'team_scope' => 'own_projects',
            'participant_scope' => 'all',
        ]);
        $user->assignRole($role);
        $this->givePermission($user, 'anwesenheit.manage');

        return $user;
    }

    private function assign(Projekt $project, Personen $person, Standort $location): void
    {
        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $person->id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);
    }

    private function group(Projekt $project, User $user, Standort $location): Gruppe
    {
        $area = Bereich::query()->create(['name' => 'Attendance test']);
        $room = Raeume::query()->create([
            'name' => 'Attendance room',
            'standort_id' => $location->id,
            'typ' => 'Seminarraum',
            'aktiv' => true,
        ]);

        return Gruppe::query()->create([
            'personen_id' => $user->person_id,
            'bereich_id' => $area->id,
            'projekt_id' => $project->id,
            'standort_id' => $location->id,
            'raum_id' => $room->id,
        ]);
    }

    private function givePermission(User $user, string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Anwesenheitsliste'],
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
