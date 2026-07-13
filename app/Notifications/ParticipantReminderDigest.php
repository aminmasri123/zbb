<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;use Illuminate\Notifications\Messages\MailMessage;use Illuminate\Notifications\Notification;
class ParticipantReminderDigest extends Notification
{
 use Queueable; public function __construct(private readonly array $reminders){}
 public function via(object $notifiable):array{return['mail'];}
 public function toMail(object $notifiable):MailMessage{$mail=(new MailMessage)->subject('Ihre Matrix-Erinnerungen')->greeting('Guten Tag,')->line('Für Sie stehen folgende Hinweise an:');foreach($this->reminders as $item)$mail->line('• '.$item['title'].' – '.$item['detail']);return $mail->action('Teilnehmerportal öffnen',route('participant-portal.dashboard'))->line('Sie können Kategorien und E-Mail-Zustellung jederzeit im Portal anpassen.');}
 public function reminders():array{return$this->reminders;}
}
