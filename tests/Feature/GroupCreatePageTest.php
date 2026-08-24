<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GroupCreatePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_create_route_renders_the_create_page(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create();
        $standort = Standort::factory()->create();

        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $user->person_id,
            'standort_id' => $standort->id,
            'status' => 'aktiv',
        ]);

        $user->update(['current_team_id' => $project->id]);
        $this->givePermission($user, 'gruppe.store');

        $this->actingAs($user)
            ->get(route('gruppe.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gruppe/Create')
                ->where('projekt.id', $project->id)
                ->has('betreuer', 1)
                ->where('betreuer.0.id', $user->person_id));
    }

    private function givePermission(User $user, string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Projektfunktionen'],
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
