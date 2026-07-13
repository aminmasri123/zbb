<?php

namespace App\Http\Controllers;

use App\Models\ParticipantContactChangeRequest;
use App\Models\User;
use App\Notifications\ParticipantEmailChangeVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ParticipantContactController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('ParticipantPortal/ContactSettings', [
            'email' => $request->user()->email,
            'requests' => ParticipantContactChangeRequest::query()->where('user_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function requestEmail(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->id)]]);
        $newEmail = Str::lower($data['email']);
        abort_if(Str::lower($request->user()->email) === $newEmail, 422, 'Die neue Adresse entspricht der aktuellen Adresse.');
        $token = Str::random(64);
        $change = DB::transaction(function () use ($request, $newEmail, $token) {
            ParticipantContactChangeRequest::query()->where('user_id', $request->user()->id)->where('field', 'email')->whereNull('confirmed_at')->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
            return ParticipantContactChangeRequest::query()->create(['user_id' => $request->user()->id, 'field' => 'email', 'old_value' => $request->user()->email, 'new_value' => $newEmail, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour(), 'requested_ip' => $request->ip(), 'requested_user_agent' => mb_substr((string) $request->userAgent(), 0, 500)]);
        });
        Notification::route('mail', $newEmail)->notify(new ParticipantEmailChangeVerification($token, $newEmail));
        return response()->json(['message' => 'Bestätigungslink wurde an die neue E-Mail-Adresse gesendet.', 'request' => $change], 201);
    }

    public function confirm(Request $request, string $token)
    {
        $change = ParticipantContactChangeRequest::query()->where('token_hash', hash('sha256', $token))->where('user_id', $request->user()->id)->whereNull('confirmed_at')->whereNull('cancelled_at')->where('expires_at', '>', now())->firstOrFail();
        DB::transaction(function () use ($request, $change) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_if(User::query()->where('email', $change->new_value)->whereKeyNot($user->id)->exists(), 422, 'Diese E-Mail-Adresse wird inzwischen bereits verwendet.');
            $user->update(['email' => $change->new_value, 'email_verified_at' => now()]);
            $change->update(['confirmed_at' => now(), 'confirmed_ip' => $request->ip()]);
            ParticipantContactChangeRequest::query()->where('user_id', $user->id)->where('field', 'email')->whereKeyNot($change->id)->whereNull('confirmed_at')->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
        });
        return redirect()->route('participant-portal.contact.index')->with('success', 'Die neue E-Mail-Adresse wurde bestätigt.');
    }

    public function cancel(Request $request, ParticipantContactChangeRequest $change)
    {
        abort_unless((int) $change->user_id === (int) $request->user()->id, 404);
        abort_if($change->confirmed_at || $change->cancelled_at, 422, 'Die Anfrage ist bereits abgeschlossen.');
        $change->update(['cancelled_at' => now()]);
        return response()->json(['message' => 'Änderungsanfrage wurde abgebrochen.']);
    }
}
