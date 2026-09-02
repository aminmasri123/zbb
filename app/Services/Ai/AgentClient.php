<?php

namespace App\Services\Ai;

use App\Services\Ai\Exceptions\AgentUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

final class AgentClient
{
    private string $baseUrl;

    private string $keyId;

    private string $secret;

    private int $connectTimeout;

    private int $timeout;

    private int $maxResponseBytes;

    public function __construct(private readonly AgentRequestSigner $signer)
    {
        $this->baseUrl = rtrim((string) config('services.zbb_ai_agent.base_url'), '/');
        $this->keyId = trim((string) config('services.zbb_ai_agent.key_id'));
        $this->secret = (string) config('services.zbb_ai_agent.secret');
        $this->connectTimeout = (int) config('services.zbb_ai_agent.connect_timeout');
        $this->timeout = (int) config('services.zbb_ai_agent.timeout');
        $this->maxResponseBytes = (int) config('services.zbb_ai_agent.max_response_bytes');
    }

    /** @return array{status: string, ollama_version: string} */
    public function ready(): array
    {
        $response = $this->request('GET', '/health/ready', '');

        if (($response['status'] ?? null) !== 'ok' || ! is_string($response['ollama_version'] ?? null)) {
            throw new AgentUnavailableException('Der KI-Agent lieferte eine ungueltige Readiness-Antwort.');
        }

        return ['status' => 'ok', 'ollama_version' => $response['ollama_version']];
    }

