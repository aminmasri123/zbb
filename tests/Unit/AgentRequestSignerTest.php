<?php

namespace Tests\Unit;

use App\Services\Ai\AgentRequestSigner;
use PHPUnit\Framework\TestCase;

class AgentRequestSignerTest extends TestCase
{
    public function test_it_matches_the_agent_canonical_signature(): void
    {
        $signature = (new AgentRequestSigner)->sign(
            'test-secret-that-is-at-least-32-bytes-long',
            '1720000000',
            'nonce-1',
            'post',
            '/v1/agent/turn',
            '{"safe":true}',
        );

        $this->assertSame(
            '78c2faf970ab8606ce26e15865d8ea2592a8014fd72758289867943982c84837',
            $signature,
        );
    }
}
