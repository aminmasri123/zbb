<?php

namespace Tests\Feature;

use App\Models\Bereich;
use App\Models\Berechtigungskategorie;
use App\Models\Gruppe;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GroupDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_group_deletion_stays_successful_after_the_group_is_gone(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create();
        $location = Standort::factory()->create();
        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $user->person_id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);
        $user->update(['current_team_id' => $project->id]);
        $this->givePermission($user, 'gruppe.destroy');

        $area = Bereich::query()->create(['name' => 'Löschtest']);
        $room = Raeume::query()->create([
            'name' => 'Testraum',
            'standort_id' => $location->id,
            'typ' => 'Seminarraum',
            'aktiv' => true,
        ]);
        $group = Gruppe::query()->create([
            'personen_id' => $user->person_id,
            'bereich_id' => $area->id,
            'projekt_id' => $project->id,
            'standort_id' => $location->id,
            'raum_id' => $room->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('gruppe.destroy', $group->id))
            ->assertOk();

        $this->assertDatabaseMissing('gruppes', ['id' => $group->id]);

        $this->actingAs($user)
            ->deleteJson(route('gruppe.destroy', $group->id))
            ->assertOk()
            ->assertJsonPath('already_deleted', true);
    }

    private function givePermission(User $user, string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Gruppen'],
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
