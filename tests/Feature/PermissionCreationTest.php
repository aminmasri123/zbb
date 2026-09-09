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

    public function test_edit_renames_permission_preserving_assignments_and_delete_removes_them(): void
    {
        [$manager, $category] = $this->managerForCategory();
        $this->grantTestPermission($manager, 'berechtigung.update');
        $this->grantTestPermission($manager, 'berechtigung.destroy');
        $permission = Permission::create(['name'=>'custom.test', 'guard_name'=>'web', 'berechtigungskategorie_id'=>$category->id]);
        $manager->givePermissionTo($permission);
        $role = $manager->roles->first();
        $role->givePermissionTo($permission);
        $this->actingAs($manager)->putJson(route('berechtigung.update', $permission->id), ['name'=>'changed.key','display_name'=>'Klarer Name','beschreibung'=>'Beschreibung'])->assertOk();
        $this->assertSame('changed.key', $permission->fresh()->name);
        $this->assertSame('Klarer Name', $permission->fresh()->display_name);
        $this->assertTrue($role->fresh()->hasPermissionTo($permission->fresh()));
        $this->assertTrue($manager->fresh()->hasPermissionTo('changed.key'));
        $this->putJson(route('berechtigung.update', $permission->id), ['name'=>'berechtigung.update','display_name'=>'Doppelt'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->deleteJson(route('berechtigung.destroy', $permission->id), ['confirm_name'=>'wrong'])->assertUnprocessable();
        $this->deleteJson(route('berechtigung.destroy', $permission->id), ['confirm_name'=>'changed.key'])->assertOk();
        $this->assertDatabaseMissing('permissions', ['id'=>$permission->id]);
        $this->assertDatabaseMissing('role_has_permissions', ['permission_id'=>$permission->id]);
        $this->assertDatabaseMissing('model_has_permissions', ['permission_id'=>$permission->id]);
    }

    public function test_permission_management_requires_right_and_category_access_and_protects_management(): void
    {
        [$manager, $category] = $this->managerForCategory();
        $permission = Permission::create(['name'=>'custom.test', 'guard_name'=>'web', 'berechtigungskategorie_id'=>$category->id]);
        $this->actingAs($manager)->putJson(route('berechtigung.update',$permission->id), ['display_name'=>'Test'])->assertForbidden();
        $this->deleteJson(route('berechtigung.destroy',$permission->id), ['confirm_name'=>'custom.test'])->assertForbidden();
        $this->grantTestPermission($manager,'berechtigung.update');
        $this->grantTestPermission($manager,'berechtigung.destroy');
        $other = Berechtigungskategorie::create(['name'=>'Andere Kategorie']);
        $permission->update(['berechtigungskategorie_id'=>$other->id]);
        $this->putJson(route('berechtigung.update',$permission->id), ['display_name'=>'Test'])->assertForbidden();
        $this->deleteJson(route('berechtigung.destroy',$permission->id), ['confirm_name'=>'custom.test'])->assertForbidden();
        $permission->update(['name'=>'berechtigung.protected','berechtigungskategorie_id'=>$category->id]);
        $this->deleteJson(route('berechtigung.destroy',$permission->id), ['confirm_name'=>'berechtigung.protected'])->assertUnprocessable();
        $this->assertDatabaseHas('permissions',['id'=>$permission->id]);
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