    /** @return array<string, mixed> */
    public function turn(AgentTurnPayload $payload): array
    {
        try {
            $body = json_encode($payload->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Die KI-Anfrage kann nicht serialisiert werden.', previous: $exception);
        }

        $response = $this->request('POST', '/v1/agent/turn', $body);
        $kind = $response['kind'] ?? null;

        if (! in_array($kind, ['tool_calls', 'final'], true)
            || ($response['run_id'] ?? null) !== $payload->runId) {
            throw new AgentUnavailableException('Der KI-Agent lieferte eine ungueltige Laufantwort.');
        }

        if ($kind === 'tool_calls') {
            $this->validateToolCalls($response, $payload);
        } else {
            $this->validateFinalReport($response['report'] ?? null, $payload);
        }

        return $response;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function generate(array $payload): array
    {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Die KI-Arbeitsbereichsanfrage kann nicht serialisiert werden.', previous: $exception);
        }
        $response = $this->request(
            'POST',
            '/v1/workspace/generate',
            $body,
            max(1, (int) config('services.zbb_ai_workspace.timeout', 300)),
        );
        $tasks = ['chat', 'cover_letter', 'summarize', 'compare', 'image_analysis'];
        if (($response['run_id'] ?? null) !== ($payload['run_id'] ?? null)
            || ! in_array($response['task'] ?? null, $tasks, true)
            || ($response['task'] ?? null) !== ($payload['task'] ?? null)
            || ! $this->boundedString($response['title'] ?? null, 1, 300)
            || ! $this->boundedString($response['content'] ?? null, 1, 30000)
            || ! is_array($response['citations'] ?? null)
            || ! is_array($response['warnings'] ?? null)) {
            throw new AgentUnavailableException('Der KI-Agent lieferte eine ungueltige Arbeitsbereichsantwort.');
        }
        $known = [];
        foreach (($payload['sources'] ?? []) as $source) {
            $known[($source['source_id'] ?? '').':'.($source['page'] ?? '')] = true;
        }
        foreach ($response['citations'] as $citation) {
            if (! is_array($citation) || ! isset($known[($citation['source_id'] ?? '').':'.($citation['page'] ?? '')])) {
                throw new AgentUnavailableException('Der KI-Agent zitierte eine unbekannte Arbeitsbereichsquelle.');
            }
        }
        return $response;
    }

    /** @return array<string, mixed> */
    private function request(string $method, string $path, string $body, ?int $timeout = null): array
    {
        $this->validateConfiguration();

        $timestamp = (string) time();
        $nonce = (string) Str::uuid();
        $signature = $this->signer->sign($this->secret, $timestamp, $nonce, $method, $path, $body);

        try {
            $response = Http::connectTimeout($this->connectTimeout)
                ->timeout($timeout ?? $this->timeout)
                ->acceptJson()
                ->withHeaders([
                    'X-ZBB-Key-Id' => $this->keyId,
                    'X-ZBB-Timestamp' => $timestamp,
                    'X-ZBB-Nonce' => $nonce,
                    'X-ZBB-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->send($method, $this->baseUrl.$path);
        } catch (ConnectionException $exception) {
            throw new AgentUnavailableException('Der KI-Agent ist nicht erreichbar.', previous: $exception);
        }

        $raw = $response->body();
        if (! $response->successful()) {
            $detail = $response->json('detail');
            $encodedDetail = is_array($detail)
                ? json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;
            $safeDetail = is_string($detail) && $detail !== ''
                ? mb_substr($detail, 0, 1000)
                : (is_string($encodedDetail) && $encodedDetail !== ''
                    ? mb_substr($encodedDetail, 0, 1000)
                    : 'keine Detailmeldung');

            throw new AgentUnavailableException(sprintf(
                'Der KI-Agent antwortete mit HTTP %d (%s).',
                $response->status(),
                $safeDetail,
            ));
        }

        if (strlen($raw) > $this->maxResponseBytes) {
            throw new AgentUnavailableException('Die Antwort des KI-Agenten war zu groß.');
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AgentUnavailableException('Der KI-Agent lieferte kein gueltiges JSON.', previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new AgentUnavailableException('Der KI-Agent lieferte eine ungueltige Antwort.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $response */
    private function validateToolCalls(array $response, AgentTurnPayload $payload): void
    {
        $calls = $response['calls'] ?? null;
        if (! is_array($calls) || $calls === [] || count($calls) > 8) {
            throw new AgentUnavailableException('Der KI-Agent lieferte ungueltige Tool-Aufrufe.');
        }

        foreach ($calls as $call) {
            if (! is_array($call)
                || ! is_string($call['call_id'] ?? null)
                || ($call['call_id'] ?? '') === ''
                || ! in_array($call['name'] ?? null, $payload->allowedTools, true)
                || ! is_array($call['arguments'] ?? null)) {
                throw new AgentUnavailableException('Der KI-Agent lieferte einen nicht erlaubten Tool-Aufruf.');
            }
        }
    }

    private function validateFinalReport(mixed $report, AgentTurnPayload $payload): void
    {
        if (! is_array($report)
            || ! $this->hasExactKeys($report, ['report_type', 'sections', 'title', 'warnings'])
            || ($report['report_type'] ?? null) !== $payload->reportType
            || ! $this->boundedString($report['title'] ?? null, 1, 300)
            || ! is_array($report['sections'] ?? null)
            || ! array_is_list($report['sections'])
            || $report['sections'] === []
            || count($report['sections']) > 60
            || ! is_array($report['warnings'] ?? null)
            || ! array_is_list($report['warnings'])
            || count($report['warnings']) > 50) {
            throw new AgentUnavailableException('Der KI-Agent lieferte keinen gueltigen Bericht.');
        }

        foreach ($report['warnings'] as $warning) {
            if (! is_string($warning)) {
                throw new AgentUnavailableException('Der KI-Agent lieferte ungueltige Warnungen.');
            }
        }

        $knownSources = [];
        $this->collectSourceIds($payload->toolResults, $knownSources);
        $claimIds = [];

        foreach ($report['sections'] as $section) {
            if (! is_array($section)
                || ! $this->hasExactKeys($section, ['claims', 'heading'])
                || ! $this->boundedString($section['heading'] ?? null, 1, 200)
                || ! is_array($section['claims'] ?? null)
                || ! array_is_list($section['claims'])
                || count($section['claims']) > 100) {
                throw new AgentUnavailableException('Der KI-Agent lieferte einen ungueltigen Berichtsabschnitt.');
            }

            foreach ($section['claims'] as $claim) {
                $this->validateClaim($claim, $knownSources, $claimIds);
            }
        }
    }

    /**
     * @param  array<string, true>  $knownSources
     * @param  array<string, true>  $claimIds
     */
    private function validateClaim(mixed $claim, array $knownSources, array &$claimIds): void
    {
        if (! is_array($claim)
            || ! $this->hasExactKeys($claim, ['claim_id', 'source_ids', 'status', 'text'])
            || ! is_string($claim['claim_id'] ?? null)
            || preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $claim['claim_id']) !== 1
            || isset($claimIds[$claim['claim_id']])
            || ! $this->boundedString($claim['text'] ?? null, 1, 4000)
            || ! in_array($claim['status'] ?? null, ['supported', 'insufficient_data'], true)
            || ! is_array($claim['source_ids'] ?? null)
            || ! array_is_list($claim['source_ids'])
            || count($claim['source_ids']) > 50) {
            throw new AgentUnavailableException('Der KI-Agent lieferte eine ungueltige Aussage.');
        }

        $claimIds[$claim['claim_id']] = true;
        $sourceIds = $claim['source_ids'];

        foreach ($sourceIds as $sourceId) {
            if (! $this->boundedString($sourceId, 1, 200) || ! isset($knownSources[$sourceId])) {
                throw new AgentUnavailableException('Der KI-Agent zitierte eine unbekannte Quelle.');
            }
        }

        if (($claim['status'] === 'supported' && $sourceIds === [])
            || ($claim['status'] === 'insufficient_data' && $sourceIds !== [])) {
            throw new AgentUnavailableException('Der KI-Agent lieferte eine ungueltige Quellenzuordnung.');
        }
    }

    /** @param array<string, true> $destination */
    private function collectSourceIds(mixed $value, array &$destination): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $nested) {
            if ($key === 'source_id' && is_string($nested)) {
                $destination[$nested] = true;
            } elseif ($key === 'source_ids' && is_array($nested)) {
                foreach ($nested as $sourceId) {
                    if (is_string($sourceId)) {
                        $destination[$sourceId] = true;
                    }
                }
            } else {
                $this->collectSourceIds($nested, $destination);
            }
        }
    }

    /** @param list<string> $keys */
    private function hasExactKeys(array $value, array $keys): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($keys);

        return $actual === $keys;
    }

    private function boundedString(mixed $value, int $minimum, int $maximum): bool
    {
        return is_string($value)
            && mb_strlen($value) >= $minimum
            && mb_strlen($value) <= $maximum;
    }

    private function validateConfiguration(): void
    {
        $parts = parse_url($this->baseUrl);
        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'http'
            || ! in_array($parts['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (($parts['path'] ?? '') !== '')) {
            throw new InvalidArgumentException('Der KI-Agent muss ueber einen lokalen HTTP-Tunnel erreichbar sein.');
        }

        if ($this->keyId === '' || strlen($this->secret) < 32) {
            throw new InvalidArgumentException('Die KI-Agent-Servicecredentials fehlen oder sind zu kurz.');
        }

        if ($this->connectTimeout < 1 || $this->timeout < 1 || $this->maxResponseBytes < 1024) {
            throw new InvalidArgumentException('Die KI-Agent-Clientlimits sind ungueltig.');
        }
    }
}
