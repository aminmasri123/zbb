<?php

namespace Tests\Feature;

use App\Models\AppCalendarEvent;
use App\Models\AppCalendar;
use App\Models\Berechtigungskategorie;
use App\Models\Projekt;
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

    public function test_assignee_sees_project_event_but_unassigned_project_colleague_does_not(): void
    {
        $manager = User::factory()->create();
        $assignee = User::factory()->create();
        $colleague = User::factory()->create();
        $project = Projekt::factory()->create();
        $manager->projekte()->attach($project->id);
        $assignee->projekte()->attach($project->id);
        $colleague->projekte()->attach($project->id);
        $this->givePermission($assignee, 'apps.calendar.events');
        $this->givePermission($colleague, 'apps.calendar.events');

        $calendar = AppCalendar::create([
            'owner_user_id' => $manager->id,
            'project_id' => $project->id,
            'kind' => 'project',
            'name' => $project->name,
            'visibility' => 'project',
        ]);
        $event = AppCalendarEvent::create([
            'owner_user_id' => $manager->id,
            'calendar_id' => $calendar->id,
            'project_id' => $project->id,
            'title' => 'Schule A',
            'starts_at' => '2026-09-10 08:00:00',
            'ends_at' => '2026-09-10 16:00:00',
            'visibility' => 'project',
            'audience' => 'assignees',
        ]);
        $event->attendees()->create([
            'user_id' => $assignee->id,
            'assigned_by_user_id' => $manager->id,
            'access_level' => 'responsible',
            'response_required' => true,
            'response' => 'pending',
        ]);

        $this->actingAs($assignee)
            ->getJson(route('apps.calendar.events', ['year' => 2026]))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.title', 'Schule A');

        $this->actingAs($colleague)
            ->getJson(route('apps.calendar.events', ['year' => 2026]))
            ->assertOk()
            ->assertJsonCount(0, 'items');
    }

    public function test_responsible_employee_can_accept_assigned_event(): void
    {
        $manager = User::factory()->create();
        $employee = User::factory()->create();
        $project = Projekt::factory()->create();
        $manager->projekte()->attach($project->id);
        $employee->projekte()->attach($project->id);
        $this->givePermission($employee, 'apps.calendar.respond');

        $event = AppCalendarEvent::create([
            'owner_user_id' => $manager->id,
            'project_id' => $project->id,
            'title' => 'Schule B',
            'starts_at' => '2026-09-11 08:00:00',
            'ends_at' => '2026-09-11 16:00:00',
            'visibility' => 'project',
            'audience' => 'assignees',
        ]);
        $assignment = $event->attendees()->create([
            'user_id' => $employee->id,
            'assigned_by_user_id' => $manager->id,
            'access_level' => 'responsible',
            'response_required' => true,
            'response' => 'pending',
        ]);

        $this->actingAs($employee)
            ->postJson(route('apps.calendar.respond', $event), ['response' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('event.my_assignment.response', 'accepted');

        $this->assertSame('accepted', $assignment->refresh()->response);
        $this->assertNotNull($assignment->responded_at);
    }

    public function test_project_manager_can_assign_employee_and_notification_is_created(): void
    {
        $manager = User::factory()->create();
        $employee = User::factory()->create();
        $project = Projekt::factory()->create();
        $manager->projekte()->attach($project->id);
        $employee->projekte()->attach($project->id);
        foreach (['apps.calendar.store', 'apps.calendar.project.manage', 'apps.calendar.project.assign'] as $permission) {
            $this->givePermission($manager, $permission);
        }

        $calendar = AppCalendar::create([
            'owner_user_id' => $manager->id,
            'project_id' => $project->id,
            'kind' => 'project',
            'name' => $project->name,
            'visibility' => 'project',
        ]);

        $response = $this->actingAs($manager)->postJson(route('apps.calendar.store'), [
            'title' => 'Einsatz Schule C',
            'calendar_id' => $calendar->id,
            'starts_at' => '2026-09-12 08:00:00',
            'ends_at' => '2026-09-12 16:00:00',
            'all_day' => true,
            'visibility' => 'project',
            'audience' => 'assignees',
            'project_id' => $project->id,
            'responsible_user_ids' => [$employee->id],
            'viewer_user_ids' => [],
            'send_notification' => true,
        ]);

        $response->assertOk()->assertJsonPath('event.attendees.0.user_id', $employee->id);
        $this->assertDatabaseHas('app_calendar_event_attendees', [
            'user_id' => $employee->id,
            'response' => 'pending',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $employee->id,
        ]);
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
