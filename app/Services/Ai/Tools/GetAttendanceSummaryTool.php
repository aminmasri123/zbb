<?php

namespace App\Services\Ai\Tools;

use App\Models\GruppeHasPersonen;
use App\Models\User;
use App\Services\Ai\AiProjectAuthorizer;
use App\Services\Ai\AiRunContext;
use App\Services\Ai\Contracts\AiTool;

final class GetAttendanceSummaryTool implements AiTool
{
    use AuthorizesParticipantTool;
    public const NAME='get_attendance_summary';
    public function __construct(private readonly AiProjectAuthorizer $authorizer) {}
    public function name(): string { return self::NAME; }
    public function execute(User $user,AiRunContext $context,array $arguments): array
    {
        $this->assertNoArguments($arguments); $this->participation($user,$context);
        $rows=GruppeHasPersonen::query()->where('personen_id',$context->participantId)->whereHas('gruppe',fn($q)=>$q->where('projekt_id',$context->projectId))->whereHas('tag',fn($q)=>$q->whereBetween('datum',[$context->fromDate,$context->untilDate]))->with(['tag:id,datum','status:id,status,abkuerzung'])->get();
        return ['source_id'=>'attendance-summary','period'=>['from'=>$context->fromDate,'until'=>$context->untilDate],'total_records'=>$rows->count(),'by_status'=>$rows->groupBy(fn($row)=>$row->status?->status ?? 'Unbekannt')->map->count()->all()];
    }
}
