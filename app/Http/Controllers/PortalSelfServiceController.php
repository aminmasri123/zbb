<?php
namespace App\Http\Controllers;
use App\Models\AppCalendarEvent;
use App\Models\AppTask;
use App\Models\AttendanceCorrectionRequest;
use App\Models\GruppeHasPersonen;
use App\Models\ProjektHasPersonen;
use Illuminate\Http\Request;
use Inertia\Inertia;
class PortalSelfServiceController extends Controller
{
 public function index(Request $request){$person=$request->user()->person;$participations=ProjektHasPersonen::query()->where('personen_id',$person->id)->where('status','aktiv')->with('projekt')->get()->filter(fn($p)=>$p->projekt?->featureEnabled('participant_portal'));$attendanceProjectIds=$participations->filter(fn($p)=>$p->projekt->portalFeatureEnabled('attendance_self_service'))->pluck('projekt_id');$calendarProjectIds=$participations->filter(fn($p)=>$p->projekt->portalFeatureEnabled('tasks_and_appointments'))->pluck('projekt_id');$attendance=GruppeHasPersonen::query()->where('personen_id',$person->id)->whereHas('gruppe',fn($q)=>$q->whereIn('projekt_id',$attendanceProjectIds))->with(['gruppe.projekt:id,name','gruppe.bereich:id,name','tag:id,datum','status:id,status,abkuerzung,farben','zeitgeplant:id,startzeit,endzeit','zeittatsaechlich:id,startzeit,endzeit'])->get()->sortByDesc('tag.datum')->values();$events=AppCalendarEvent::query()->where('visibility','project')->whereIn('project_id',$calendarProjectIds)->where('starts_at','>=',now()->subMonth())->where('starts_at','<=',now()->addYear())->orderBy('starts_at')->get();return Inertia::render('ParticipantPortal/SelfService',['attendance'=>$attendance,'events'=>$events,'corrections'=>AttendanceCorrectionRequest::query()->where('person_id',$person->id)->latest()->get(),'attendanceEnabled'=>$attendanceProjectIds->isNotEmpty(),'appointmentsEnabled'=>$calendarProjectIds->isNotEmpty()]);}
 public function tasks(Request $request)
 {
  $participations=ProjektHasPersonen::query()->where('personen_id',$request->user()->person_id)->where('status','aktiv')->with('projekt')->get()->filter(fn($participation)=>$participation->projekt?->featureEnabled('participant_portal')&&$participation->projekt->portalFeatureEnabled('tasks_and_appointments'));
  $tasks=AppTask::query()->whereIn('project_person_id',$participations->pluck('id'))->whereIn('project_id',$participations->pluck('projekt_id'))->where('visible_to_participant',true)->with('participation.projekt:id,name')->orderByRaw("case status when 'open' then 1 when 'progress' then 2 else 3 end")->orderByRaw('due_at is null')->orderBy('due_at')->latest('id')->get();

  return Inertia::render('ParticipantPortal/Tasks',['tasks'=>$tasks]);
 }
 public function requestCorrection(Request $request,GruppeHasPersonen $attendance){$person=$request->user()->person;abort_unless((int)$attendance->personen_id===(int)$person->id,404);$attendance->load('gruppe.projekt');$activeParticipation=ProjektHasPersonen::query()->where('personen_id',$person->id)->where('projekt_id',$attendance->gruppe?->projekt_id)->where('status','aktiv')->exists();abort_unless($activeParticipation&&$attendance->gruppe?->projekt?->featureEnabled('participant_portal')&&$attendance->gruppe?->projekt?->portalFeatureEnabled('attendance_self_service'),404);$data=$request->validate(['message'=>['required','string','min:5','max:2000']]);abort_if(AttendanceCorrectionRequest::query()->where('attendance_id',$attendance->id)->where('status','open')->exists(),422,'Für diesen Eintrag besteht bereits eine offene Anfrage.');$correction=AttendanceCorrectionRequest::query()->create(['attendance_id'=>$attendance->id,'person_id'=>$person->id,'message'=>$data['message'],'status'=>'open']);return response()->json(['message'=>'Korrekturanfrage wurde übermittelt.','correction'=>$correction],201);}
}
