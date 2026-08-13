<?php

namespace App\Services\Auth;

use App\Models\StaffAccountInvitation;
use App\Models\User;
use App\Notifications\StaffAccountInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffAccountInvitationService
{
    public function send(User $user, ?User $invitedBy = null): StaffAccountInvitation
    {
        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($user, $invitedBy, $token) {
            StaffAccountInvitation::query()
                ->where('user_id', $user->id)
                ->whereNull('accepted_at')
                ->update(['expires_at' => now()]);

            return StaffAccountInvitation::query()->create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
                'invited_by_user_id' => $invitedBy?->id,
            ]);
        });

        $user->notify(new StaffAccountInvitationNotification($token, $invitedBy?->name));
        $invitation->forceFill(['sent_at' => now()])->save();

        return $invitation;
    }
}
