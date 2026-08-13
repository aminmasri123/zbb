<?php

namespace App\Notifications;

use App\Models\Materialanforderung;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UpdateMaterialanforderungNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Materialanforderung $anforderung,
        public string $status,
        public User $actor,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $ersteller = $this->anforderung->besteller;
        $actorName = trim($this->actor->name) ?: 'Unbekannte Person';
        $link = $this->status === 'zurueckgezogen'
            ? route('materialanforderung.index')
            : route('materialanforderung.show', $this->anforderung->id);

        switch ($this->status) {
            case 'eingereicht':
                $message = "{$actorName} hat die Materialanforderung #{$this->anforderung->id} eingereicht. Sie wartet auf Ihre sachliche Genehmigung.";
                break;

            case 'sachlich_genehmigt':
                $message = "{$actorName} hat die Materialanforderung #{$this->anforderung->id} sachlich genehmigt. Sie wartet auf die kaufmännische Genehmigung.";
                break;

            case 'kaufmaennisch_genehmigt':
                $message = "{$actorName} hat die Materialanforderung #{$this->anforderung->id} kaufmännisch genehmigt. Sie ist zur Bestellung freigegeben.";
                break;

            case 'zur_ueberarbeitung':
                $message = "{$actorName} hat die Materialanforderung #{$this->anforderung->id} zur Überarbeitung an " . ($ersteller?->name ?? 'den Antragsteller') . ' zurückgegeben.';
                break;

            case 'zurueckgezogen':
                $message = "{$actorName} hat die Materialanforderung #{$this->anforderung->id} zurückgezogen.";
                break;

            case 'storniert':
                $message = "{$actorName} hat die Materialanforderung #{$this->anforderung->id} storniert.";
                break;

            case 'bestellt':
                $message = "{$actorName} hat die Materialanforderung #{$this->anforderung->id} als bestellt markiert.";
                break;

            case 'geliefert':
                $message = "{$actorName} hat die Materialanforderung #{$this->anforderung->id} als vollständig geliefert markiert.";
                break;

            case 'teilweise_geliefert':
                $message = "{$actorName} hat für die Materialanforderung #{$this->anforderung->id} eine Teillieferung erfasst.";
                break;

            default:
                $message = "{$actorName} hat den Status der Materialanforderung #{$this->anforderung->id} aktualisiert.";
        }

        return [
            'message' => $message,
            'link' => $link,
            'user_name' => $actorName,
            'user_id' => $this->actor->id,
            'id' => $this->anforderung->id,
            'typ' => 'Materialanforderung',
            'status' => $this->status
        ];
    }
}
