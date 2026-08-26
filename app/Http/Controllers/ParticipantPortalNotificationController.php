<?php

namespace App\Http\Controllers;

use App\Services\Participants\ParticipantReminderService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ParticipantPortalNotificationController extends Controller
{
    public function __construct(private readonly ParticipantReminderService $reminders)
    {
    }

    public function index(Request $request)
    {
        $this->reminders->syncInAppNotifications($request->user());
        $notifications = $request->user()->notifications()
            ->latest()
            ->paginate(20)
            ->through(fn ($notification) => $this->payload($notification));

        return Inertia::render('ParticipantPortal/Notifications', [
            'notifications' => $notifications,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notification)
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return back()->with('success', 'Hinweis wurde als gelesen markiert.');
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Alle Hinweise wurden als gelesen markiert.');
    }

    private function payload($notification): array
    {
        $data = $notification->data ?: [];

        return [
            'id' => $notification->id,
            'typ' => $data['typ'] ?? 'Hinweis',
            'message' => $data['message'] ?? 'Benachrichtigung',
            'detail' => $data['detail'] ?? null,
            'link' => $data['link'] ?? null,
            'is_read' => (bool) $notification->read_at,
            'created_at' => $notification->created_at?->format('d.m.Y H:i'),
        ];
    }
}
