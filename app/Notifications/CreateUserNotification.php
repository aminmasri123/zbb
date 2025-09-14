<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CreateUserNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;

    }

    public function via($notifiable)
    {
        // Datenbank + Broadcast + Mail möglich
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'message' => 'User "' . $this->user->name . '" wurde erfolgreich erstellt.',
            'user_id' => $this->user->id,
        ];
    }
}
