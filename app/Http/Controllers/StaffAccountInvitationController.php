<?php

namespace App\Http\Controllers;

use App\Models\StaffAccountInvitation;
use App\Models\User;
use App\Services\Auth\StaffAccountInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class StaffAccountInvitationController extends Controller
{
    public function __construct(private readonly StaffAccountInvitationService $invitations)
    {
    }

    public function show(string $token)
    {
        $invitation = $this->validInvitation($token)->load('user.person:id,vorname,nachname');

        return Inertia::render('Auth/AcceptStaffInvitation', [
            'token' => $token,
            'email' => $invitation->user->email,
            'employeeName' => $invitation->user->name,
            'expiresAt' => $invitation->expires_at,
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invitation = $this->validInvitation($token);
        $validated = $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(10)->letters()->mixedCase()->numbers(),
            ],
        ]);

        DB::transaction(function () use ($invitation, $validated) {
            $lockedInvitation = StaffAccountInvitation::query()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $lockedInvitation->accepted_at || $lockedInvitation->expires_at->isPast(),
                404,
                'Diese Einladung ist nicht mehr gültig.'
            );

            $lockedInvitation->user()->update([
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
            ]);
            $lockedInvitation->update(['accepted_at' => now()]);
        });

        return redirect()->route('login')
            ->with('status', 'Ihr Mitarbeiterkonto wurde aktiviert. Sie können sich jetzt anmelden.');
    }

    public function resend(Request $request, User $user)
    {
        abort_unless($user->person?->typ === 'mitarbeiter', 404);

        $latestInvitation = $user->staffAccountInvitations()->latest()->first();
        abort_unless($latestInvitation, 422, 'Für dieses Konto wurde keine E-Mail-Einladung gewählt.');
        abort_if($latestInvitation->accepted_at, 422, 'Dieses Mitarbeiterkonto wurde bereits aktiviert.');

        try {
            $invitation = $this->invitations->send($user, $request->user());
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Die Einladung konnte nicht per E-Mail versendet werden. Bitte prüfen Sie die Mail-Einstellungen.',
            ], 502);
        }

        return response()->json([
            'message' => "Die Einladung wurde erneut an {$user->email} gesendet.",
            'invitation_status' => 'pending',
            'invitation_expires_at' => $invitation->expires_at,
        ]);
    }

    private function validInvitation(string $token): StaffAccountInvitation
    {
        return StaffAccountInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();
    }
}
