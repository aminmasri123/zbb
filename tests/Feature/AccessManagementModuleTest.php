<?php

namespace Tests\Feature;

use App\Models\AccessDoor;
use App\Models\AccessFloorPlan;
use App\Models\AccessProfile;
use App\Models\AccessRequest;
use App\Models\Berechtigungskategorie;
use App\Models\Role;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Modules\ModuleStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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
        $permissions = collect([
            $this->ensurePermission('zutritt.index'),
            $this->ensurePermission('zutritt.stammdaten.manage'),
        ]);

        $user->assignRole($role);
        $this->assertFalse($user->fresh()->can('zutritt.index'));

        DB::table('role_has_permissions')->insert($permissions->map(fn (Permission $permission) => [
            'permission_id' => $permission->id,
            'role_id' => $role->id,
        ])->all());

        $this->enableModule($user);

        $this->assertTrue($user->fresh()->hasStoredPermission('zutritt.index'));

        $this->actingAs($user)
            ->get(route('zutritt.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('accessPermissions.canManageMasterData', true)
                ->has('doors')
                ->has('locations')
                ->has('rooms'));
    }

    public function test_master_data_manager_can_create_and_arrange_a_private_2d_floor_plan(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $this->givePermission($user, 'zutritt.index');
        $this->givePermission($user, 'zutritt.stammdaten.manage');
        $this->enableModule($user);

        $location = Standort::factory()->create();
        $room = Raeume::query()->create([
            'standort_id' => $location->id,
            'name' => 'Büro 101',
            'raumnummer' => '101',
            'etage' => 'EG',
            'typ' => 'Büro',
        ]);
        $door = AccessDoor::query()->create([
            'standort_id' => $location->id,
            'room_to_id' => $room->id,
            'name' => 'Tür Büro 101',
            'code' => 'T-EG-101',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('zutritt.grundrisse.store'), [
                'standort_id' => $location->id,
                'floor_label' => 'EG',
                'name' => 'Hauptgebäude Erdgeschoss',
                'image' => UploadedFile::fake()->image('grundriss-eg.png', 1200, 800),
            ])
            ->assertRedirect();

        $floorPlan = AccessFloorPlan::query()->firstOrFail();
        Storage::disk('local')->assertExists($floorPlan->image_path);

        $this->actingAs($user)
            ->put(route('zutritt.grundrisse.layout.update', $floorPlan), [
                'rooms' => [[
                    'room_id' => $room->id,
                    'x_percent' => -8,
                    'y_percent' => 15,
                    'width_percent' => 30,
                    'height_percent' => 20,
                    'rotation_degrees' => 270,
                ]],
                'doors' => [[
                    'door_id' => $door->id,
                    'x_percent' => 39,
                    'y_percent' => 25,
                    'rotation_degrees' => 90,
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('access_floor_plan_rooms', [
            'access_floor_plan_id' => $floorPlan->id,
            'raum_id' => $room->id,
            'x_percent' => -8,
            'width_percent' => 30,
            'rotation_degrees' => 270,
        ]);
        $this->assertDatabaseHas('access_floor_plan_doors', [
            'access_floor_plan_id' => $floorPlan->id,
            'access_door_id' => $door->id,
            'rotation_degrees' => 90,
        ]);

        $this->actingAs($user)
            ->get(route('zutritt.grundrisse.image', $floorPlan))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, private');

        $this->actingAs($user)
            ->get(route('zutritt.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('floorPlans', 1)
                ->has('floorPlans.0.rooms', 1)
                ->where('floorPlans.0.rooms.0.rotation_degrees', 270)
                ->has('floorPlans.0.doors', 1));

        $viewer = User::factory()->create();
        $this->givePermission($viewer, 'zutritt.index');

        $this->actingAs($viewer)
            ->get(route('zutritt.grundrisse.image', $floorPlan))
            ->assertForbidden();
    }

    public function test_floor_plan_rejects_rooms_from_another_location(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $this->givePermission($user, 'zutritt.stammdaten.manage');
        $this->enableModule($user);

        $floorPlan = AccessFloorPlan::query()->create([
            'standort_id' => Standort::factory()->create()->id,
            'floor_label' => 'EG',
            'name' => 'Pilotplan',
            'image_path' => 'access-management/floor-plans/pilot.png',
            'original_name' => 'pilot.png',
            'mime_type' => 'image/png',
            'active' => true,
        ]);
        $foreignLocation = Standort::factory()->create();
        $foreignRoom = Raeume::query()->create([
            'standort_id' => $foreignLocation->id,
            'name' => 'Fremder Raum',
            'raumnummer' => 'F-1',
            'etage' => 'EG',
            'typ' => 'Büro',
        ]);

        $this->actingAs($user)
            ->put(route('zutritt.grundrisse.layout.update', $floorPlan), [
                'rooms' => [[
                    'room_id' => $foreignRoom->id,
                    'x_percent' => 10,
                    'y_percent' => 10,
                    'width_percent' => 20,
                    'height_percent' => 20,
                    'rotation_degrees' => 0,
                ]],
                'doors' => [],
            ])
            ->assertSessionHasErrors('rooms');

        $this->assertDatabaseCount('access_floor_plan_rooms', 0);
    }

    public function test_locked_floor_plan_rejects_layout_changes_until_it_is_unlocked(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'zutritt.stammdaten.manage');
        $this->enableModule($user);

        $location = Standort::factory()->create();
        $room = Raeume::query()->create([
            'standort_id' => $location->id,
            'name' => 'IT und Mediengestaltung',
            'raumnummer' => '19',
            'etage' => 'OG',
            'typ' => 'Unterrichtsraum',
        ]);
        $floorPlan = AccessFloorPlan::query()->create([
            'standort_id' => $location->id,
            'floor_label' => 'OG',
            'name' => 'Hauptgebäude OG',
            'image_path' => 'access-management/floor-plans/og.png',
            'original_name' => 'og.png',
            'mime_type' => 'image/png',
            'active' => true,
        ]);
        $placement = $floorPlan->roomPlacements()->create([
            'raum_id' => $room->id,
            'x_percent' => 10,
            'y_percent' => 15,
            'width_percent' => 20,
            'height_percent' => 20,
            'rotation_degrees' => 0,
        ]);

        $this->actingAs($user)
            ->put(route('zutritt.grundrisse.lock.update', $floorPlan), ['locked' => true])
            ->assertRedirect();

        $this->assertTrue($floorPlan->fresh()->layout_locked);

        $lockedLayout = [
            'rooms' => [[
                'room_id' => $room->id,
                'x_percent' => 50,
                'y_percent' => 15,
                'width_percent' => 20,
                'height_percent' => 20,
                'rotation_degrees' => 0,
            ]],
            'doors' => [],
        ];

        $this->actingAs($user)
            ->put(route('zutritt.grundrisse.layout.update', $floorPlan), $lockedLayout)
            ->assertSessionHasErrors('layout');

        $this->actingAs($user)
            ->delete(route('zutritt.grundrisse.destroy', $floorPlan))
            ->assertSessionHasErrors('layout');

        $this->assertDatabaseHas('access_floor_plan_rooms', [
            'id' => $placement->id,
            'x_percent' => 10,
        ]);
        $this->assertDatabaseHas('access_floor_plans', ['id' => $floorPlan->id]);

        $this->actingAs($user)
            ->put(route('zutritt.grundrisse.lock.update', $floorPlan), ['locked' => false])
            ->assertRedirect();

        $this->actingAs($user)
            ->put(route('zutritt.grundrisse.layout.update', $floorPlan), $lockedLayout)
            ->assertRedirect();

        $this->assertFalse($floorPlan->fresh()->layout_locked);
        $this->assertDatabaseHas('access_floor_plan_rooms', [
            'access_floor_plan_id' => $floorPlan->id,
            'raum_id' => $room->id,
            'x_percent' => 50,
        ]);
    }

    public function test_master_data_manager_can_link_a_placed_door_to_rooms_from_the_2d_plan(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'zutritt.stammdaten.manage');
        $this->enableModule($user);

        $location = Standort::factory()->create();
        $corridor = Raeume::query()->create([
            'standort_id' => $location->id,
            'name' => 'Flur OG',
            'raumnummer' => 'FL-OG-01',
            'etage' => 'OG',
            'typ' => 'Flur / Verkehrsfläche',
        ]);
        $room = Raeume::query()->create([
            'standort_id' => $location->id,
            'name' => 'IT und Mediengestaltung',
            'raumnummer' => '19',
            'etage' => 'OG',
            'typ' => 'Unterrichtsraum',
        ]);
        $unplacedRoom = Raeume::query()->create([
            'standort_id' => $location->id,
            'name' => 'Nicht platzierter Raum',
            'raumnummer' => '20',
            'etage' => 'OG',
            'typ' => 'Büro',
        ]);
        $door = AccessDoor::query()->create([
            'standort_id' => $location->id,
            'name' => 'Außentür G1',
            'code' => 'G1',
            'active' => true,
        ]);
        $floorPlan = AccessFloorPlan::query()->create([
            'standort_id' => $location->id,
            'floor_label' => 'OG',
            'name' => 'Hauptgebäude OG',
            'image_path' => 'access-management/floor-plans/og.png',
            'original_name' => 'og.png',
            'mime_type' => 'image/png',
            'image_width' => 1600,
            'image_height' => 900,
            'active' => true,
        ]);

        foreach ([$corridor, $room] as $index => $placedRoom) {
            $floorPlan->roomPlacements()->create([
                'raum_id' => $placedRoom->id,
                'x_percent' => 10 + $index * 30,
                'y_percent' => 10,
                'width_percent' => 20,
                'height_percent' => 20,
                'rotation_degrees' => 0,
            ]);
        }
        $floorPlan->doorPlacements()->create([
            'access_door_id' => $door->id,
            'x_percent' => 35,
            'y_percent' => 20,
            'rotation_degrees' => 0,
        ]);

        $this->actingAs($user)
            ->put(route('zutritt.grundrisse.doors.connection.update', [$floorPlan, $door]), [
                'room_from_id' => $corridor->id,
                'room_to_id' => $room->id,
                'required_room_ids' => [$corridor->id, $room->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('access_doors', [
            'id' => $door->id,
            'room_from_id' => $corridor->id,
            'room_to_id' => $room->id,
        ]);
        $this->assertDatabaseHas('access_door_room_requirements', [
            'access_door_id' => $door->id,
            'raum_id' => $room->id,
        ]);

        $this->actingAs($user)
            ->put(route('zutritt.grundrisse.doors.connection.update', [$floorPlan, $door]), [
                'room_from_id' => null,
                'room_to_id' => $room->id,
                'required_room_ids' => [$room->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('access_doors', [
            'id' => $door->id,
            'room_from_id' => null,
            'room_to_id' => $room->id,
        ]);
        $this->assertDatabaseMissing('access_door_room_requirements', [
            'access_door_id' => $door->id,
            'raum_id' => $corridor->id,
        ]);

        $this->actingAs($user)
            ->put(route('zutritt.grundrisse.doors.connection.update', [$floorPlan, $door]), [
                'room_from_id' => null,
                'room_to_id' => $room->id,
                'required_room_ids' => [$unplacedRoom->id],
            ])
            ->assertSessionHasErrors('required_room_ids');

        $this->assertSame($room->id, $door->fresh()->room_to_id);
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

    public function test_master_data_manager_can_update_a_profile_without_changing_existing_request_snapshots(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'zutritt.stammdaten.manage');
        $this->givePermission($user, 'zutritt.antrag.store');
        $this->enableModule($user);

        $profile = $this->profile();
        $originalDoor = $profile->doors()->firstOrFail();

        $this->actingAs($user)
            ->post(route('zutritt.antraege.store'), [
                'requested_for_person_id' => $user->person_id,
                'access_profile_id' => $profile->id,
                'valid_from' => now()->addHour()->format('Y-m-d H:i:s'),
                'valid_until' => now()->addMonth()->format('Y-m-d H:i:s'),
                'reason' => 'Bestehender Antrag vor der Profiländerung.',
            ])
            ->assertRedirect();

        $replacementDoor = AccessDoor::query()->create([
            'standort_id' => Standort::factory()->create()->id,
            'name' => 'Außentür G1',
            'code' => 'G1-UPDATE',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('zutritt.profile.update', $profile), [
                'name' => 'Zugang OG 19',
                'description' => 'Aktualisiertes Profil',
                'door_ids' => [$replacementDoor->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('access_profiles', [
            'id' => $profile->id,
            'name' => 'Zugang OG 19',
            'description' => 'Aktualisiertes Profil',
        ]);
        $this->assertDatabaseMissing('access_profile_door', [
            'access_profile_id' => $profile->id,
            'access_door_id' => $originalDoor->id,
        ]);
        $this->assertDatabaseHas('access_profile_door', [
            'access_profile_id' => $profile->id,
            'access_door_id' => $replacementDoor->id,
        ]);

        $snapshot = AccessRequest::query()->firstOrFail()->profile_snapshot;
        $this->assertSame('Pilotprofil', $snapshot['name']);
        $this->assertSame($originalDoor->id, $snapshot['doors'][0]['id']);
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
