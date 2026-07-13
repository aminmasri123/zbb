<?php
namespace Tests\Feature;
use App\Models\AppTask;use App\Models\ParticipantNotificationPreference;use App\Models\Personen;use App\Models\Projekt;use App\Models\ProjektHasPersonen;use App\Models\Standort;use App\Models\SystemModule;use App\Models\User;use App\Notifications\ParticipantReminderDigest;use App\Services\Modules\ModuleStateResolver;use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Support\Facades\Notification;use Inertia\Testing\AssertableInertia as Assert;use Tests\TestCase;
class ParticipantNotificationPreferenceTest extends TestCase
{
 use RefreshDatabase;
 public function test_preferences_filter_dashboard_and_email_digest_is_sent_only_once():void
 {
  Notification::fake();$staff=User::factory()->create();$location=Standort::factory()->create();$project=Projekt::factory()->create(['portal_feature_settings'=>['tasks_and_appointments'=>true]]);$participant=Personen::factory()->create(['typ'=>'teilnehmer']);$participation=$this->assign($project,$participant,$location);$portal=User::factory()->create(['person_id'=>$participant->id,'email_verified_at'=>now()]);app(ModuleStateResolver::class)->set(SystemModule::where('key','participant_portal')->firstOrFail(),true,null,$staff->id);AppTask::query()->create(['owner_user_id'=>$staff->id,'project_id'=>$project->id,'project_person_id'=>$participation->id,'title'=>'Unterlagen mitbringen','status'=>'open','priority'=>'normal','due_at'=>today()->addDay(),'visibility'=>'project','visible_to_participant'=>true]);
  $preferences=collect(['task','application','course','course_session','message'])->map(fn($category)=>['category'=>$category,'in_app_enabled'=>$category!=='task','email_enabled'=>$category==='task','days_before'=>14])->all();
  $this->actingAs($portal)->putJson(route('participant-portal.notification-preferences.update'),['preferences'=>$preferences])->assertOk()->assertJsonCount(5,'preferences');
  $this->actingAs($portal)->get(route('participant-portal.dashboard'))->assertInertia(fn(Assert $page)=>$page->has('reminders',0));
  $this->artisan('participant-portal:send-reminder-digests',['--user'=>$portal->id])->assertSuccessful();$this->artisan('participant-portal:send-reminder-digests',['--user'=>$portal->id])->assertSuccessful();
  Notification::assertSentToTimes($portal,ParticipantReminderDigest::class,1);$this->assertDatabaseHas('participant_notification_deliveries',['user_id'=>$portal->id,'status'=>'sent']);
 }
 public function test_settings_are_private_and_module_protected():void
 {
  $staff=User::factory()->create();$first=User::factory()->create(['person_id'=>Personen::factory()->create(['typ'=>'teilnehmer'])->id]);$second=User::factory()->create(['person_id'=>Personen::factory()->create(['typ'=>'teilnehmer'])->id]);app(ModuleStateResolver::class)->set(SystemModule::where('key','participant_portal')->firstOrFail(),true,null,$staff->id);$prefs=collect(['task','application','course','course_session','message'])->map(fn($c)=>['category'=>$c,'in_app_enabled'=>true,'email_enabled'=>false,'days_before'=>7])->all();$this->actingAs($first)->putJson(route('participant-portal.notification-preferences.update'),['preferences'=>$prefs])->assertOk();$this->actingAs($second)->get(route('participant-portal.notification-preferences.index'))->assertInertia(fn(Assert $page)=>$page->where('preferences.0.days_before',14));$this->assertSame(5,ParticipantNotificationPreference::query()->where('user_id',$first->id)->count());$this->assertSame(0,ParticipantNotificationPreference::query()->where('user_id',$second->id)->count());app(ModuleStateResolver::class)->set(SystemModule::where('key','participant_portal')->firstOrFail(),false,null,$staff->id);$this->actingAs($first)->get(route('participant-portal.notification-preferences.index'))->assertNotFound();
 }
 private function assign(Projekt $p,Personen $person,Standort $s):ProjektHasPersonen{return ProjektHasPersonen::query()->create(['projekt_id'=>$p->id,'personen_id'=>$person->id,'standort_id'=>$s->id,'status'=>'aktiv']);}
}
