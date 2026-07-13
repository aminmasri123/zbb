<?php

namespace Tests\Feature;

use App\Http\Controllers\BopGruppeExportController;
use App\Http\Controllers\BopLegacyFunctionController;
use App\Http\Controllers\EinteilungParameterController;
use App\Http\Controllers\PotenzialanalyseController;
use App\Http\Controllers\ProjektBopController;
use App\Models\SystemModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BopModuleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_bop_functions_are_project_configuration_not_module_gated(): void
    {
        $controllers = [
            ProjektBopController::class,
            BopLegacyFunctionController::class,
            BopGruppeExportController::class,
            EinteilungParameterController::class,
            PotenzialanalyseController::class,
        ];

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => collect($controllers)->contains(
                fn (string $controller) => str_starts_with($route->getActionName(), $controller . '@')
            ));

        $this->assertGreaterThanOrEqual(60, $routes->count());

        foreach ($routes as $route) {
            $this->assertNotContains('module:bop', $route->gatherMiddleware());
        }
    }

    public function test_legacy_education_module_entries_are_hidden_and_unenforced(): void
    {
        foreach (['bop', 'bvb_reha'] as $key) {
            $module = SystemModule::query()->where('key', $key)->firstOrFail();

            $this->assertFalse($module->visible_in_settings);
            $this->assertFalse($module->is_enforced);
        }
    }
}
