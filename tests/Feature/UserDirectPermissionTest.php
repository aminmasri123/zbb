<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserDirectPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_manager_can_assign_and_remove_direct_user_permissions(): void
    {
        $manager = User::factory()->create();
        $employee = User::factory()->create();
        $this->grantTestPermission($manager, 'berechtigung.update');

        $category = Berechtigungskategorie::firstOrCreate(
            ['name' => 'Zusatzberechtigungen'],
            ['beschreibung' => 'Test']
        );
        $permission = Permission::create([
            'name' => 'materialanforderung.sachlische_freigabe.update',
            'guard_name' => 'web',
            'berechtigungskategorie_id' => $category->id,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($manager)
            ->put(route('personal.permissions.update', $employee), [
                'permission_ids' => [$permission->id],
            ])
            ->assertRedirect();

        $this->assertTrue($employee->fresh()->hasDirectPermission($permission));

        $this->actingAs($manager)
            ->put(route('personal.permissions.update', $employee), [
                'permission_ids' => [],
            ])
            ->assertRedirect();

        $this->assertFalse($employee->fresh()->hasDirectPermission($permission));
    }

    public function test_user_without_permission_management_cannot_change_direct_permissions(): void
    {
        $actor = User::factory()->create();
        $employee = User::factory()->create();

        $this->actingAs($actor)
            ->put(route('personal.permissions.update', $employee), ['permission_ids' => []])
            ->assertForbidden();
    }
}
