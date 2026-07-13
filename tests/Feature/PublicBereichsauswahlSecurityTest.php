<?php

namespace Tests\Feature;

use App\Http\Controllers\BopLegacyFunctionController;
use App\Models\BereichsauswahlSetting;
use App\Models\Partner;
use App\Models\Projekt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicBereichsauswahlSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_access_code_verification_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson(route('bereichsauswahl.self.verify', 'unknown-token'), [
                'access_code' => 'UNKNOWN',
            ])->assertNotFound();
        }

        $this->postJson(route('bereichsauswahl.self.verify', 'unknown-token'), [
            'access_code' => 'UNKNOWN',
        ])->assertTooManyRequests();
    }

    public function test_public_selection_routes_have_explicit_rate_limits(): void
    {
        $expectedMiddleware = [
            'bereichsauswahl.self.show' => ['throttle:60,1'],
            'bereichsauswahl.self.thanks' => ['throttle:60,1'],
            'bereichsauswahl.self.verify' => ['throttle:10,1'],
            'bereichsauswahl.self.store' => ['throttle:10,1'],
        ];

        foreach ($expectedMiddleware as $routeName => $expected) {
            $middleware = Route::getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];

            foreach ($expected as $entry) {
                $this->assertContains($entry, $middleware, "Route {$routeName} muss {$entry} verwenden.");
            }
        }
    }

    public function test_thanks_page_is_unavailable_when_public_access_is_disabled(): void
    {
        $setting = BereichsauswahlSetting::query()->create([
            'projekt_id' => Projekt::factory()->create()->id,
            'partner_id' => Partner::query()->create(['name' => 'Testschule'])->id,
            'schuljahr' => '2026/2027',
            'teil' => '1',
            'auswahl_anzahl' => 4,
            'public_token' => 'disabled-public-token',
            'zugang_aktiv' => false,
        ]);

        $this->get(route('bereichsauswahl.self.thanks', $setting->public_token))
            ->assertNotFound();
    }

    public function test_all_legacy_bop_routes_remain_authenticated_and_permission_protected(): void
    {
        $legacyRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with(
                $route->getActionName(),
                BopLegacyFunctionController::class . '@'
            ));

        $this->assertNotEmpty($legacyRoutes);

        foreach ($legacyRoutes as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth', $middleware, "Route {$route->getName()} muss authentifiziert sein.");
            $this->assertContains('routePermission', $middleware, "Route {$route->getName()} muss eine Permission pruefen.");
        }
    }
}
