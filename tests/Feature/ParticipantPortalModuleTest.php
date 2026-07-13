<?php

namespace Tests\Feature;

use App\Models\SystemModule;
use App\Models\User;
use App\Services\Modules\ModuleStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ParticipantPortalModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_is_a_visible_enforced_global_module_disabled_by_default(): void
    {
        $module = $this->portalModule();

        $this->assertTrue($module->visible_in_settings);
        $this->assertTrue($module->is_enforced);
        $this->assertFalse($module->supports_location_scope);
        $this->assertFalse($module->default_enabled);
        $this->assertFalse(app(ModuleStateResolver::class)->enabled('participant_portal'));
    }

    public function test_every_portal_route_is_protected_and_activation_controls_backend_access(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'participant-portal.'));

        $this->assertNotEmpty($routes);
        foreach ($routes as $route) {
            $this->assertContains('module:participant_portal', $route->gatherMiddleware());
        }

        $this->get(route('participant-portal.welcome'))->assertNotFound();

        $user = User::factory()->create();
        app(ModuleStateResolver::class)->set($this->portalModule(), true, null, $user->id);

        $this->get(route('participant-portal.welcome'))->assertOk();
    }

    public function test_portal_cannot_run_when_participant_module_is_disabled(): void
    {
        $user = User::factory()->create();
        $modules = app(ModuleStateResolver::class);

        $modules->set($this->portalModule(), true, null, $user->id);
        $modules->set(
            SystemModule::query()->where('key', 'participant_management')->firstOrFail(),
            false,
            null,
            $user->id
        );

        $this->assertFalse($modules->enabled('participant_portal'));
        $this->assertFalse($modules->available('participant_portal'));
        $this->get(route('participant-portal.welcome'))->assertNotFound();
    }

    private function portalModule(): SystemModule
    {
        return SystemModule::query()->where('key', 'participant_portal')->firstOrFail();
    }
}
