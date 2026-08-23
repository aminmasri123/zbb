<?php

namespace App\Services\Ai;

use App\Models\Projekt;
use App\Models\User;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Auth\Access\AuthorizationException;

final class AiProjectAuthorizer
{
    public function __construct(private readonly ActiveProjectContext $projects) {}

    /**
     * Re-authorize every tool execution. Never trust identity or project data
     * originating from the model response.
     */
    public function authorize(User $user, AiRunContext $context, string $permission): Projekt
    {
        if ((int) $user->getKey() !== $context->userId
            || (int) $user->current_team_id !== $context->projectId
            || ! $user->hasStoredPermission($permission)) {
            throw new AuthorizationException('Der KI-Tool-Aufruf ist nicht autorisiert.');
        }

        $project = $this->projects->forUser($user, $context->projectId);

        if (! $project || ! $project->aktiv) {
            throw new AuthorizationException('Der KI-Tool-Aufruf ist nicht autorisiert.');
        }

        return $project;
    }
}
