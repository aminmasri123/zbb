<?php

namespace Tests\Feature;

use App\Models\{Role, RoleDataAccessSetting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name): Role
    {
        return Role::create(['name' => $name, 'guard_name' => 'web', 'color' => '#123456']);
    }

    public function test_label_changes_preserve_canonical_role_membership_permissions_and_scope(): void
    {
        $editor = User::factory()->create();
        $this->grantTestPermission($editor, 'berechtigung.update');
        $member = User::factory()->create();
        $role = $this->role('Berufsbegleiter');
        $member->assignRole($role);
        $this->grantTestPermission($member, 'teilnehmer.index');
        $role->givePermissionTo('teilnehmer.index');
        $before = RoleDataAccessSetting::scopeForUser($member, 'participant');
        $permissionIds = $role->permissions()->pluck('permissions.id')->sort()->values()->all();
        $this->actingAs($editor)->putJson(route('rolle.update', $role->id), [
            'display_name' => '  Bildungsbegleitung  ', 'name' => 'Administrator', 'color' => '#ffffff',
        ])->assertOk()->assertJsonPath('role.display_name', 'Bildungsbegleitung')->assertJsonPath('role.name', 'Berufsbegleiter');
        $this->assertTrue($member->fresh()->hasRole('Berufsbegleiter'));
        $this->assertFalse($member->fresh()->hasRole('Administrator'));
        $this->assertTrue($role->fresh()->hasPermissionTo('teilnehmer.index'));
        $this->assertSame($before, RoleDataAccessSetting::scopeForUser($member->fresh(), 'participant'));
        $this->assertSame('#123456', $role->fresh()->color);
        $this->assertDatabaseCount('model_has_roles', 1);
        $this->assertSame($permissionIds, $role->fresh()->permissions()->pluck('permissions.id')->sort()->values()->all());
    }

    public function test_edit_requires_permission_and_valid_distinct_label(): void
    {
        $user = User::factory()->create();
        $role = $this->role('Ausbilder');
        $this->role('Administrator');
        $this->actingAs($user)->putJson(route('rolle.update', $role->id), ['display_name' => 'Test'])->assertForbidden();
        $this->grantTestPermission($user, 'rolle.update');
        foreach (['', '   ', 'Administrator', str_repeat('x', 256), ['invalid']] as $label) {
            $this->putJson(route('rolle.update', $role->id), ['display_name' => $label])->assertUnprocessable();
        }
        $this->role('Andere')->update(['display_name' => 'Fachkraft']);
        $this->putJson(route('rolle.update', $role->id), ['display_name' => 'Fachkraft'])->assertUnprocessable();
        $this->putJson(route('rolle.update', $role->id), ['display_name' => 'Ausbilder'])->assertOk();
        $this->assertSame('Ausbilder', $role->fresh()->toArray()['display_name']);
    }

    public function test_renamed_administrator_keeps_protection_and_default_scope(): void
    {
        $editor = User::factory()->create();
        $this->grantTestPermission($editor, 'berechtigung.update');
        $role = $this->role('Administrator');
        $this->actingAs($editor)->putJson(route('rolle.update', $role->id), ['display_name' => 'Administration'])->assertOk();
        $this->deleteJson(route('rolle.destroy', $role->id))->assertUnprocessable();
        $this->assertSame('all', RoleDataAccessSetting::valuesForRole($role->fresh())['participant_scope']);
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Administrator']);
    }
}
