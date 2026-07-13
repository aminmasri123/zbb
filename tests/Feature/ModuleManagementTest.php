<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Modules\ModuleStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ModuleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_modules_are_enabled_by_default(): void
    {
        $resolver = app(ModuleStateResolver::class);

        $this->assertTrue($resolver->enabled('room_management'));
        $this->assertTrue($resolver->enabled('it_management'));
        $this->assertTrue($resolver->enabled('warehouse_management'));
        $this->assertTrue($resolver->enabled('vehicle_management'));
        $this->assertTrue($resolver->enabled('participant_management'));
    }

    public function test_all_visible_modules_are_global_only(): void
    {
        $this->assertFalse(
            SystemModule::query()
                ->where('visible_in_settings', true)
                ->where('supports_location_scope', true)
                ->exists()
        );
    }

    public function test_disabled_room_module_blocks_backend_without_deleting_room_data(): void
    {
        $location = Standort::factory()->create();
        $room = Raeume::query()->create([
            'name' => 'Raum 101',
            'standort_id' => $location->id,
            'typ' => 'Seminarraum',
        ]);
        $user = User::factory()->create();
        $this->givePermission($user, 'raeumlichkeiten.index');
        app(ModuleStateResolver::class)->set($this->roomModule(), false, null, $user->id);

        $this->actingAs($user)
            ->get(route('raeumlichkeiten.index'))
            ->assertNotFound();

        $this->assertDatabaseHas('raeumes', ['id' => $room->id, 'name' => 'Raum 101']);
    }

    public function test_enabled_module_does_not_bypass_permission(): void
    {
        $this->ensurePermission('raeumlichkeiten.index');

        $this->actingAs(User::factory()->create())
            ->get(route('raeumlichkeiten.index'))
            ->assertForbidden();
    }

    public function test_module_admin_stores_only_global_assignment(): void
    {
        $user = User::factory()->create();
        $module = $this->roomModule();
        $this->givePermission($user, 'berechtigung.update');

        $this->actingAs($user)
            ->put(route('module-settings.update', $module), [
                'enabled' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('module_assignments', [
            'module_id' => $module->id,
            'scope_key' => 'global',
            'location_id' => null,
            'enabled' => false,
            'activated_by_user_id' => $user->id,
        ]);
    }

    public function test_module_admin_endpoint_requires_permission(): void
    {
        $this->ensurePermission('berechtigung.update');

        $this->actingAs(User::factory()->create())
            ->put(route('module-settings.update', $this->roomModule()), ['enabled' => false])
            ->assertForbidden();

        $this->assertDatabaseCount('module_assignments', 0);
    }

    public function test_unenforced_module_cannot_be_disabled_from_admin_endpoint(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'berechtigung.update');
        $module = SystemModule::query()->where('key', 'document_management')->firstOrFail();

        $this->actingAs($user)
            ->put(route('module-settings.update', $module), ['enabled' => false])
            ->assertUnprocessable();

        $this->assertTrue(app(ModuleStateResolver::class)->enabled($module->key));
        $this->assertDatabaseMissing('module_assignments', ['module_id' => $module->id]);
    }

    public function test_all_room_routes_use_module_middleware(): void
    {
        $roomRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'raeumlichkeiten.'));

        $this->assertCount(9, $roomRoutes);

        foreach ($roomRoutes as $route) {
            $this->assertContains(
                'module:room_management',
                $route->gatherMiddleware(),
                "Route {$route->getName()} muss den Modulstatus pruefen."
            );
        }
    }

    public function test_effective_module_state_is_shared_with_inertia(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'dashboard.index');
        app(ModuleStateResolver::class)->set($this->roomModule(), false, null, $user->id);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('enabledModules.room_management', false)
            );
    }

    public function test_settings_show_participant_module_but_not_project_labels(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'berechtigung.update');

        $this->actingAs($user)
            ->get(route('module-settings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('modules', fn ($modules) => collect($modules)->pluck('key')->contains('participant_management')
                    && !collect($modules)->pluck('key')->contains('bop')
                    && !collect($modules)->pluck('key')->contains('bvb_reha'))
            );
    }

    private function roomModule(): SystemModule
    {
        return SystemModule::query()->where('key', 'room_management')->firstOrFail();
    }

    private function givePermission(User $user, string $name): void
    {
        $this->ensurePermission($name);
        $user->givePermissionTo($name);
    }

    private function ensurePermission(string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Test'],
            ['beschreibung' => '']
        );

        Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
