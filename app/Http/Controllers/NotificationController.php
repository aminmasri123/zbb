<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->input('status', 'unread');
        $type = $request->input('type');

        if (! in_array($status, ['all', 'unread', 'read'], true)) {
            $status = 'unread';
        }

        $query = $user->notifications()
            ->when($status === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($status === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when($type, fn ($query) => $query->where('data->typ', $type))
            ->latest();

        $notifications = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($notification) => $this->notificationPayload($notification));

        $types = $user->notifications()
            ->get(['data'])
            ->map(fn ($notification) => $notification->data['typ'] ?? null)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $totalCount = $user->notifications()->count();
        $unreadCount = $user->unreadNotifications()->count();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filters' => [
                'status' => $status,
                'type' => $type,
            ],
            'types' => $types,
            'stats' => [
                'total' => $totalCount,
                'unread' => $unreadCount,
                'read' => max(0, $totalCount - $unreadCount),
            ],
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()?->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return $this->notificationResponse($request, 'Alle Benachrichtigungen wurden als gelesen markiert.');
    }

    public function markAsRead(Request $request, string $notification)
    {
        $item = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $item->markAsRead();

        return $this->notificationResponse($request, 'Benachrichtigung wurde als gelesen markiert.');
    }

    public function markAsUnread(Request $request, string $notification)
    {
        $item = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $item->update(['read_at' => null]);

        return $this->notificationResponse($request, 'Benachrichtigung wurde als ungelesen markiert.');
    }

    public function destroy(Request $request, string $notification)
    {
        $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail()
            ->delete();

        return $this->notificationResponse($request, 'Benachrichtigung wurde entfernt.');
    }

    private function notificationPayload($notification): array
    {
        $data = $notification->data ?: [];

        return [
            'id' => $notification->id,
            'class' => class_basename($notification->type),
            'typ' => $data['typ'] ?? class_basename($notification->type),
            'status' => $data['status'] ?? null,
            'entity_id' => $data['id'] ?? null,
            'message' => $data['message'] ?? 'Benachrichtigung',
            'link' => $data['link'] ?? null,
            'read_at' => $notification->read_at?->toDateTimeString(),
            'created_at' => $notification->created_at?->toDateTimeString(),
            'created_at_formatted' => $notification->created_at?->format('d.m.Y H:i'),
            'is_read' => (bool) $notification->read_at,
        ];
    }

    private function notificationResponse(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }
}
