<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UpdateMaterialanforderungNotification extends Notification
{
    use Queueable;

    public $anforderung;
    public $status;

    public function __construct($anforderung, $status)
    {
        $this->anforderung = $anforderung;
        $this->status = $status;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $ersteller = $this->anforderung->besteller;
        $link = $this->status === 'zurueckgezogen'
            ? route('materialanforderung.index')
            : route('materialanforderung.show', $this->anforderung->id);

        switch ($this->status) {
            case 'eingereicht':
                $message = "Materialanforderung #{$this->anforderung->id} von " . ($ersteller?->name ?? 'Unbekannt') . " wartet auf Ihre sachliche Genehmigung.";
                break;

            case 'sachlich_genehmigt':
                $message = "Materialanforderung #{$this->anforderung->id} wurde sachlich genehmigt und wartet auf kaufmännische Genehmigung.";
                break;

            case 'kaufmaennisch_genehmigt':
                $message = "Materialanforderung #{$this->anforderung->id} wurde vollständig genehmigt und ist bereit zur Bestellung.";
                break;

            case 'zur_ueberarbeitung':
                $message = "Materialanforderung #{$this->anforderung->id} wurde zur Überarbeitung zurückgesendet.";
                break;

            case 'zurueckgezogen':
                $message = "Materialanforderung #{$this->anforderung->id} wurde vom Antragsteller zurückgezogen.";
                break;

            case 'storniert':
                $message = "Materialanforderung #{$this->anforderung->id} wurde storniert.";
                break;

            case 'bestellt':
                $message = "Materialanforderung #{$this->anforderung->id} wurde bestellt.";
                break;

            case 'geliefert':
                $message = "Materialanforderung #{$this->anforderung->id} wurde vollständig geliefert.";
                break;

            case 'teilweise_geliefert':
                $message = "Materialanforderung #{$this->anforderung->id} wurde teilweise geliefert.";
                break;

            default:
                $message = "Status der Materialanforderung #{$this->anforderung->id} wurde aktualisiert.";
        }

        return [
            'message' => $message,
            'link' => $link,
            'user_name' => auth()->user()->name,
            'id' => $this->anforderung->id,
            'typ' => 'Materialanforderung',
            'status' => $this->status
        ];
    }
}
