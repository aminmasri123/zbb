<?php

namespace Tests\Feature;

use Tests\TestCase;

class SystemKeepAliveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'array',
            'session.lifetime' => 30,
        ]);
    }

    public function test_keepalive_is_lightweight_and_available_without_login(): void
    {
        $response = $this->get('/system/keepalive');

        $response->assertNoContent();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_session_status_requires_login(): void
    {
        $this->getJson('/system/session-status')
            ->assertUnauthorized();
    }

    public function test_session_status_confirms_an_authenticated_session(): void
    {
        $user = new \App\Models\User();
        $user->forceFill([
            'id' => 1,
            'name' => 'Session Test',
            'email' => 'session-test@example.test',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/system/session-status');

        $response->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('lifetime_seconds', 1800)
            ->assertJsonStructure(['expires_at', 'remaining_seconds']);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_status_check_does_not_extend_real_user_activity(): void
    {
        $now = now()->startOfSecond();
        \Illuminate\Support\Carbon::setTestNow($now);
        $user = $this->testUser();
        $lastActivity = $now->timestamp - 60;

        $this->actingAs($user)
            ->withSession(['auth_last_user_activity_at' => $lastActivity])
            ->getJson('/system/session-status')
            ->assertOk()
            ->assertJsonPath('expires_at', $lastActivity + 1800)
            ->assertJsonPath('remaining_seconds', 1740);

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_real_activity_extends_the_idle_deadline(): void
    {
        $now = now()->startOfSecond();
        \Illuminate\Support\Carbon::setTestNow($now);

        $this->actingAs($this->testUser())
            ->withSession([
                'auth_last_user_activity_at' => $now->timestamp - 60,
                '_token' => 'session-activity-test-token',
            ])
            ->withHeader('X-CSRF-TOKEN', 'session-activity-test-token')
            ->postJson('/system/session-activity')
            ->assertOk()
            ->assertJsonPath('expires_at', $now->timestamp + 1800)
            ->assertJsonPath('remaining_seconds', 1800);

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_expired_idle_session_is_rejected(): void
    {
        $now = now()->startOfSecond();
        \Illuminate\Support\Carbon::setTestNow($now);

        $this->actingAs($this->testUser())
            ->withSession(['auth_last_user_activity_at' => $now->timestamp - 1800])
            ->getJson('/system/session-status')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'session_expired');

        \Illuminate\Support\Carbon::setTestNow();
    }

    private function testUser(): \App\Models\User
    {
        $user = new \App\Models\User();
        $user->forceFill([
            'id' => 1,
            'name' => 'Session Test',
            'email' => 'session-test@example.test',
        ]);

        return $user;
    }
}
