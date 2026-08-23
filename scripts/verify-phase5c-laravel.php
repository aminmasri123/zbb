<?php

use App\Services\Ai\AgentClient;
use App\Services\Ai\AgentRequestSigner;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $ready = $app->make(AgentClient::class)->ready();
    printf("readiness=%s ollama_version=%s\n", $ready['status'], $ready['ollama_version']);
} catch (Throwable $exception) {
    $path = '/health/ready';
    $timestamp = (string) time();
    $nonce = (string) Str::uuid();
    $signer = $app->make(AgentRequestSigner::class);
    $signature = $signer->sign(
        (string) config('services.zbb_ai_agent.secret'),
        $timestamp,
        $nonce,
        'GET',
        $path,
        '',
    );
    $response = Http::withHeaders([
        'X-ZBB-Key-Id' => (string) config('services.zbb_ai_agent.key_id'),
        'X-ZBB-Timestamp' => $timestamp,
        'X-ZBB-Nonce' => $nonce,
        'X-ZBB-Signature' => $signature,
    ])->withBody('', 'application/json')->get(rtrim((string) config('services.zbb_ai_agent.base_url'), '/').$path);

    printf("readiness=failed http_status=%d detail=%s\n", $response->status(), (string) $response->json('detail', 'unknown'));
    exit(1);
}
