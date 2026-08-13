<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StaffAccountInvitation;
use App\Models\User;
use App\Notifications\StaffAccountInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffAccountInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_account_can_be_created_with_a_manually_assigned_password(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $this->grantTestPermission($admin, 'benutzer.store');
        $role = $this->employeeRole();

        $this->actingAs($admin)
            ->postJson(route('user.store'), $this->payload($role, [
                'account_setup_method' => 'manual',
                'password' => 'SicheresPasswort2026',
                'password_confirmation' => 'SicheresPasswort2026',
            ]))
            ->assertCreated()
            ->assertJsonPath('setup_method', 'manual')
            ->assertJsonPath('invitation_sent', null);

        $employee = User::query()->where('email', 'neue.kraft@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('SicheresPasswort2026', $employee->password));
        $this->assertDatabaseCount('staff_account_invitations', 0);
        Notification::assertNotSentTo($employee, StaffAccountInvitationNotification::class);
    }

    public function test_staff_account_can_be_invited_by_email_and_activated_once(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $this->grantTestPermission($admin, 'benutzer.store');
        $role = $this->employeeRole();

        $this->actingAs($admin)
            ->postJson(route('user.store'), $this->payload($role, [
                'account_setup_method' => 'email_invitation',
            ]))
            ->assertCreated()
            ->assertJsonPath('setup_method', 'email_invitation')
            ->assertJsonPath('invitation_sent', true);

        $employee = User::query()->where('email', 'neue.kraft@example.test')->firstOrFail();
        $this->assertNull($employee->email_verified_at);

        $token = null;
        Notification::assertSentTo(
            $employee,
            StaffAccountInvitationNotification::class,
            function (StaffAccountInvitationNotification $notification) use (&$token): bool {
                $token = $notification->token;

                return $token !== '';
            }
        );

        $this->assertNotNull($token);
        $this->assertDatabaseHas('staff_account_invitations', [
            'user_id' => $employee->id,
            'token_hash' => hash('sha256', $token),
            'accepted_at' => null,
        ]);

        auth()->logout();

        $this->get(route('staff-invitation.show', $token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/AcceptStaffInvitation')
                ->where('email', 'neue.kraft@example.test')
                ->where('employeeName', 'Neue Kraft'));

        $this->post(route('staff-invitation.accept', $token), [
            'password' => 'EigenesPasswort2026',
            'password_confirmation' => 'EigenesPasswort2026',
        ])->assertRedirect(route('login'));

        $employee->refresh();
        $this->assertTrue(Hash::check('EigenesPasswort2026', $employee->password));
        $this->assertNotNull($employee->email_verified_at);
        $this->assertNotNull(StaffAccountInvitation::query()->firstOrFail()->accepted_at);

        $this->get(route('staff-invitation.show', $token))->assertNotFound();
    }

    public function test_manual_password_must_be_confirmed_and_meet_the_security_rules(): void
    {
        $admin = User::factory()->create();
        $this->grantTestPermission($admin, 'benutzer.store');
        $role = $this->employeeRole();

        $this->actingAs($admin)
            ->postJson(route('user.store'), $this->payload($role, [
                'account_setup_method' => 'manual',
                'password' => 'kurz',
                'password_confirmation' => 'anders',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'neue.kraft@example.test']);
    }

    public function test_pending_invitation_can_be_sent_again(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $this->grantTestPermission($admin, 'benutzer.store');
        $this->grantTestPermission($admin, 'benutzer.update');
        $role = $this->employeeRole();

        $this->actingAs($admin)->postJson(route('user.store'), $this->payload($role, [
            'account_setup_method' => 'email_invitation',
        ]))->assertCreated();

        $employee = User::query()->where('email', 'neue.kraft@example.test')->firstOrFail();

        $this->postJson(route('user.invitation.store', $employee))
            ->assertOk()
            ->assertJsonPath('invitation_status', 'pending');

        $this->assertSame(2, StaffAccountInvitation::query()->where('user_id', $employee->id)->count());
        $this->assertSame(
            1,
            StaffAccountInvitation::query()
                ->where('user_id', $employee->id)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->count()
        );
    }

    private function payload(Role $role, array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Neue',
            'last_name' => 'Kraft',
            'username' => 'neue.kraft',
            'email' => 'neue.kraft@example.test',
            'rollen' => [$role->id],
            'projekt_zuweisungen' => [],
        ], $overrides);
    }

    private function employeeRole(): Role
    {
        return Role::query()->create([
            'name' => 'Mitarbeiter',
            'guard_name' => 'web',
            'color' => '#64748b',
        ]);
    }
}
