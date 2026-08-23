<?php

namespace Tests\Feature;

use App\Services\Ai\AgentClient;
use App\Services\Ai\AgentRequestSigner;
use App\Services\Ai\AgentTurnPayload;
use App\Services\Ai\Exceptions\AgentUnavailableException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class AgentClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.zbb_ai_agent', [
            'base_url' => 'http://127.0.0.1:18000',
            'key_id' => 'laravel',
            'secret' => 'test-secret-that-is-at-least-32-bytes-long',
            'connect_timeout' => 3,
            'timeout' => 130,
            'max_response_bytes' => 1000000,
        ]);
        Http::preventStrayRequests();
    }

    public function test_it_signs_the_exact_json_body_sent_to_the_agent(): void
    {
        Http::fake([
            'http://127.0.0.1:18000/v1/agent/turn' => Http::response([
                'kind' => 'tool_calls',
                'run_id' => $this->runId(),
                'calls' => [[
                    'call_id' => 'call-1',
                    'name' => 'get_project_report_rules',
                    'arguments' => [],
                ]],
            ]),
        ]);

        $response = app(AgentClient::class)->turn($this->payload());

        $this->assertSame('tool_calls', $response['kind']);
        Http::assertSent(function (Request $request): bool {
            $timestamp = $request->header('X-ZBB-Timestamp')[0] ?? '';
            $nonce = $request->header('X-ZBB-Nonce')[0] ?? '';
            $signature = (new AgentRequestSigner)->sign(
                'test-secret-that-is-at-least-32-bytes-long',
                $timestamp,
                $nonce,
                'POST',
                '/v1/agent/turn',
                $request->body(),
            );

            return $request->url() === 'http://127.0.0.1:18000/v1/agent/turn'
                && ($request->header('X-ZBB-Key-Id')[0] ?? null) === 'laravel'
                && ($request->header('X-ZBB-Signature')[0] ?? null) === $signature;
        });
    }

    public function test_it_rejects_a_tool_call_outside_the_request_allowlist(): void
    {
        Http::fake([
            '*' => Http::response([
                'kind' => 'tool_calls',
                'run_id' => $this->runId(),
                'calls' => [[
                    'call_id' => 'call-1',
                    'name' => 'get_participant_identity_summary',
                    'arguments' => [],
                ]],
            ]),
        ]);

        $this->expectException(AgentUnavailableException::class);

        app(AgentClient::class)->turn($this->payload());
    }

    public function test_it_rejects_a_mismatched_run_id(): void
    {
        Http::fake(['*' => Http::response([
            'kind' => 'final',
            'run_id' => 'f81d4fae-7dec-41d0-a765-00a0c91e6bf6',
            'report' => [],
        ])]);

        $this->expectException(AgentUnavailableException::class);

        app(AgentClient::class)->turn($this->payload());
    }

    public function test_it_rejects_a_supported_claim_with_an_unknown_source(): void
    {
        Http::fake(['*' => Http::response([
            'kind' => 'final',
            'run_id' => $this->runId(),
            'report' => [
                'report_type' => 'luv',
                'title' => 'Manipulierter Entwurf',
                'sections' => [[
                    'heading' => 'Bewertung',
                    'claims' => [[
                        'claim_id' => 'claim-1',
                        'text' => 'Nicht belegte Aussage.',
                        'status' => 'supported',
                        'source_ids' => ['unknown-source'],
                    ]],
                ]],
                'warnings' => [],
            ],
        ])]);

        $this->expectException(AgentUnavailableException::class);

        app(AgentClient::class)->turn($this->payload());
    }

    public function test_it_rejects_a_changed_report_type(): void
    {
        Http::fake(['*' => Http::response([
            'kind' => 'final',
            'run_id' => $this->runId(),
            'report' => [
                'report_type' => 'final',
                'title' => 'Falscher Typ',
                'sections' => [['heading' => 'Datenlage', 'claims' => []]],
                'warnings' => [],
            ],
        ])]);

        $this->expectException(AgentUnavailableException::class);

        app(AgentClient::class)->turn($this->payload());
    }

    public function test_it_fails_closed_on_an_agent_error_without_exposing_the_body(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'internal-secret'], 500)]);

        try {
            app(AgentClient::class)->turn($this->payload());
            $this->fail('Expected AgentUnavailableException.');
        } catch (AgentUnavailableException $exception) {
            $this->assertStringNotContainsString('internal-secret', $exception->getMessage());
        }
    }

    public function test_it_rejects_a_non_loopback_agent_url(): void
    {
        config()->set('services.zbb_ai_agent.base_url', 'http://10.100.1.30:8000');

        $this->expectException(InvalidArgumentException::class);

        app(AgentClient::class)->ready();
    }

    public function test_it_accepts_a_workspace_response_with_known_page_citations(): void
    {
        Http::fake(['*' => Http::response([
            'run_id'=>$this->runId(),'task'=>'summarize','title'=>'Kurzfassung','content'=>'Belegter Inhalt.',
            'citations'=>[['source_id'=>'document-1-page-2','page'=>2]],'warnings'=>[],
        ])]);
        $response=app(AgentClient::class)->generate(['run_id'=>$this->runId(),'task'=>'summarize','instruction'=>'Zusammenfassen','sources'=>[['source_id'=>'document-1-page-2','label'=>'A.pdf','page'=>2,'text'=>'Inhalt']],'image_base64'=>null]);
        $this->assertSame('Kurzfassung',$response['title']);
    }

    public function test_it_rejects_an_unknown_workspace_citation(): void
    {
        Http::fake(['*' => Http::response([
            'run_id'=>$this->runId(),'task'=>'summarize','title'=>'Kurzfassung','content'=>'Inhalt',
            'citations'=>[['source_id'=>'fremd','page'=>9]],'warnings'=>[],
        ])]);
        $this->expectException(AgentUnavailableException::class);
        app(AgentClient::class)->generate(['run_id'=>$this->runId(),'task'=>'summarize','instruction'=>'Zusammenfassen','sources'=>[['source_id'=>'document-1-page-2','label'=>'A.pdf','page'=>2,'text'=>'Inhalt']],'image_base64'=>null]);
    }

    private function payload(): AgentTurnPayload
    {
        return new AgentTurnPayload(
            runId: $this->runId(),
            projectId: 17,
            participantId: 31,
            reportType: 'luv',
            fromDate: '2026-01-01',
            untilDate: '2026-06-30',
            userRequest: 'Erstelle einen belegten Entwurf.',
            allowedTools: ['get_project_report_rules'],
        );
    }

    private function runId(): string
    {
        return '123e4567-e89b-42d3-a456-426614174000';
    }
}
