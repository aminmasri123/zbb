<?php

namespace App\Http\Controllers;

use App\Models\ProgramFeedback;
use App\Models\ProgramFeedbackAttachment;
use App\Models\User;
use App\Notifications\ConfiguredEventNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProgramFeedbackController extends Controller
{
    public function index(Request $request)
    {
        $canManage = $request->user()->can('program-feedback.manage');

        $feedback = ProgramFeedback::query()
            ->when(! $canManage, fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest()
            ->get()
            ->map(fn (ProgramFeedback $item) => $this->loadFeedback($item, $canManage));

        return Inertia::render('ProgramFeedback/Index', [
            'feedbackItems' => $feedback,
            'canManage' => $canManage,
            'assignees' => $canManage
                ? User::permission('program-feedback.manage')
                    ->with('person:id,vorname,nachname')
                    ->orderBy('username')
                    ->get(['id', 'username', 'email', 'person_id'])
                : [],
            'options' => $this->options(),
            'contextPage' => Str::limit((string) $request->query('from', ''), 2048, ''),
            'requestedFeedbackId' => $request->integer('open') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(ProgramFeedback::TYPES)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:10000'],
            'expected_result' => ['nullable', 'string', 'max:5000'],
            'area' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', Rule::in(ProgramFeedback::PRIORITIES)],
            'page_url' => [
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if (str_starts_with((string) $value, '/')) {
                        return;
                    }

                    $scheme = parse_url((string) $value, PHP_URL_SCHEME);
                    $host = parse_url((string) $value, PHP_URL_HOST);

                    if (! in_array($scheme, ['http', 'https'], true) || $host !== $request->getHost()) {
                        $fail('Die betroffene Seite muss zu diesem Programm gehören.');
                    }
                },
            ],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx', 'max:10240'],
        ]);

        $feedback = DB::transaction(function () use ($request, $validated) {
            $files = $validated['attachments'] ?? [];
            unset($validated['attachments']);

            $feedback = ProgramFeedback::create([
                ...$validated,
                'user_id' => $request->user()->id,
                'status' => 'new',
                'browser' => Str::limit((string) $request->userAgent(), 500, ''),
                'app_version' => Str::limit((string) config('app.version', ''), 80, ''),
            ]);

            $feedback->forceFill([
                'reference' => sprintf('FB-%s-%04d', now()->format('Y'), $feedback->id),
            ])->save();

            $feedback->history()->create([
                'user_id' => $request->user()->id,
                'from_status' => null,
                'to_status' => 'new',
                'note' => 'Meldung eingereicht',
            ]);

            foreach ($files as $file) {
                $storedName = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());
                $path = $file->storeAs('program-feedback/' . $feedback->id, $storedName, 'local');

                $feedback->attachments()->create([
                    'user_id' => $request->user()->id,
                    'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            return $feedback;
        });

        $managers = User::permission('program-feedback.manage')
            ->where('id', '!=', $request->user()->id)
            ->get();

        Notification::send($managers, new ConfiguredEventNotification([
            'event_key' => 'program-feedback.created',
            'message' => "Neue Programm-Meldung {$feedback->reference}: {$feedback->title}",
            'link' => route('program-feedback.index', ['open' => $feedback->id]),
            'id' => $feedback->id,
            'typ' => 'Programm-Feedback',
        ]));

        return response()->json([
            'message' => "{$feedback->reference} wurde erfolgreich eingereicht.",
            'feedback' => $this->loadFeedback($feedback, false),
        ], 201);
    }

    public function update(Request $request, ProgramFeedback $feedback)
    {
        $this->ensureManager($request);

        $validated = $request->validate([
            'status' => ['required', Rule::in(ProgramFeedback::STATUSES)],
            'priority' => ['required', Rule::in(ProgramFeedback::PRIORITIES)],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'release_version' => ['nullable', 'string', 'max:80'],
            'status_note' => ['nullable', 'string', 'max:500'],
        ]);

        $oldStatus = $feedback->status;
        $statusChanged = $oldStatus !== $validated['status'];
        $note = $validated['status_note'] ?? null;
        unset($validated['status_note']);

        if (in_array($validated['status'], ['released', 'rejected', 'duplicate'], true)) {
            $validated['closed_at'] = $feedback->closed_at ?? now();
        } else {
            $validated['closed_at'] = null;
        }

        DB::transaction(function () use ($request, $feedback, $validated, $statusChanged, $oldStatus, $note) {
            $feedback->update($validated);

            if ($statusChanged) {
                $feedback->history()->create([
                    'user_id' => $request->user()->id,
                    'from_status' => $oldStatus,
                    'to_status' => $validated['status'],
                    'note' => $note,
                ]);
            }
        });

        if ($statusChanged && $feedback->user && $feedback->user_id !== $request->user()->id) {
            $feedback->user->notify(new ConfiguredEventNotification([
                'event_key' => 'program-feedback.status-changed',
                'message' => "Status von {$feedback->reference} wurde aktualisiert.",
                'link' => route('program-feedback.index', ['open' => $feedback->id]),
                'id' => $feedback->id,
                'typ' => 'Programm-Feedback',
            ]));
        }

        return response()->json([
            'message' => 'Meldung wurde aktualisiert.',
            'feedback' => $this->loadFeedback($feedback, true),
        ]);
    }

    public function storeComment(Request $request, ProgramFeedback $feedback)
    {
        $canManage = $request->user()->can('program-feedback.manage');
        $this->ensureVisible($request, $feedback, $canManage);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $internal = $canManage && (bool) ($validated['is_internal'] ?? false);
        $feedback->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'is_internal' => $internal,
        ]);

        if (! $internal && $feedback->user && $feedback->user_id !== $request->user()->id) {
            $feedback->user->notify(new ConfiguredEventNotification([
                'event_key' => 'program-feedback.comment.created',
                'message' => "Neue Rückmeldung zu {$feedback->reference}.",
                'link' => route('program-feedback.index', ['open' => $feedback->id]),
                'id' => $feedback->id,
                'typ' => 'Programm-Feedback',
            ]));
        } elseif ($feedback->user_id === $request->user()->id) {
            $recipients = User::permission('program-feedback.manage')
                ->where('id', '!=', $request->user()->id)
                ->when($feedback->assigned_to_user_id, fn ($query) => $query->where('id', $feedback->assigned_to_user_id))
                ->get();

            Notification::send($recipients, new ConfiguredEventNotification([
                'event_key' => 'program-feedback.comment.created',
                'message' => "Neue Rückmeldung zu {$feedback->reference}.",
                'link' => route('program-feedback.index', ['open' => $feedback->id]),
                'id' => $feedback->id,
                'typ' => 'Programm-Feedback',
            ]));
        }

        return response()->json([
            'message' => $internal ? 'Interne Notiz wurde gespeichert.' : 'Rückmeldung wurde gespeichert.',
            'feedback' => $this->loadFeedback($feedback, $canManage),
        ]);
    }

    public function downloadAttachment(Request $request, ProgramFeedbackAttachment $attachment)
    {
        $attachment->load('feedback');
        $this->ensureVisible($request, $attachment->feedback, $request->user()->can('program-feedback.manage'));

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Request $request, ProgramFeedback $feedback)
    {
        $this->ensureManager($request);

        foreach ($feedback->attachments as $attachment) {
            Storage::disk('local')->delete($attachment->path);
        }

        Storage::disk('local')->deleteDirectory('program-feedback/' . $feedback->id);
        $feedback->delete();

        return response()->json(['message' => 'Meldung wurde gelöscht.']);
    }

    private function loadFeedback(ProgramFeedback $feedback, bool $canManage): ProgramFeedback
    {
        return $feedback->fresh()->load([
            'user.person:id,vorname,nachname',
            'assignedTo.person:id,vorname,nachname',
            'attachments',
            'comments' => fn ($query) => $query
                ->when(! $canManage, fn ($comments) => $comments->where('is_internal', false))
                ->with('user.person:id,vorname,nachname'),
            'history.user.person:id,vorname,nachname',
        ]);
    }

    private function ensureVisible(Request $request, ProgramFeedback $feedback, bool $canManage): void
    {
        abort_unless($canManage || $feedback->user_id === $request->user()->id, 403);
    }

    private function ensureManager(Request $request): void
    {
        abort_unless($request->user()->can('program-feedback.manage'), 403);
    }

    private function options(): array
    {
        return [
            'types' => [
                ['value' => 'suggestion', 'label' => 'Verbesserungsvorschlag'],
                ['value' => 'bug', 'label' => 'Fehler melden'],
            ],
            'priorities' => [
                ['value' => 'low', 'label' => 'Niedrig'],
                ['value' => 'normal', 'label' => 'Normal'],
                ['value' => 'high', 'label' => 'Hoch'],
                ['value' => 'critical', 'label' => 'Kritisch'],
            ],
            'statuses' => [
                ['value' => 'new', 'label' => 'Neu'],
                ['value' => 'review', 'label' => 'Wird geprüft'],
                ['value' => 'planned', 'label' => 'Geplant'],
                ['value' => 'in_progress', 'label' => 'In Bearbeitung'],
                ['value' => 'testing', 'label' => 'Im Test'],
                ['value' => 'released', 'label' => 'Veröffentlicht'],
                ['value' => 'rejected', 'label' => 'Abgelehnt'],
                ['value' => 'duplicate', 'label' => 'Duplikat'],
            ],
            'areas' => [
                'Dashboard',
                'Benutzer und Berechtigungen',
                'Projekte',
                'Teilnehmer',
                'Klassenbuch und Anwesenheit',
                'Apps und Kalender',
                'Dokumente',
                'Ressourcen und IT-Service',
                'Finanzen und Bestellungen',
                'Profil und Einstellungen',
                'Sonstiges',
            ],
        ];
    }
}
