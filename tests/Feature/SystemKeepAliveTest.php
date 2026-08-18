<?php

namespace Tests\Feature;

use Tests\TestCase;

class SystemKeepAliveTest extends TestCase
{
    public function test_keepalive_is_lightweight_and_available_without_login(): void
    {
        $response = $this->get('/system/keepalive');

        $response->assertNoContent();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
