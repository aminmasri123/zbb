<?php

namespace Tests\Feature;

use App\Models\AccessDoor;
use App\Models\AccessProfile;
use App\Models\AccessRequest;
use App\Models\Berechtigungskategorie;
use App\Models\Role;
use App\Models\Standort;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Modules\ModuleStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccessManagementModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_is_disabled_by_default_and_blocks_backend_access(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'zutritt.index');

        $this->assertFalse(app(ModuleStateResolver::class)->enabled('access_management'));

        $this->actingAs($user)
            ->get(route('zutritt.index'))
            ->assertNotFound();
    }

    public function test_only_administrator_role_may_toggle_access_module(): void
    {
        $this->ensurePermission('berechtigung.update');
        $module = $this->accessModule();

        $editor = User::factory()->create();
        $editor->givePermissionTo('berechtigung.update');

        $this->actingAs($editor)
            ->put(route('module-settings.update', $module), ['enabled' => true])
            ->assertForbidden();

        $administrator = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Administrator',
            'guard_name' => 'web',
            'color' => 'bg-orange-200',
        ]);
        $administrator->assignRole($role);
        $administrator->givePermissionTo('berechtigung.update');

        $this->actingAs($administrator)
            ->put(route('module-settings.update', $module), ['enabled' => true])
            ->assertRedirect();

        $this->assertTrue(app(ModuleStateResolver::class)->enabled('access_management'));
    }

    public function test_access_route_uses_current_role_assignment_when_permission_cache_is_stale(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'Zutrittsverwaltung-'.uniqid(),
            'guard_name' => 'web',
            'color' => 'bg-orange-200',
        ]);
        $permission = $this->ensurePermission('zutritt.index');

        $user->assignRole($role);
        $this->assertFalse($user->fresh()->can('zutritt.index'));

        DB::table('role_has_permissions')->insert([
            'permission_id' => $permission->id,
            'role_id' => $role->id,
        ]);

        $this->enableModule($user);

        $this->assertTrue($user->fresh()->hasStoredPermission('zutritt.index'));

        $this->actingAs($user)
            ->get(route('zutritt.index'))
            ->assertOk();
    }

    public function test_request_approval_and_manual_activation_require_separate_users(): void
    {
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $technician = User::factory()->create();

        $this->givePermission($requester, 'zutritt.index');
        $this->givePermission($requester, 'zutritt.antrag.store');
        $this->givePermission($requester, 'zutritt.antrag.approve');
        $this->givePermission($approver, 'zutritt.antrag.approve');
        $this->givePermission($approver, 'zutritt.aktivierung.update');
        $this->givePermission($technician, 'zutritt.aktivierung.update');
        $this->enableModule($technician);

        $profile = $this->profile();

        $this->actingAs($requester)
            ->post(route('zutritt.antraege.store'), [
                'requested_for_person_id' => $requester->person_id,
                'access_profile_id' => $profile->id,
                'valid_from' => now()->addHour()->format('Y-m-d H:i:s'),
                'valid_until' => now()->addMonth()->format('Y-m-d H:i:s'),
                'reason' => 'Projektarbeit im gesicherten Raum.',
            ])
            ->assertRedirect();

        $accessRequest = AccessRequest::query()->firstOrFail();

        $this->actingAs($requester)
            ->put(route('zutritt.antraege.decision', $accessRequest), [
                'decision' => 'approve',
                'comment' => 'Selbstfreigabe',
            ])
            ->assertUnprocessable();

        $this->actingAs($approver)
            ->put(route('zutritt.antraege.decision', $accessRequest), [
                'decision' => 'approve',
                'comment' => 'Fachlich genehmigt.',
            ])
            ->assertRedirect();

        $accessRequest->refresh();
        $this->assertSame(AccessRequest::STATUS_APPROVED, $accessRequest->status);

        $this->actingAs($approver)
            ->put(route('zutritt.antraege.activation', $accessRequest), [
                'technical_reference' => 'ZKS-1001',
                'activation_note' => 'Im Fremdsystem erfasst.',
            ])
            ->assertUnprocessable();

        $this->actingAs($technician)
            ->put(route('zutritt.antraege.activation', $accessRequest), [
                'technical_reference' => 'ZKS-1001',
                'activation_note' => 'Im Fremdsystem erfasst.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('access_requests', [
            'id' => $accessRequest->id,
            'status' => AccessRequest::STATUS_PROVISIONED,
            'approved_by_user_id' => $approver->id,
            'activated_by_user_id' => $technician->id,
            'technical_reference' => 'ZKS-1001',
        ]);
        $this->assertDatabaseHas('access_request_events', [
            'access_request_id' => $accessRequest->id,
            'event_type' => 'manually_provisioned',
            'actor_user_id' => $technician->id,
        ]);
    }

    public function test_disabling_module_preserves_access_data(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'zutritt.index');
        $this->enableModule($user);
        $profile = $this->profile();

        app(ModuleStateResolver::class)->set($this->accessModule(), false, null, $user->id);

        $this->actingAs($user)
            ->get(route('zutritt.index'))
            ->assertNotFound();

        $this->assertDatabaseHas('access_profiles', [
            'id' => $profile->id,
            'name' => 'Pilotprofil',
        ]);
    }

    private function profile(): AccessProfile
    {
        $door = AccessDoor::query()->create([
            'standort_id' => Standort::factory()->create()->id,
            'name' => 'Haupteingang Nord',
            'code' => 'NORD-01',
            'active' => true,
        ]);
        $profile = AccessProfile::query()->create([
            'name' => 'Pilotprofil',
            'description' => 'Testprofil',
            'active' => true,
        ]);
        $profile->doors()->attach($door);

        return $profile;
    }

    private function accessModule(): SystemModule
    {
        return SystemModule::query()->where('key', 'access_management')->firstOrFail();
    }

    private function enableModule(User $user): void
    {
        app(ModuleStateResolver::class)->set($this->accessModule(), true, null, $user->id);
    }

    private function givePermission(User $user, string $name): void
    {
        $this->ensurePermission($name);
        $user->givePermissionTo($name);
    }

    private function ensurePermission(string $name): Permission
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Zutrittsverwaltung'],
            ['beschreibung' => 'Test']
        );

        $permission = Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            [
                'berechtigungskategorie_id' => $category->id,
                'beschreibung' => null,
            ]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permission;
    }
}
