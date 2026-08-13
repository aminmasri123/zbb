<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_all_own_notifications_without_deleting_notifications_of_others(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->grantTestPermission($user, 'notifications.readAll');

        $ownNotificationIds = [
            $this->createNotification($user, 'Erste Benachrichtigung'),
            $this->createNotification($user, 'Zweite Benachrichtigung'),
        ];
        $otherNotificationId = $this->createNotification($otherUser, 'Fremde Benachrichtigung');

        $this->actingAs($user)
            ->delete(route('notifications.destroyAll'))
            ->assertRedirect()
            ->assertSessionHas('success', '2 Benachrichtigungen wurden entfernt.');

        foreach ($ownNotificationIds as $notificationId) {
            $this->assertDatabaseMissing('notifications', ['id' => $notificationId]);
        }

        $this->assertDatabaseHas('notifications', ['id' => $otherNotificationId]);
    }

    public function test_user_cannot_delete_another_users_notification(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->grantTestPermission($user, 'notifications.readAll');
        $notificationId = $this->createNotification($otherUser, 'Fremde Benachrichtigung');

        $this->actingAs($user)
            ->delete(route('notifications.destroy', $notificationId))
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $notificationId]);
    }

    private function createNotification(User $user, string $message): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'Tests\\Notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['message' => $message, 'typ' => 'Test'], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
