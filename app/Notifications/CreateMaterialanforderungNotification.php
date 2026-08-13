<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CreateMaterialanforderungNotification extends Notification
{
    use Queueable;

    public $anforderung;

    public function __construct($anforderung)
    {
        $this->anforderung = $anforderung;
    }

   
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $besteller = $this->anforderung->besteller;
        return [
            'message' => 'Materialanforderung #' . $this->anforderung->id . ' von ' . ($besteller?->name ?? 'Unbekannt') . ' wartet auf Ihre Genehmigung.',
            'link' => route('materialanforderung.show', $this->anforderung->id),
            'user_name' => auth()->user()->name,
            'id' => $this->anforderung->id,
            'typ' => 'Materialanforderung'
        ];
    }
}
