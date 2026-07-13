<?php
namespace App\Http\Controllers;
use App\Models\AppCalendarEvent;
use App\Models\AttendanceCorrectionRequest;
use App\Models\GruppeHasPersonen;
use App\Models\ProjektHasPersonen;
use Illuminate\Http\Request;
use Inertia\Inertia;
class PortalSelfServiceController extends Controller
{
 public function index(Request $request){$person=$request->user()->person;$participations=ProjektHasPersonen::query()->where('personen_id',$person->id)->with('projekt')->get();$attendanceProjectIds=$participations->filter(fn($p)=>$p->projekt->portalFeatureEnabled('attendance_self_service'))->pluck('projekt_id');$calendarProjectIds=$participations->filter(fn($p)=>$p->projekt->portalFeatureEnabled('tasks_and_appointments'))->pluck('projekt_id');$attendance=GruppeHasPersonen::query()->where('personen_id',$person->id)->whereHas('gruppe',fn($q)=>$q->whereIn('projekt_id',$attendanceProjectIds))->with(['gruppe.projekt:id,name','gruppe.bereich:id,name','tag:id,datum','status:id,status,abkuerzung,farben','zeitgeplant:id,startzeit,endzeit','zeittatsaechlich:id,startzeit,endzeit'])->get()->sortByDesc('tag.datum')->values();$events=AppCalendarEvent::query()->where('visibility','project')->whereIn('project_id',$calendarProjectIds)->where('starts_at','>=',now()->subMonth())->where('starts_at','<=',now()->addYear())->orderBy('starts_at')->get();return Inertia::render('ParticipantPortal/SelfService',['attendance'=>$attendance,'events'=>$events,'corrections'=>AttendanceCorrectionRequest::query()->where('person_id',$person->id)->latest()->get()]);}
 public function requestCorrection(Request $request,GruppeHasPersonen $attendance){$person=$request->user()->person;abort_unless((int)$attendance->personen_id===(int)$person->id,404);$attendance->load('gruppe.projekt');abort_unless($attendance->gruppe?->projekt?->portalFeatureEnabled('attendance_self_service'),404);$data=$request->validate(['message'=>['required','string','min:5','max:2000']]);abort_if(AttendanceCorrectionRequest::query()->where('attendance_id',$attendance->id)->where('status','open')->exists(),422,'Für diesen Eintrag besteht bereits eine offene Anfrage.');$correction=AttendanceCorrectionRequest::query()->create(['attendance_id'=>$attendance->id,'person_id'=>$person->id,'message'=>$data['message'],'status'=>'open']);return response()->json(['message'=>'Korrekturanfrage wurde übermittelt.','correction'=>$correction],201);}
}
