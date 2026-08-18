<?php

namespace Tests\Feature;

use Tests\TestCase;

class SystemKeepAliveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'array']);
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
            ->assertExactJson(['authenticated' => true]);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
