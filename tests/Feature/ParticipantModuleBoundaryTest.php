<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Personen;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Modules\ModuleStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ParticipantModuleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_management_is_a_visible_global_module(): void
    {
        $module = $this->participantModule();

        $this->assertTrue($module->visible_in_settings);
        $this->assertTrue($module->is_enforced);
        $this->assertTrue($module->default_enabled);
        $this->assertFalse($module->supports_location_scope);
    }

    public function test_all_participant_routes_are_protected_by_the_module(): void
    {
        $participantRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route): bool {
                $name = (string) $route->getName();
                $uri = $route->uri();

                return str_starts_with($uri, 'teilnehmer')
                    || str_starts_with($uri, 'gruppehasteilnehmer')
                    || str_contains($uri, 'potenzialanalyse/teilnehmer')
                    || in_array($name, ['gruppeHasPersonen.destroy', 'export.excel.esfStammblatt'], true);
            });

        $this->assertGreaterThanOrEqual(25, $participantRoutes->count());

        foreach ($participantRoutes as $route) {
            $this->assertContains(
                'module:participant_management',
                $route->gatherMiddleware(),
                "Route {$route->getName()} muss das Teilnehmer-Modul pruefen."
            );
            $this->assertContains(
                'projectFeature:participant_management',
                $route->gatherMiddleware(),
                "Route {$route->getName()} muss die aktive Projektfunktion pruefen."
            );
        }
    }

    public function test_disabled_module_blocks_backend_without_deleting_participants(): void
    {
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $user = User::factory()->create();
        $this->givePermission($user, 'teilnehmer.index');
        app(ModuleStateResolver::class)->set($this->participantModule(), false, null, $user->id);

        $this->actingAs($user)->get(route('teilnehmer.index'))->assertNotFound();

        $this->assertDatabaseHas('personens', ['id' => $participant->id]);
    }

    public function test_enabled_module_does_not_bypass_permissions(): void
    {
        $this->givePermission(User::factory()->create(), 'dashboard.index');

        $this->actingAs(User::factory()->create())
            ->get(route('teilnehmer.index'))
            ->assertForbidden();
    }

    private function participantModule(): SystemModule
    {
        return SystemModule::query()->where('key', 'participant_management')->firstOrFail();
    }

    private function givePermission(User $user, string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Teilnehmer-Modultest'],
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
