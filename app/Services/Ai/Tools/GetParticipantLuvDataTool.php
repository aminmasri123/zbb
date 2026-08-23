<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\AiProjectAuthorizer;
use App\Services\Ai\AiRunContext;
use App\Services\Ai\Contracts\AiTool;

final class GetParticipantLuvDataTool implements AiTool
{
    use AuthorizesParticipantTool;
    public const NAME = 'get_participant_luv_data';
    public function __construct(private readonly AiProjectAuthorizer $authorizer) {}
    public function name(): string { return self::NAME; }
    public function execute(User $user, AiRunContext $context, array $arguments): array
    {
        $this->assertNoArguments($arguments); $participation=$this->participation($user,$context);
        $entries=$participation->luv()->whereDate('bis','>=',$context->fromDate)->whereDate('von','<=',$context->untilDate)->orderBy('von')->get()->map(fn($luv)=>[
            'source_id'=>'luv-'.$luv->id,'type'=>$luv->typ,'from'=>$luv->von?->toDateString(),'until'=>$luv->bis?->toDateString(),'initial_situation'=>$luv->ausgangssituation,'goal_agreement'=>$luv->zielvereinbarung,
        ])->all();
        return ['source_id'=>'luv-summary','period'=>['from'=>$context->fromDate,'until'=>$context->untilDate],'entries'=>$entries];
    }
}
