<?php

namespace App\Http\Controllers;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Notifications\ConfiguredEventNotification;
use App\Services\NotificationRecipientService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class AccountDeletionRequestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'request_details' => ['nullable', 'string', 'max:5000'],
        ]);

        $user = $request->user();
        $existingRequest = AccountDeletionRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->latest()
            ->first();

        if ($existingRequest) {
            return back()->with('info', 'Sie haben bereits einen offenen Loeschantrag fuer Ihr Konto eingereicht.');
        }

        $accountDeletionRequest = AccountDeletionRequest::create([
            'user_id' => $user->id,
            'person_id' => $user->person_id,
            'requester_name' => $user->name,
            'requester_email' => $user->email,
            'status' => 'submitted',
            'request_details' => $validated['request_details'] ?? null,
        ]);

        Notification::send(
            app(NotificationRecipientService::class)->forEvent(
                'user.account-deletion.requested',
                [
                    'actor' => $user,
                    'creator_user' => $user,
                    'account_deletion_request' => $accountDeletionRequest,
                ],
                fn () => $this->fallbackRecipients($user)
            ),
            new ConfiguredEventNotification([
                'event_key' => 'user.account-deletion.requested',
                'message' => 'Neuer Konto-Loeschantrag von ' . $user->name . '.',
                'link' => route('user.edit', $user->id),
                'id' => $accountDeletionRequest->id,
                'typ' => 'Konto-Loeschantrag',
            ])
        );

        return back()->with('success', 'Ihr Loeschantrag wurde eingereicht. Die Administration prueft den Antrag.');
    }

    private function fallbackRecipients(User $actor): Collection
    {
        try {
            return User::permission('benutzer.destroy')
                ->where('id', '!=', $actor->id)
                ->get();
        } catch (PermissionDoesNotExist) {
            return collect();
        }
    }
}
