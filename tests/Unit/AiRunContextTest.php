<?php

namespace Tests\Unit;

use App\Services\Ai\AiRunContext;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AiRunContextTest extends TestCase
{
    public function test_it_accepts_only_explicit_valid_tool_names(): void
    {
        $context = new AiRunContext(7, 19, ['get_project_report_rules']);

        $this->assertTrue($context->allows('get_project_report_rules'));
        $this->assertFalse($context->allows('get_participant_data'));
    }

    public function test_it_rejects_duplicate_or_malformed_tool_names(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AiRunContext(7, 19, ['get_project_report_rules', 'get_project_report_rules']);
    }

    public function test_it_rejects_invalid_identity_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AiRunContext(0, 19, ['get_project_report_rules']);
    }
}
