<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

class RecordParticipantPortalLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $isParticipant = $event->user->relationLoaded('person')
            ? $event->user->person?->typ === 'teilnehmer'
            : $event->user->person()->where('typ', 'teilnehmer')->exists();

        if (! $isParticipant) {
            return;
        }

        // Only the latest successful portal login is retained. No IP address,
        // device information or login history is collected.
        $event->user->forceFill(['portal_last_login_at' => now()])->saveQuietly();
    }
}
