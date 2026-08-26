<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ParticipantPortalReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly array $reminder,
        private readonly string $reminderKey,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'typ' => $this->reminder['type'] ?? 'Hinweis',
            'message' => $this->reminder['title'] ?? 'Neuer Hinweis',
            'detail' => $this->reminder['detail'] ?? null,
            'link' => $this->reminder['href'] ?? null,
            'event_at' => $this->reminder['at'] ?? null,
            'reminder_key' => $this->reminderKey,
        ];
    }
}
