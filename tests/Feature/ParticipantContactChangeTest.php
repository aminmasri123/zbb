<?php
namespace Tests\Feature;
use App\Models\ParticipantContactChangeRequest;use App\Models\Personen;use App\Models\SystemModule;use App\Models\User;use App\Notifications\ParticipantEmailChangeVerification;use App\Services\Modules\ModuleStateResolver;use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Support\Facades\Notification;use Tests\TestCase;
class ParticipantContactChangeTest extends TestCase
{
 use RefreshDatabase;
 public function test_new_email_is_sent_to_new_address_and_applied_only_after_own_confirmation():void
 {
  Notification::fake();$participant=Personen::factory()->create(['typ'=>'teilnehmer']);$portal=User::factory()->create(['person_id'=>$participant->id,'email'=>'alt@example.test']);app(ModuleStateResolver::class)->set(SystemModule::where('key','participant_portal')->firstOrFail(),true,null,$portal->id);$this->actingAs($portal)->postJson(route('participant-portal.contact.email.request'),['email'=>'Neu@Example.test'])->assertCreated();$this->assertSame('alt@example.test',$portal->fresh()->email);$change=ParticipantContactChangeRequest::firstOrFail();$this->assertSame('neu@example.test',$change->new_value);$this->assertNotSame('',$change->token_hash);
  $token=null;Notification::assertSentOnDemand(ParticipantEmailChangeVerification::class,function($notification,$channels,$notifiable)use(&$token){$token=$notification->token;return in_array('mail',$channels,true)&&$notifiable->routeNotificationFor('mail')==='neu@example.test';});$this->assertNotNull($token);
  $otherPerson=Personen::factory()->create(['typ'=>'teilnehmer']);$other=User::factory()->create(['person_id'=>$otherPerson->id]);$this->actingAs($other)->get(route('participant-portal.contact.email.confirm',$token))->assertNotFound();$this->assertSame('alt@example.test',$portal->fresh()->email);
  $this->actingAs($portal)->get(route('participant-portal.contact.email.confirm',$token))->assertRedirect(route('participant-portal.contact.index'));$this->assertSame('neu@example.test',$portal->fresh()->email);$this->assertNotNull($portal->fresh()->email_verified_at);$this->assertNotNull($change->fresh()->confirmed_at);$this->actingAs($portal)->get(route('participant-portal.contact.email.confirm',$token))->assertNotFound();
 }
 public function test_new_request_cancels_previous_and_expired_token_cannot_change_email():void
 {
  Notification::fake();$participant=Personen::factory()->create(['typ'=>'teilnehmer']);$portal=User::factory()->create(['person_id'=>$participant->id,'email'=>'alt@example.test']);app(ModuleStateResolver::class)->set(SystemModule::where('key','participant_portal')->firstOrFail(),true,null,$portal->id);$this->actingAs($portal)->postJson(route('participant-portal.contact.email.request'),['email'=>'eins@example.test'])->assertCreated();$first=ParticipantContactChangeRequest::firstOrFail();Notification::fake();$this->actingAs($portal)->postJson(route('participant-portal.contact.email.request'),['email'=>'zwei@example.test'])->assertCreated();$this->assertNotNull($first->fresh()->cancelled_at);$second=ParticipantContactChangeRequest::latest('id')->firstOrFail();$second->update(['expires_at'=>now()->subMinute()]);$token=null;Notification::assertSentOnDemand(ParticipantEmailChangeVerification::class,function($notification)use(&$token){$token=$notification->token;return true;});$this->assertNotNull($token);$this->actingAs($portal)->get(route('participant-portal.contact.email.confirm',$token))->assertNotFound();$this->assertSame('alt@example.test',$portal->fresh()->email);
 }
}
