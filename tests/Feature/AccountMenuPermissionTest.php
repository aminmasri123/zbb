<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountMenuPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_menu_permissions_are_shared_independently(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'dashboard.index');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageProfile', false)
                ->where('canManageNotifications', false)
                ->has('notify.notifications', 0));

        $this->grantTestPermission($user, 'user.profil');

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageProfile', true)
                ->where('canManageNotifications', false));

        $this->grantTestPermission($user, 'notifications.readAll');

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageProfile', true)
                ->where('canManageNotifications', true));
    }

    public function test_notifications_page_is_forbidden_without_notification_permission(): void
    {
        $allowedUser = User::factory()->create();
        $this->grantTestPermission($allowedUser, 'notifications.readAll');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertForbidden();
    }
}
