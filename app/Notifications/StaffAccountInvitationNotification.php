<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffAccountInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly ?string $inviterName = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employeeName = $notifiable instanceof User ? $notifiable->name : null;
        $greeting = $employeeName ? "Guten Tag {$employeeName}," : 'Guten Tag,';

        $mail = (new MailMessage)
            ->subject('Einladung zu Ihrem Mitarbeiterkonto')
            ->greeting($greeting)
            ->line('Für Sie wurde ein Mitarbeiterkonto in Matrix angelegt.')
            ->line('Über den folgenden Link legen Sie Ihr persönliches Passwort fest und aktivieren Ihr Konto.')
            ->action('Mitarbeiterkonto aktivieren', route('staff-invitation.show', $this->token))
            ->line('Der Link ist sieben Tage gültig und kann nur einmal verwendet werden.')
            ->line('Wenn Sie diese Einladung nicht erwartet haben, ignorieren Sie diese E-Mail.');

        if ($this->inviterName) {
            $mail->salutation("Freundliche Grüße\n{$this->inviterName}");
        }

        return $mail;
    }
}
