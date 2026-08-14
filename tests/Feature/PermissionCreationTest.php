<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_store_permission_can_create_and_assign_a_permission(): void
    {
        $manager = User::factory()->create();
        $managerRole = Role::create(['name' => 'Berechtigungsmanager', 'guard_name' => 'web', 'color' => '#000000']);
        $selectedRole = Role::create(['name' => 'Einkauf', 'guard_name' => 'web', 'color' => '#000000']);
        $administrator = Role::create(['name' => 'Administrator', 'guard_name' => 'web', 'color' => '#000000']);
        $category = Berechtigungskategorie::create(['name' => 'Interne Kommunikation']);

        $manager->assignRole($managerRole);
        $managerRole->berechtigungskategories()->attach($category);
        $this->grantTestPermission($manager, 'berechtigung.store');

        $response = $this->actingAs($manager)->postJson(route('berechtigung.store'), [
            'name' => 'chat.moderate',
            'display_name' => 'Chat moderieren',
            'beschreibung' => 'Erlaubt das Moderieren des internen Chats.',
            'berechtigungskategorie_id' => $category->id,
            'assign_to_role' => true,
            'role_id' => $selectedRole->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('permission.name', 'chat.moderate')
            ->assertJsonPath('permission.display_name', 'Chat moderieren');

        $this->assertDatabaseHas('permissions', [
            'name' => 'chat.moderate',
            'display_name' => 'Chat moderieren',
            'guard_name' => 'web',
            'berechtigungskategorie_id' => $category->id,
        ]);

        $permission = Permission::findByName('chat.moderate', 'web');
        $this->assertTrue($selectedRole->fresh()->hasPermissionTo($permission));
        $this->assertTrue($administrator->fresh()->hasPermissionTo($permission));
        $this->assertDatabaseHas('role_berechtigungskategories', [
            'role_id' => $selectedRole->id,
            'berechtigungskategorie_id' => $category->id,
        ]);
    }

    public function test_permission_name_must_be_unique_and_must_not_contain_spaces(): void
    {
        [$manager, $category] = $this->managerForCategory();

        $this->actingAs($manager)->postJson(route('berechtigung.store'), [
            'name' => 'chat.use',
            'display_name' => 'Doppelter Chat',
            'berechtigungskategorie_id' => $category->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->actingAs($manager)->postJson(route('berechtigung.store'), [
            'name' => 'chat verwenden',
            'display_name' => 'Ungültiger Name',
            'berechtigungskategorie_id' => $category->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_permission_cannot_be_created_without_store_permission(): void
    {
        $user = User::factory()->create();
        $category = Berechtigungskategorie::create(['name' => 'Interne Kommunikation']);
        $this->grantTestPermission($user, 'berechtigung.update');

        $this->actingAs($user)->postJson(route('berechtigung.store'), [
            'name' => 'chat.moderate',
            'display_name' => 'Chat moderieren',
            'berechtigungskategorie_id' => $category->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('permissions', ['name' => 'chat.moderate']);
    }

    /** @return array{0: User, 1: Berechtigungskategorie} */
    private function managerForCategory(): array
    {
        $manager = User::factory()->create();
        $role = Role::create(['name' => 'Berechtigungsmanager', 'guard_name' => 'web', 'color' => '#000000']);
        $category = Berechtigungskategorie::query()->firstOrCreate(['name' => 'Interne Kommunikation']);
        $manager->assignRole($role);
        $role->berechtigungskategories()->attach($category);
        $this->grantTestPermission($manager, 'berechtigung.store');

        return [$manager, $category];
    }
}
