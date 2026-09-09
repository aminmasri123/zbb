<?php

namespace Tests\Feature;

use App\Models\{Personen, Projekt, ProjektHasPersonen, Role, RoleDataAccessSetting, Standort, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantProjectLocationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_counsellor_sees_only_active_project_assignment_locations(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Berufsbegleiter', 'guard_name' => 'web', 'color' => '#123456']);
        $user->assignRole($role);
        $project = Projekt::factory()->create();
        $otherProject = Projekt::factory()->create();
        $location = Standort::factory()->create();
        $otherLocation = Standort::factory()->create();
        $user->update(['current_team_id' => $project->id]);
        $user->projekte()->attach($project, ['standort_id' => $location->id, 'status' => 'aktiv']);
        $user->projekte()->attach($otherProject, ['standort_id' => $otherLocation->id, 'status' => 'aktiv']);
        // A stale general location must not grant access in the active project.
        $user->standorte()->attach($otherLocation);
        $expected = null;
        foreach ([[$project, $location], [$project, $otherLocation], [$otherProject, $location], [$otherProject, $otherLocation]] as $index => [$p, $l]) {
            $person = Personen::factory()->create(['typ' => 'teilnehmer']);
            $person->projekte()->attach($p, ['standort_id' => $l->id, 'status' => 'aktiv']);
            if ($index === 0) {
                $expected = $person->id;
            }
        }
        $visible = fn () => Personen::teilnehmer()->visibleForUser($user)->pluck('personens.id')->all();
        $this->assertSame([$expected], $visible());
        $this->assertSame('none', RoleDataAccessSetting::scopeForUser($user, 'team'));

        ProjektHasPersonen::where('personen_id', $user->person_id)->where('projekt_id', $project->id)->update(['status' => 'abgeschlossen']);
        $this->assertSame([], $visible());
        ProjektHasPersonen::where('personen_id', $user->person_id)->where('projekt_id', $project->id)->update(['status' => 'aktiv']);
        RoleDataAccessSetting::create(['role_id' => $role->id, 'team_scope' => 'none', 'participant_scope' => 'none']);
        $this->assertSame([], $visible(), 'An explicit administrator denial must override the role default.');
    }
}
