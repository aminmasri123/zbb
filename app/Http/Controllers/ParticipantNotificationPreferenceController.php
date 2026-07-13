<?php
namespace App\Http\Controllers;
use App\Models\ParticipantNotificationPreference;use App\Services\Participants\ParticipantReminderService;use Illuminate\Http\Request;use Inertia\Inertia;
class ParticipantNotificationPreferenceController extends Controller
{
 public function __construct(private readonly ParticipantReminderService $reminders){}
 public function index(Request $request){return Inertia::render('ParticipantPortal/NotificationPreferences',['preferences'=>$this->reminders->preferences($request->user())->values()]);}
 public function update(Request $request){$data=$request->validate(['preferences'=>['required','array','size:5'],'preferences.*.category'=>['required','distinct','in:task,application,course,course_session,message'],'preferences.*.in_app_enabled'=>['required','boolean'],'preferences.*.email_enabled'=>['required','boolean'],'preferences.*.days_before'=>['required','integer','min:0','max:30']]);foreach($data['preferences'] as $preference)ParticipantNotificationPreference::query()->updateOrCreate(['user_id'=>$request->user()->id,'category'=>$preference['category']],$preference);return response()->json(['message'=>'Benachrichtigungseinstellungen wurden gespeichert.','preferences'=>$this->reminders->preferences($request->user())->values()]);}
}
