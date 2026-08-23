<?php

namespace App\Services\Ai;

final class AgentRequestSigner
{
    public function sign(
        string $secret,
        string $timestamp,
        string $nonce,
        string $method,
        string $path,
        string $body,
    ): string {
        $canonical = implode("\n", [
            $timestamp,
            $nonce,
            strtoupper($method),
            $path,
            hash('sha256', $body),
        ]);

        return hash_hmac('sha256', $canonical, $secret);
    }
}
