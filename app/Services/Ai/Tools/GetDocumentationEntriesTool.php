<?php

namespace App\Services\Ai\Tools;

use App\Models\PersonenHasNotizen;
use App\Models\User;
use App\Services\Ai\AiProjectAuthorizer;
use App\Services\Ai\AiRunContext;
use App\Services\Ai\Contracts\AiTool;

final class GetDocumentationEntriesTool implements AiTool
{
    use AuthorizesParticipantTool;
    public const NAME='get_documentation_entries';
    public function __construct(private readonly AiProjectAuthorizer $authorizer) {}
    public function name(): string { return self::NAME; }
    public function execute(User $user,AiRunContext $context,array $arguments): array
    {
        $this->assertNoArguments($arguments); $this->participation($user,$context);
        $entries=PersonenHasNotizen::query()->where('person_id',$context->participantId)->whereBetween('created_at',[$context->fromDate.' 00:00:00',$context->untilDate.' 23:59:59'])->orderBy('created_at')->limit(100)->get()->map(fn($note)=>['source_id'=>'documentation-'.$note->id,'date'=>$note->created_at?->toDateString(),'title'=>$note->titel,'content'=>$note->notizinhalt])->all();
        return ['source_id'=>'documentation-summary','period'=>['from'=>$context->fromDate,'until'=>$context->untilDate],'entries'=>$entries];
    }
}
