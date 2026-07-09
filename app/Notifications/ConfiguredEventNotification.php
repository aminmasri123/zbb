<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ConfiguredEventNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly array $payload)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->payload['message'] ?? 'Benachrichtigung',
            'link' => $this->payload['link'] ?? null,
            'id' => $this->payload['id'] ?? null,
            'typ' => $this->payload['typ'] ?? 'Benachrichtigung',
            'event_key' => $this->payload['event_key'] ?? null,
        ];
    }
}
