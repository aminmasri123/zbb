<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\AiProjectAuthorizer;
use App\Services\Ai\AiRunContext;
use App\Services\Ai\Contracts\AiTool;

final class GetParticipantIdentitySummaryTool implements AiTool
{
    use AuthorizesParticipantTool;
    public const NAME = 'get_participant_identity_summary';
    public function __construct(private readonly AiProjectAuthorizer $authorizer) {}
    public function name(): string { return self::NAME; }
    public function execute(User $user, AiRunContext $context, array $arguments): array
    {
        $this->assertNoArguments($arguments); $participation = $this->participation($user, $context); $person = $participation->teilnehmer;
        return ['source_id'=>'participant-identity','participant_id'=>(int)$person->id,'name'=>trim($person->vorname.' '.$person->nachname),'project_status'=>$participation->status];
    }
}
