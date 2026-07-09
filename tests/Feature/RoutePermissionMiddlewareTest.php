<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoutePermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_route_with_existing_permission_is_forbidden_without_permission(): void
    {
        $this->ensurePermission('organisation.index');

        $response = $this->actingAs(User::factory()->create())
            ->get(route('organisation.index'));

        $response->assertForbidden();
    }

    public function test_named_route_with_existing_permission_is_allowed_with_permission(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'organisation.index');

        $response = $this->actingAs($user)->get(route('organisation.index'));

        $response->assertOk();
    }

    public function test_route_permission_override_protects_legacy_route_name(): void
    {
        $this->ensurePermission('kooperationspartner.index');

        $response = $this->actingAs(User::factory()->create())
            ->get(route('partner.index'));

        $response->assertForbidden();
    }

    public function test_existing_explicit_route_authorization_is_not_replaced_by_route_name_permission(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'teilnehmer.update');
        $this->ensurePermission('abschluss.store');

        $response = $this->actingAs($user)->post(route('abschluss.store'), []);

        $this->assertNotSame(
            403,
            $response->getStatusCode(),
            'Explizite can()-Middleware darf nicht durch die automatische Routennamen-Permission ersetzt werden.'
        );
    }

    private function givePermission(User $user, string $permissionName): void
    {
        $this->ensurePermission($permissionName);
        $user->givePermissionTo($permissionName);
    }

    private function ensurePermission(string $permissionName): void
    {
        $categoryId = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Test'],
            ['beschreibung' => '']
        )->id;

        Permission::query()->updateOrCreate(
            [
                'name' => $permissionName,
                'guard_name' => 'web',
            ],
            [
                'berechtigungskategorie_id' => $categoryId,
                'beschreibung' => null,
            ]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
