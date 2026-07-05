<?php

namespace App\Notifications;

use App\Models\KlassenbuchWoche;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class KlassenbuchWocheZurPruefungNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly KlassenbuchWoche $woche)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $klassenbuch = $this->woche->klassenbuch;
        $gruppe = $klassenbuch?->gruppe;
        $bereich = $gruppe?->bereich?->name ?: 'Gruppe';

        return [
            'message' => 'Klassenbuch ' . $bereich . ' KW ' . $this->woche->kalenderwoche . ' wartet auf Prüfung.',
            'link' => route('klassenbuch.woche.show', [$klassenbuch->id, $this->woche->id]),
            'id' => $this->woche->id,
            'typ' => 'Klassenbuch',
        ];
    }
}
