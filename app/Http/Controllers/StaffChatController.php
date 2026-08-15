<?php

namespace App\Http\Controllers;

use App\Models\Materialanforderung;
use App\Models\Projekt;
use App\Models\StaffConversation;
use App\Models\StaffMessage;
use App\Models\StaffMessageAttachment;
use App\Models\User;
use App\Notifications\ConfiguredEventNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StaffChatController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureCanUseChat($request);
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));

        $conversations = StaffConversation::query()
            ->whereHas('members', fn ($members) => $members->where('users.id', $user->id))
            ->when($search !== '', fn ($query) => $query->where(function ($filter) use ($search) {
                $filter->where('name', 'like', "%{$search}%")
                    ->orWhereHas('members.person', fn ($people) => $people
                        ->where('vorname', 'like', "%{$search}%")
                        ->orWhere('nachname', 'like', "%{$search}%"))
                    ->orWhereHas('messages', fn ($messages) => $messages->where('body', 'like', "%{$search}%"));
            }))
            ->with([
                'members.person:id,vorname,nachname,typ,aktiv',
                'project:id,name',
                'messages' => fn ($messages) => $messages->latest()->limit(1),
            ])
            ->orderByDesc(DB::raw('COALESCE(last_message_at, created_at)'))
            ->get();

        $selected = null;
        $selectedId = $request->integer('conversation');
        if ($selectedId) {
            $selected = $conversations->firstWhere('id', $selectedId);
            abort_unless($selected, 403);
        } elseif ($conversations->isNotEmpty()) {
            $selected = $conversations->first();
        }

        $messages = collect();
        if ($selected) {
            $messages = $selected->messages()
                ->with([
                    'sender.person:id,vorname,nachname',
                    'attachments',
                    'materialanforderung:id,projekt_id,status',
                ])
                ->latest()
                ->limit(150)
                ->get()
                ->reverse()
                ->values();

            DB::table('staff_conversation_members')
                ->where('conversation_id', $selected->id)
                ->where('user_id', $user->id)
                ->update(['last_read_at' => now(), 'updated_at' => now()]);
        }

        $staff = User::query()
            ->whereKeyNot($user->id)
            ->whereHas('person', fn ($person) => $person->where('typ', 'mitarbeiter')->where('aktiv', true))
            ->with('person:id,vorname,nachname')
            ->orderBy('username')
            ->get(['id', 'person_id', 'username'])
            ->map(fn (User $member) => $this->userPayload($member));

        $projects = $user->projekte()
            ->orderBy('name')
            ->get(['projekts.id', 'projekts.name'])
            ->map(fn (Projekt $project) => ['id' => $project->id, 'name' => $project->name]);

        return Inertia::render('Chat/Index', [
            'conversations' => $conversations->map(fn (StaffConversation $conversation) => $this->conversationPayload($conversation, $user)),
            'selectedConversationId' => $selected?->id,
            'messages' => $messages->map(fn (StaffMessage $message) => $this->messagePayload($message)),
            'staff' => $staff,
            'projects' => $projects,
            'filters' => ['search' => $search],
            'prefillMaterialRequestId' => $request->integer('materialanforderung') ?: null,
            'privacy' => [
                'retention_days' => max(30, (int) config('internal_communication.chat_retention_days', 365)),
                'notice' => 'Nur dienstliche Inhalte teilen. Nachrichten und Anhänge werden nach Ablauf der festgelegten Aufbewahrungsfrist automatisch gelöscht.',
            ],
        ]);
    }

    public function storeConversation(Request $request)
    {
        $this->ensureCanUseChat($request);
        $validated = $request->validate([
            'type' => ['required', Rule::in(['direct', 'group', 'project'])],
            'name' => ['nullable', 'string', 'max:160'],
            'member_ids' => ['nullable', 'array', 'max:100'],
            'member_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:projekts,id'],
            'materialanforderung_id' => ['nullable', 'integer', 'exists:materialanforderungs,id'],
        ]);

        $user = $request->user();
        $type = $validated['type'];
        $memberIds = collect($validated['member_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();

        if (! empty($validated['materialanforderung_id'])) {
            $materialRequest = Materialanforderung::findOrFail($validated['materialanforderung_id']);
            abort_unless($this->mayViewMaterialRequest($user, $materialRequest), 403);
        }

        if ($type === 'direct') {
            abort_unless($memberIds->count() === 1, 422, 'Für eine Direktnachricht muss genau eine Person ausgewählt werden.');
        }
        if ($type === 'group') {
            abort_unless($memberIds->isNotEmpty() && trim((string) ($validated['name'] ?? '')) !== '', 422, 'Für eine Gruppe sind ein Name und mindestens eine weitere Person erforderlich.');
        }

        $staffIds = User::query()
            ->whereIn('id', $memberIds)
            ->whereHas('person', fn ($person) => $person->where('typ', 'mitarbeiter')->where('aktiv', true))
            ->pluck('id');
        abort_unless($staffIds->count() === $memberIds->count(), 422, 'Es dürfen nur aktive Mitarbeitende hinzugefügt werden.');

        if ($type === 'project') {
            $projectId = (int) ($validated['project_id'] ?? 0);
            abort_unless($projectId && $user->projekte()->whereKey($projectId)->exists(), 403);
            $staffIds = User::query()
                ->whereHas('person', fn ($person) => $person->where('typ', 'mitarbeiter')->where('aktiv', true))
                ->whereHas('projekte', fn ($projects) => $projects->where('projekts.id', $projectId))
                ->pluck('users.id');
            $memberIds = $staffIds;
        }

        $allMemberIds = $memberIds->push($user->id)->unique()->sort()->values();

        if ($type === 'direct') {
            $existing = StaffConversation::query()
                ->where('type', 'direct')
                ->whereHas('members', fn ($members) => $members->where('users.id', $user->id))
                ->whereHas('members', fn ($members) => $members->where('users.id', $memberIds->first()))
                ->withCount('members')
                ->get()
                ->firstWhere('members_count', 2);

            if ($existing) {
                return redirect()->route('chat.index', array_filter([
                    'conversation' => $existing->id,
                    'materialanforderung' => $validated['materialanforderung_id'] ?? null,
                ]));
            }
        }

        $conversation = DB::transaction(function () use ($validated, $type, $user, $allMemberIds) {
            $retentionDays = min(3650, max(30, (int) config('internal_communication.chat_retention_days', 365)));
            $conversation = StaffConversation::create([
                'type' => $type,
                'name' => $type === 'direct' ? null : (trim((string) ($validated['name'] ?? '')) ?: null),
                'project_id' => $type === 'project' ? (int) $validated['project_id'] : null,
                'created_by_user_id' => $user->id,
                'retention_days' => $retentionDays,
            ]);

            $now = now();
            $conversation->members()->attach($allMemberIds->mapWithKeys(fn ($id) => [$id => [
                'joined_at' => $now,
                'last_read_at' => (int) $id === (int) $user->id ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]])->all());

            return $conversation;
        });

        return redirect()->route('chat.index', array_filter([
            'conversation' => $conversation->id,
            'materialanforderung' => $validated['materialanforderung_id'] ?? null,
        ]))
            ->with('success', 'Unterhaltung wurde erstellt.');
    }

    public function storeMessage(Request $request, StaffConversation $conversation)
    {
        $this->ensureCanUseChat($request);
        $this->ensureMember($request, $conversation);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:10000', 'required_without:attachments'],
            'materialanforderung_id' => ['nullable', 'integer', 'exists:materialanforderungs,id'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'mimes:'.implode(',', config('internal_communication.allowed_attachment_mimes')),
                'max:'.(int) config('internal_communication.max_attachment_kilobytes', 10240),
            ],
        ]);

        $materialRequest = null;
        if (! empty($validated['materialanforderung_id'])) {
            $materialRequest = Materialanforderung::findOrFail($validated['materialanforderung_id']);
            abort_unless($this->mayViewMaterialRequest($request->user(), $materialRequest), 403);

            $unauthorizedMemberExists = $conversation->members()
                ->with('person')
                ->get()
                ->contains(fn (User $member) => ! $this->mayViewMaterialRequest($member, $materialRequest));
            if ($unauthorizedMemberExists) {
                throw ValidationException::withMessages([
                    'materialanforderung_id' => 'Die Materialanforderung kann nicht verknüpft werden, weil nicht alle Mitglieder darauf zugreifen dürfen.',
                ]);
            }
        }

        $message = DB::transaction(function () use ($request, $conversation, $validated, $materialRequest) {
            $message = $conversation->messages()->create([
                'sender_user_id' => $request->user()->id,
                'body' => trim((string) ($validated['body'] ?? '')) ?: null,
                'materialanforderung_id' => $materialRequest?->id,
                'expires_at' => now()->addDays($conversation->retention_days),
            ]);

            foreach ($validated['attachments'] ?? [] as $file) {
                $storedName = Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
                $path = $file->storeAs("internal-chat/{$conversation->id}/{$message->id}", $storedName, 'local');
                $message->attachments()->create([
                    'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            $conversation->update(['last_message_at' => $message->created_at]);
            DB::table('staff_conversation_members')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $request->user()->id)
                ->update(['last_read_at' => now(), 'updated_at' => now()]);

            return $message;
        });

        $recipients = $conversation->members()->whereKeyNot($request->user()->id)->get();
        Notification::send($recipients, new ConfiguredEventNotification([
            'event_key' => 'chat.message.created',
            'message' => 'Neue interne Nachricht von '.$request->user()->name.'.',
            'link' => route('chat.index', ['conversation' => $conversation->id]),
            'id' => $message->id,
            'typ' => 'Interner Chat',
        ]));

        return redirect()->route('chat.index', ['conversation' => $conversation->id]);
    }

    public function destroyMessage(Request $request, StaffMessage $message)
    {
        $this->ensureCanUseChat($request);
        $this->ensureMember($request, $message->conversation);
        abort_unless((int) $message->sender_user_id === (int) $request->user()->id, 403);

        foreach ($message->attachments as $attachment) {
            Storage::disk('local')->delete($attachment->path);
        }
        Storage::disk('local')->deleteDirectory("internal-chat/{$message->conversation_id}/{$message->id}");
        $conversationId = $message->conversation_id;
        $message->delete();

        return redirect()->route('chat.index', ['conversation' => $conversationId])
            ->with('success', 'Die eigene Nachricht wurde gelöscht.');
    }

    public function downloadAttachment(Request $request, StaffMessageAttachment $attachment)
    {
        $this->ensureCanUseChat($request);
        $attachment->load('message.conversation');
        $this->ensureMember($request, $attachment->message->conversation);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function export(Request $request)
    {
        $this->ensureCanUseChat($request);
        $user = $request->user();
        $conversations = StaffConversation::query()
            ->whereHas('members', fn ($members) => $members->where('users.id', $user->id))
            ->with([
                'members.person:id,vorname,nachname',
                'messages.sender.person:id,vorname,nachname',
                'messages.attachments:id,message_id,original_name,mime_type,size',
            ])
            ->get();

        $payload = [
            'exported_at' => now()->toISOString(),
            'user' => $this->userPayload($user->loadMissing('person:id,vorname,nachname')),
            'conversations' => $conversations->map(fn (StaffConversation $conversation) => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'name' => $conversation->name,
                'members' => $conversation->members->map(fn (User $member) => $this->userPayload($member)),
                'messages' => $conversation->messages->map(fn (StaffMessage $message) => [
                    'id' => $message->id,
                    'sender' => $message->sender ? $this->userPayload($message->sender) : null,
                    'body' => $message->body,
                    'materialanforderung_id' => $message->materialanforderung_id,
                    'attachments' => $message->attachments->map->only(['original_name', 'mime_type', 'size']),
                    'created_at' => $message->created_at?->toISOString(),
                    'expires_at' => $message->expires_at?->toISOString(),
                ]),
            ]),
        ];

        return response()->streamDownload(
            fn () => print (json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'meine-internen-nachrichten-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    private function conversationPayload(StaffConversation $conversation, User $viewer): array
    {
        $member = $conversation->members->firstWhere('id', $viewer->id);
        $lastReadAt = $member?->pivot?->last_read_at;
        $unread = $conversation->messages()
            ->where('sender_user_id', '!=', $viewer->id)
            ->when($lastReadAt, fn ($messages) => $messages->where('created_at', '>', $lastReadAt))
            ->count();
        $otherMembers = $conversation->members->where('id', '!=', $viewer->id)->values();
        $title = match ($conversation->type) {
            'direct' => $otherMembers->first()?->name ?? 'Ehemalige Person',
            'project' => $conversation->name ?: ($conversation->project?->name ? 'Projekt: '.$conversation->project->name : 'Projektchat'),
            default => $conversation->name ?: 'Gruppe',
        };

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $title,
            'project' => $conversation->project ? ['id' => $conversation->project->id, 'name' => $conversation->project->name] : null,
            'members' => $conversation->members->map(fn (User $user) => $this->userPayload($user)),
            'unread_count' => $unread,
            'last_message' => $conversation->messages->first()?->body,
            'last_message_at' => $conversation->last_message_at?->toISOString(),
            'retention_days' => $conversation->retention_days,
        ];
    }

    private function messagePayload(StaffMessage $message): array
    {
        return [
            'id' => $message->id,
            'sender' => $message->sender ? $this->userPayload($message->sender) : null,
            'body' => $message->body,
            'materialanforderung' => $message->materialanforderung ? [
                'id' => $message->materialanforderung->id,
                'status' => $message->materialanforderung->status,
                'link' => route('materialanforderung.show', $message->materialanforderung->id),
            ] : null,
            'attachments' => $message->attachments->map(fn (StaffMessageAttachment $attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'download_url' => route('chat.attachments.download', $attachment),
            ]),
            'created_at' => $message->created_at?->toISOString(),
            'expires_at' => $message->expires_at?->toISOString(),
        ];
    }

    private function userPayload(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name ?: 'Ehemalige Person'];
    }

    private function ensureCanUseChat(Request $request): void
    {
        abort_unless($request->user()?->hasStoredPermission('chat.use'), 403);
    }

    private function ensureMember(Request $request, StaffConversation $conversation): void
    {
        abort_unless($conversation->members()->where('users.id', $request->user()->id)->exists(), 403);
    }

    private function mayViewMaterialRequest(User $user, Materialanforderung $anforderung): bool
    {
        if ((int) $anforderung->ersteller_id === (int) $user->id
            || $anforderung->genehmigungen()->where('genehmiger_id', $user->id)->exists()) {
            return true;
        }

        if ($user->can('materialanforderung.sachlische_freigabe.index')
            && $anforderung->status === 'eingereicht'
            && $user->projekte()->whereKey($anforderung->projekt_id)->exists()) {
            return true;
        }

        return ($user->can('materialanforderung.kaufmännische_freigabe.index')
                || $user->can('materialanforderung.kaufmännische_freigabe.update')
                || $user->can('materialanforderung.bestellwesen.update'))
            && ! in_array($anforderung->status, ['entwurf', 'eingereicht'], true);
    }
}
