<?php

namespace Tests\Unit;

use App\Services\Ai\AgentTurnPayload;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AgentTurnPayloadTest extends TestCase
{
    public function test_it_builds_the_strict_agent_contract(): void
    {
        $payload = $this->payload();

        $this->assertSame(17, $payload->toArray()['project_id']);
        $this->assertSame(
            ['from_date' => '2026-01-01', 'until_date' => '2026-06-30'],
            $payload->toArray()['period'],
        );
    }

    public function test_it_rejects_an_inverted_period(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->payload(from: '2026-07-01', until: '2026-06-30');
    }

    public function test_it_rejects_tool_results_outside_the_allowlist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->payload(results: [[
            'role' => 'tool',
            'tool_name' => 'get_participant_identity_summary',
            'content' => [],
        ]]);
    }

    /** @param list<array{role: string, tool_name: string, content: array<string, mixed>}> $results */
    private function payload(
        string $from = '2026-01-01',
        string $until = '2026-06-30',
        array $results = [],
    ): AgentTurnPayload {
        return new AgentTurnPayload(
            runId: '123e4567-e89b-42d3-a456-426614174000',
            projectId: 17,
            participantId: 31,
            reportType: 'luv',
            fromDate: $from,
            untilDate: $until,
            userRequest: 'Erstelle einen belegten Entwurf.',
            allowedTools: ['get_project_report_rules'],
            toolResults: $results,
        );
    }
}
