<?php

namespace App\Notifications;

use App\Models\Personen;
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
        $besteller = Personen::where('id', $this->anforderung->ersteller_id)->first();
        return [
            'message' => 'Materialanforderung N\' #' . $this->anforderung->id . ' von ' . $besteller->vorname . ' ' . $besteller->nachname . ' wartet auf Ihre Genehmigung.',
            'link' => route('materialanforderung.show', $this->anforderung->id),
            'user_name' => auth()->user()->name,
            'id' => $this->anforderung->id,
            'typ' => 'Materialanforderung'
        ];
    }
}
