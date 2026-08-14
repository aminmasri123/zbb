<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Materialanforderung;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MaterialanforderungProjectlessAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administration_user_without_project_can_open_overview_and_visible_request(): void
    {
        $administration = User::factory()->create([
            'current_team_id' => null,
            'default_projekt_id' => null,
        ]);
        $this->givePermission($administration, 'materialanforderung.kaufmännische_freigabe.index');

        $requester = User::factory()->create();
        $project = Projekt::factory()->create();
        $materialRequest = Materialanforderung::query()->create([
            'projekt_id' => $project->id,
            'kostenstelle' => '14473',
            'status' => 'sachlich_genehmigt',
            'gesamtpreis' => 100,
            'endsumme' => 119,
            'ersteller_id' => $requester->id,
        ]);

        $this->actingAs($administration)
            ->get(route('materialanforderung.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasActiveProject', false)
                ->where('canCreateRequest', false)
                ->where('canOpenRequest', true)
                ->has('anforderungen', 1)
                ->where('anforderungen.0.id', $materialRequest->id)
            );

        $this->actingAs($administration)
            ->get(route('materialanforderung.show', $materialRequest->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('anforderung.id', $materialRequest->id)
            );
    }

    public function test_projectless_user_with_index_permission_can_open_empty_overview(): void
    {
        $user = User::factory()->create([
            'current_team_id' => null,
            'default_projekt_id' => null,
        ]);
        $this->givePermission($user, 'materialanforderung.index');

        $this->actingAs($user)
            ->get(route('materialanforderung.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('hasActiveProject', false)
                ->has('anforderungen', 0)
            );
    }

    public function test_material_requests_can_be_searched_by_project_name(): void
    {
        $administration = User::factory()->create([
            'current_team_id' => null,
            'default_projekt_id' => null,
        ]);
        $this->givePermission($administration, 'materialanforderung.kaufmännische_freigabe.index');

        $requester = User::factory()->create();
        $selectedProject = Projekt::factory()->create(['name' => 'Projekt Aurora']);
        $otherProject = Projekt::factory()->create(['name' => 'Projekt Borealis']);

        $selectedRequest = Materialanforderung::query()->create([
            'projekt_id' => $selectedProject->id,
            'kostenstelle' => '14473',
            'status' => 'sachlich_genehmigt',
            'gesamtpreis' => 100,
            'endsumme' => 119,
            'ersteller_id' => $requester->id,
        ]);
        Materialanforderung::query()->create([
            'projekt_id' => $otherProject->id,
            'kostenstelle' => '14474',
            'status' => 'sachlich_genehmigt',
            'gesamtpreis' => 200,
            'endsumme' => 238,
            'ersteller_id' => $requester->id,
        ]);

        $this->actingAs($administration)
            ->get(route('materialanforderung.index', ['search' => 'Aurora']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('anforderungen', 1)
                ->where('anforderungen.0.id', $selectedRequest->id)
                ->where('anforderungen.0.projekt.name', 'Projekt Aurora')
                ->where('filters.search', 'Aurora')
            );
    }

    public function test_creating_request_without_project_returns_clear_conflict(): void
    {
        $user = User::factory()->create([
            'current_team_id' => null,
            'default_projekt_id' => null,
        ]);
        $this->givePermission($user, 'materialanforderung.create');

        $this->actingAs($user)
            ->get(route('materialanforderung.create'))
            ->assertStatus(409)
            ->assertSee('Projekt zugewiesen und ausgewählt');
    }

    private function givePermission(User $user, string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Bestellungen'],
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
