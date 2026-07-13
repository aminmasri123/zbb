<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\ProjektHasPersonenMeta;
use App\Models\Role;
use App\Models\Standort;
use App\Models\User;
use App\Models\Zeitraum;
use App\Services\Projects\StaffProjectAssignmentSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffProjectAssignmentSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unchanged_assignment_keeps_identity_and_dependent_data(): void
    {
        $person = Personen::factory()->create(['typ' => 'mitarbeiter']);
        $project = Projekt::factory()->create();
        $location = Standort::factory()->create();
        $assignment = ProjektHasPersonen::query()->create([
            'personen_id' => $person->id,
            'projekt_id' => $project->id,
            'standort_id' => $location->id,
            'status' => 'pausiert',
        ]);
        $meta = ProjektHasPersonenMeta::query()->create([
            'projekt_person_id' => $assignment->id,
        ]);
        Zeitraum::query()->create([
            'model_type' => ProjektHasPersonen::class,
            'model_id' => $assignment->id,
            'starttermin' => '2026-08-01',
        ]);

        $this->synchronizer()->sync($person, [[
            'projekt_id' => $project->id,
            'standort_ids' => [$location->id],
        ]]);

        $this->assertDatabaseHas('projekt_has_personens', [
            'id' => $assignment->id,
            'status' => 'aktiv',
        ]);
        $this->assertDatabaseHas('projekt_has_personen_metas', [
            'id' => $meta->id,
            'projekt_person_id' => $assignment->id,
        ]);
        $this->assertDatabaseHas('zeitraums', [
            'model_type' => ProjektHasPersonen::class,
            'model_id' => $assignment->id,
            'starttermin' => '2026-08-01 00:00:00',
        ]);
    }

    public function test_sync_changes_only_actual_project_location_differences(): void
    {
        $person = Personen::factory()->create(['typ' => 'mitarbeiter']);
        $firstProject = Projekt::factory()->create();
        $secondProject = Projekt::factory()->create();
        $firstLocation = Standort::factory()->create();
        $secondLocation = Standort::factory()->create();
        $kept = ProjektHasPersonen::query()->create([
            'personen_id' => $person->id,
            'projekt_id' => $firstProject->id,
            'standort_id' => $firstLocation->id,
            'status' => 'aktiv',
        ]);
        $removed = ProjektHasPersonen::query()->create([
            'personen_id' => $person->id,
            'projekt_id' => $firstProject->id,
            'standort_id' => $secondLocation->id,
            'status' => 'aktiv',
        ]);

        $this->synchronizer()->sync($person, [
            ['projekt_id' => $firstProject->id, 'standort_ids' => [$firstLocation->id]],
            ['projekt_id' => $secondProject->id, 'standort_ids' => [$secondLocation->id]],
        ]);

        $this->assertDatabaseHas('projekt_has_personens', ['id' => $kept->id]);
        $this->assertDatabaseMissing('projekt_has_personens', ['id' => $removed->id]);
        $this->assertDatabaseHas('projekt_has_personens', [
            'personen_id' => $person->id,
            'projekt_id' => $secondProject->id,
            'standort_id' => $secondLocation->id,
        ]);
        $this->assertSame(2, ProjektHasPersonen::query()->where('personen_id', $person->id)->count());
    }

    public function test_duplicate_input_does_not_create_duplicate_assignment(): void
    {
        $person = Personen::factory()->create(['typ' => 'mitarbeiter']);
        $project = Projekt::factory()->create();
        $location = Standort::factory()->create();
        $payload = [[
            'projekt_id' => $project->id,
            'standort_ids' => [$location->id, $location->id],
        ]];

        $this->synchronizer()->sync($person, $payload);
        $firstId = ProjektHasPersonen::query()->where('personen_id', $person->id)->value('id');
        $this->synchronizer()->sync($person, $payload);

        $this->assertSame(1, ProjektHasPersonen::query()->where('personen_id', $person->id)->count());
        $this->assertSame($firstId, ProjektHasPersonen::query()->where('personen_id', $person->id)->value('id'));
    }

    public function test_staff_synchronizer_rejects_participant_records(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->synchronizer()->sync(
            Personen::factory()->create(['typ' => 'teilnehmer']),
            []
        );
    }

    public function test_user_update_preserves_unchanged_assignment_and_meta_record(): void
    {
        $actor = User::factory()->create();
        $this->givePermission($actor, 'benutzer.update');
        $target = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Kern-Testrolle',
            'guard_name' => 'web',
            'color' => '#334155',
        ]);
        $target->assignRole($role);
        $project = Projekt::factory()->create();
        $location = Standort::factory()->create();
        $assignment = ProjektHasPersonen::query()->create([
            'personen_id' => $target->person_id,
            'projekt_id' => $project->id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);
        $meta = ProjektHasPersonenMeta::query()->create([
            'projekt_person_id' => $assignment->id,
        ]);

        $this->actingAs($actor)->put(route('user.update', $target), [
            'first_name' => $target->person->vorname,
            'last_name' => $target->person->nachname,
            'username' => $target->username,
            'email' => $target->email,
            'rollen' => [$role->id],
            'projekt_zuweisungen' => [[
                'projekt_id' => $project->id,
                'standort_ids' => [$location->id],
            ]],
        ])->assertRedirect(route('user.edit', $target->id));

        $this->assertDatabaseHas('projekt_has_personens', ['id' => $assignment->id]);
        $this->assertDatabaseHas('projekt_has_personen_metas', [
            'id' => $meta->id,
            'projekt_person_id' => $assignment->id,
        ]);
    }

    public function test_personal_update_preserves_unchanged_assignment_identity(): void
    {
        $actor = User::factory()->create();
        $this->givePermission($actor, 'personal.update');
        $target = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Personal-Kern-Testrolle',
            'guard_name' => 'web',
            'color' => '#475569',
        ]);
        $target->assignRole($role);
        $project = Projekt::factory()->create();
        $location = Standort::factory()->create();
        $assignment = ProjektHasPersonen::query()->create([
            'personen_id' => $target->person_id,
            'projekt_id' => $project->id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);

        $this->actingAs($actor)->put(route('personal.update', $target->person_id), [
            'first_name' => $target->person->vorname,
            'last_name' => $target->person->nachname,
            'username' => $target->username,
            'email' => $target->email,
            'rollen' => [$role->id],
            'projekt_zuweisungen' => [[
                'projekt_id' => $project->id,
                'standort_ids' => [$location->id],
            ]],
        ])->assertRedirect(route('personal.index'));

        $this->assertDatabaseHas('projekt_has_personens', ['id' => $assignment->id]);
    }

    public function test_one_person_can_have_separate_participations_in_different_projects(): void
    {
        $person = Personen::factory()->create(['typ' => 'teilnehmer']);
        $location = Standort::factory()->create();
        $bop = Projekt::factory()->create(['name' => 'BOP 2026']);
        $bvbReha = Projekt::factory()->create(['name' => 'BvB Reha 2027']);

        foreach ([$bop, $bvbReha] as $project) {
            ProjektHasPersonen::query()->create([
                'personen_id' => $person->id,
                'projekt_id' => $project->id,
                'standort_id' => $location->id,
                'status' => 'aktiv',
            ]);
        }

        $this->assertDatabaseCount('personens', 1);
        $this->assertSame(2, ProjektHasPersonen::query()->where('personen_id', $person->id)->count());
        $this->assertEqualsCanonicalizing(
            [$bop->id, $bvbReha->id],
            $person->projekte()->pluck('projekts.id')->all()
        );
    }

    private function synchronizer(): StaffProjectAssignmentSynchronizer
    {
        return app(StaffProjectAssignmentSynchronizer::class);
    }

    private function givePermission(User $user, string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Kern-Test'],
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
