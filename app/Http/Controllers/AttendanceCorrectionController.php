<?php
namespace App\Http\Controllers;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Personen;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class AttendanceCorrectionController extends Controller
{
 public function __construct(private readonly ActiveProjectContext $activeProjectContext){}
 public function resolve(Request $request,AttendanceCorrectionRequest $correction){$project=$this->activeProjectContext->currentAvailableFor($request->user());abort_unless($project,409);$correction->load('attendance.gruppe');abort_unless((int)$correction->attendance?->gruppe?->projekt_id===(int)$project->id,404);abort_unless(Personen::query()->teilnehmer()->visibleForUser($request->user())->whereKey($correction->person_id)->exists(),403);$data=$request->validate(['status'=>['required',Rule::in(['accepted','rejected'])],'resolution_note'=>['nullable','string','max:2000']]);$correction->update([...$data,'resolved_by_user_id'=>$request->user()->id,'resolved_at'=>now()]);return response()->json(['message'=>'Korrekturanfrage wurde bearbeitet.','correction'=>$correction->fresh()->load('resolver:id,username')]);}
}
