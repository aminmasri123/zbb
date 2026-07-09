<?php

namespace Tests\Feature;

use App\Models\AppCalendarEvent;
use App\Models\Berechtigungskategorie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AppsCalendarWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_group_move_keeps_signed_day_delta_for_excluded_dates(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'apps.calendar.move');

        $event = AppCalendarEvent::create([
            'owner_user_id' => $user->id,
            'title' => 'Projektwoche',
            'starts_at' => '2026-01-10 09:00:00',
            'ends_at' => '2026-01-12 10:00:00',
            'all_day' => false,
            'include_weekends' => true,
            'excluded_dates' => ['2026-01-11'],
            'visibility' => 'private',
        ]);

        $response = $this->actingAs($user)->postJson(route('apps.calendar.move', $event), [
            'mode' => 'group',
            'target_date' => '2026-01-05',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $event->refresh();

        $this->assertSame('2026-01-05 09:00:00', $event->starts_at->toDateTimeString());
        $this->assertSame('2026-01-07 10:00:00', $event->ends_at->toDateTimeString());
        $this->assertSame(['2026-01-06'], $event->excluded_dates);
    }

    private function givePermission(User $user, string $permissionName): void
    {
        $categoryId = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Apps'],
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
        $user->givePermissionTo($permissionName);
    }
}
