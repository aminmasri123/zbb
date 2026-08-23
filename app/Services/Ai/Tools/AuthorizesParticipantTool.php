<?php

namespace App\Services\Ai\Tools;

use App\Models\ProjektHasPersonen;
use App\Models\User;
use App\Services\Ai\AiProjectAuthorizer;
use App\Services\Ai\AiRunContext;
use Illuminate\Auth\Access\AuthorizationException;

trait AuthorizesParticipantTool
{
    private function participation(User $user, AiRunContext $context): ProjektHasPersonen
    {
        $this->authorizer->authorize($user, $context, GetProjectReportRulesTool::PERMISSION);
        if ($context->participantId === null || $context->fromDate === null || $context->untilDate === null) {
            throw new AuthorizationException('Der KI-Lauf enthält keinen autorisierten Teilnehmerzeitraum.');
        }

        return ProjektHasPersonen::query()
            ->where('projekt_id', $context->projectId)
            ->where('personen_id', $context->participantId)
            ->whereHas('teilnehmer', fn ($query) => $query->visibleForUser($user))
            ->firstOrFail();
    }

    private function assertNoArguments(array $arguments): void
    {
        if ($arguments !== []) {
            throw new AuthorizationException('Dieses KI-Tool akzeptiert keine Modellparameter.');
        }
    }
}
