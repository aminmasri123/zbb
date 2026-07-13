<?php

namespace Tests\Feature;

use App\Http\Controllers\DienstwagenBuchungController;
use App\Http\Controllers\DienstwagenController;
use App\Http\Controllers\DienstwagenfahrtenbuchController;
use App\Http\Controllers\DienstwagenkostenController;
use App\Http\Controllers\DienstwagenMeldungController;
use App\Http\Controllers\DienstwagenreportsController;
use App\Http\Controllers\DienstwagenwartungController;
use App\Http\Controllers\GeraetausgabeController;
use App\Http\Controllers\GeraetController;
use App\Http\Controllers\GeraetrueckgabeController;
use App\Http\Controllers\ItServiceController;
use App\Http\Controllers\LagerController;
use App\Models\Berechtigungskategorie;
use App\Models\Dienstwagen;
use App\Models\Geraet;
use App\Models\LagerArtikel;
use App\Models\Standort;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Modules\ModuleStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdministrativeModuleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_administrative_module_routes_use_their_module_middleware(): void
    {
        $boundaries = [
            'it_management' => [
                'count' => 28,
                'controllers' => [
                    ItServiceController::class,
                    GeraetController::class,
                    GeraetausgabeController::class,
                    GeraetrueckgabeController::class,
                ],
            ],
            'warehouse_management' => [
                'count' => 7,
                'controllers' => [LagerController::class],
            ],
            'vehicle_management' => [
                'count' => 34,
                'controllers' => [
                    DienstwagenController::class,
                    DienstwagenBuchungController::class,
                    DienstwagenfahrtenbuchController::class,
                    DienstwagenkostenController::class,
                    DienstwagenMeldungController::class,
                    DienstwagenreportsController::class,
                    DienstwagenwartungController::class,
                ],
            ],
        ];

        foreach ($boundaries as $moduleKey => $boundary) {
            $routes = collect(Route::getRoutes()->getRoutes())
                ->filter(fn ($route) => collect($boundary['controllers'])->contains(
                    fn (string $controller) => str_starts_with($route->getActionName(), $controller . '@')
                ));

            $this->assertCount($boundary['count'], $routes, "Unerwartete Routenzahl fuer {$moduleKey}.");

            foreach ($routes as $route) {
                $this->assertContains(
                    "module:{$moduleKey}",
                    $route->gatherMiddleware(),
                    "Route {$route->getName()} muss den Status von {$moduleKey} pruefen."
                );
            }
        }
    }

    public function test_administrative_modules_are_enforced_and_global_only(): void
    {
        $modules = SystemModule::query()
            ->whereIn('key', ['it_management', 'warehouse_management', 'vehicle_management'])
            ->get()
            ->keyBy('key');

        $this->assertCount(3, $modules);

        foreach ($modules as $module) {
            $this->assertTrue($module->is_enforced, "{$module->key} muss vollstaendig durchgesetzt sein.");
            $this->assertFalse($module->supports_location_scope, "{$module->key} darf noch keine Standortisolation vortaeuschen.");
            $this->assertTrue($module->default_enabled);
        }
    }

    public function test_disabled_it_module_blocks_mutation_without_deleting_device(): void
    {
        $device = Geraet::factory()->create();
        $user = User::factory()->create();
        $this->givePermission($user, 'it.geraet.destroy');
        $this->disableModule('it_management', $user);

        $this->actingAs($user)
            ->deleteJson(route('it-service.geraete.destroy', $device))
            ->assertNotFound();

        $this->assertDatabaseHas('geraets', ['id' => $device->id, 'sn' => $device->sn]);
    }

    public function test_disabled_warehouse_module_blocks_mutation_without_deleting_stock(): void
    {
        $article = LagerArtikel::query()->create([
            'name' => 'Bestandsschutz',
            'artikelnummer' => 'SAFE-001',
            'einheit' => 'Stueck',
            'bestand' => 12,
            'mindestbestand' => 2,
            'aktiv' => true,
        ]);
        $user = User::factory()->create();
        $this->givePermission($user, 'lager.artikel.destroy');
        $this->disableModule('warehouse_management', $user);

        $this->actingAs($user)
            ->delete(route('lager.artikel.destroy', $article))
            ->assertNotFound();

        $this->assertDatabaseHas('lager_artikel', [
            'id' => $article->id,
            'bestand' => 12,
        ]);
    }

    public function test_disabled_vehicle_module_blocks_mutation_without_deleting_vehicle(): void
    {
        $vehicle = Dienstwagen::query()->create([
            'typ' => 'PKW',
            'kennzeichen' => 'ZBB-SAFE-1',
            'marke' => 'Test',
            'modell' => 'Bestandsschutz',
            'baujahr' => 2024,
            'kraftstoffart' => 'Elektro',
            'kilometerstand' => 100,
            'standort_id' => Standort::factory()->create()->id,
            'status' => 'passiv',
        ]);
        $user = User::factory()->create();
        $this->givePermission($user, 'dienstwagen.destroy');
        $this->disableModule('vehicle_management', $user);

        $this->actingAs($user)
            ->delete(route('dienstwagen.destroy', $vehicle))
            ->assertNotFound();

        $this->assertDatabaseHas('dienstwagens', [
            'id' => $vehicle->id,
            'kennzeichen' => 'ZBB-SAFE-1',
        ]);
    }

    public function test_reactivated_module_restores_backend_access(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'lager.index');
        $module = $this->disableModule('warehouse_management', $user);

        $this->actingAs($user)
            ->get(route('lager.index'))
            ->assertNotFound();

        app(ModuleStateResolver::class)->set($module, true, null, $user->id);

        $this->actingAs($user)
            ->get(route('lager.index'))
            ->assertOk();
    }

    public function test_enabled_vehicle_module_does_not_bypass_permission(): void
    {
        $this->ensurePermission('dienstwagen.index');

        $this->actingAs(User::factory()->create())
            ->get(route('dienstwagen.index'))
            ->assertForbidden();
    }

    public function test_authorized_vehicle_access_works_while_module_is_enabled(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'dienstwagen.index');

        $this->actingAs($user)
            ->get(route('dienstwagen.index'))
            ->assertOk();
    }

    private function disableModule(string $key, User $user): SystemModule
    {
        $module = SystemModule::query()->where('key', $key)->firstOrFail();
        app(ModuleStateResolver::class)->set($module, false, null, $user->id);

        return $module;
    }

    private function givePermission(User $user, string $name): void
    {
        $permission = $this->ensurePermission($name);
        $user->givePermissionTo($permission);
    }

    private function ensurePermission(string $name): Permission
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Modulgrenzen'],
            ['beschreibung' => '']
        );

        $permission = Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permission;
    }
}
