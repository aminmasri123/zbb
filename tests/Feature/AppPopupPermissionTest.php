<?php

namespace Tests\Feature;

use App\Models\AppPopup;
use App\Models\Berechtigungskategorie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AppPopupPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_popup_is_not_shared_with_user_without_popup_permission(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'dashboard.index');
        $this->ensurePermission('apps.popups');
        $this->publicPopup();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('appPopups', 0));
    }

    public function test_popup_is_shared_with_user_with_popup_permission(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'dashboard.index');
        $this->givePermission($user, 'apps.popups');
        $popup = $this->publicPopup();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('appPopups', 1)
                ->where('appPopups.0.id', $popup->id)
            );
    }

    public function test_popup_page_is_forbidden_without_popup_permission(): void
    {
        $user = User::factory()->create();
        $this->ensurePermission('apps.popups');

        $this->actingAs($user)
            ->get(route('apps.popups'))
            ->assertForbidden();
    }

    private function publicPopup(): AppPopup
    {
        return AppPopup::query()->create([
            'owner_user_id' => User::factory()->create()->id,
            'title' => 'Interner Hinweis',
            'message' => 'Nur mit Popup-Berechtigung sichtbar.',
            'level' => 'info',
            'active' => true,
            'visibility' => 'all',
        ]);
    }

    private function givePermission(User $user, string $name): void
    {
        $this->ensurePermission($name);
        $user->givePermissionTo($name);
    }

    private function ensurePermission(string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Apps'],
            ['beschreibung' => ''],
        );
        Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
