<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ParticipantPortalInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $invitationUrl,
        public readonly string $participantName,
        public readonly string $projectName,
        public readonly ?string $inviterName = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Einladung zum Teilnehmerportal')
            ->greeting($this->participantName !== '' ? "Guten Tag {$this->participantName}," : 'Guten Tag,')
            ->line("Sie wurden zum Teilnehmerportal für das Projekt „{$this->projectName}“ eingeladen.")
            ->line('Über den folgenden Link legen Sie Ihr persönliches Passwort fest und aktivieren Ihren Portalzugang.')
            ->action('Teilnehmerportal aktivieren', $this->invitationUrl)
            ->line('Der Link ist sieben Tage gültig und kann nur einmal verwendet werden.')
            ->line('Wenn Sie diese Einladung nicht erwartet haben, ignorieren Sie diese E-Mail.');

        if ($this->inviterName) {
            $mail->salutation("Freundliche Grüße\n{$this->inviterName}");
        }

        return $mail;
    }
}
