<?php
namespace App\Console\Commands;
use App\Models\ParticipantNotificationDelivery;use App\Models\User;use App\Notifications\ParticipantReminderDigest;use App\Services\Participants\ParticipantReminderService;use Illuminate\Console\Command;
class SendParticipantReminderDigests extends Command
{
 protected $signature='participant-portal:send-reminder-digests {--user=}';protected $description='Sendet persönliche Teilnehmerportal-Erinnerungen gemäß den Benachrichtigungseinstellungen.';
 public function handle(ParticipantReminderService $service):int
 {
  $sent=0;$failed=0;User::query()->whereNotNull('email_verified_at')->whereHas('person',fn($q)=>$q->where('typ','teilnehmer')->where('aktiv',true))->when($this->option('user'),fn($q,$id)=>$q->whereKey($id))->orderBy('id')->chunkById(100,function($users)use($service,&$sent,&$failed){foreach($users as $user){$items=$service->build($user,'email')['reminders']->values()->all();if(!$items)continue;$hash=hash('sha256',json_encode($items,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$delivery=ParticipantNotificationDelivery::query()->firstOrCreate(['user_id'=>$user->id,'digest_date'=>today()->toDateString(),'content_sha256'=>$hash],['status'=>'pending']);if($delivery->status==='sent')continue;try{$delivery->update(['status'=>'pending','error'=>null]);$user->notify(new ParticipantReminderDigest($items));$delivery->update(['status'=>'sent','sent_at'=>now()]);$sent++;}catch(\Throwable $e){$delivery->update(['status'=>'failed','error'=>mb_substr($e->getMessage(),0,5000)]);$failed++;report($e);}}});$this->info("Erinnerungs-E-Mails: {$sent} gesendet, {$failed} fehlgeschlagen.");return $failed?self::FAILURE:self::SUCCESS;
 }
}
