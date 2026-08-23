<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Ai\Contracts\AiTool;
use App\Services\Ai\Tools\GetProjectReportRulesTool;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

final class AiToolRegistry
{
    /** @var array<string, AiTool> */
    private array $tools;

    public function __construct(GetProjectReportRulesTool $projectReportRules)
    {
        $this->tools = [$projectReportRules->name() => $projectReportRules];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(
        User $user,
        AiRunContext $context,
        string $toolName,
        array $arguments = [],
    ): array {
        if (! $context->allows($toolName)) {
            throw new AuthorizationException('Das Tool ist fuer diesen KI-Lauf nicht freigegeben.');
        }

        $tool = $this->tools[$toolName] ?? null;

        if (! $tool) {
            throw new InvalidArgumentException('Unbekanntes KI-Tool.');
        }

        return $tool->execute($user, $context, $arguments);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->tools);
    }
}
