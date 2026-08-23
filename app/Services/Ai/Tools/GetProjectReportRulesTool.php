<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\AiProjectAuthorizer;
use App\Services\Ai\AiRunContext;
use App\Services\Ai\Contracts\AiTool;
use Illuminate\Validation\ValidationException;

final class GetProjectReportRulesTool implements AiTool
{
    public const NAME = 'get_project_report_rules';

    public const PERMISSION = 'ai.report.use';

    public function __construct(private readonly AiProjectAuthorizer $authorizer) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function execute(User $user, AiRunContext $context, array $arguments): array
    {
        if ($arguments !== []) {
            throw ValidationException::withMessages([
                'arguments' => 'Dieses Tool akzeptiert keine vom Modell gesetzten Argumente.',
            ]);
        }

        $project = $this->authorizer->authorize($user, $context, self::PERMISSION);

        return [
            'project_id' => (int) $project->id,
            'rules' => $project->ruleSettings(),
            'features' => $project->featureSettings(),
        ];
    }
}
