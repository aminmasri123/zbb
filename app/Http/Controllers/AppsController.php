<?php

namespace App\Http\Controllers;

use App\Models\AppCalendarEvent;
use App\Models\AppCalendar;
use App\Models\AppCalendarStyle;
use App\Models\AppContact;
use App\Models\AppFile;
use App\Models\AppPopup;
use App\Models\AppShare;
use App\Models\AppTask;
use App\Models\Personen;
use App\Models\Projekt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AppsController extends Controller
{
    private array $visibility = ['private', 'all', 'team', 'project'];

    public function index()
    {
        $user = Auth::user();

        return Inertia::render('Apps/Index', [
            ...$this->baseProps(),
            'stats' => [
                'files' => $this->visible(AppFile::query(), AppFile::class)->where('type', 'file')->count(),
                'events' => $this->visible(AppCalendarEvent::query(), AppCalendarEvent::class)->count(),
                'contacts' => $this->visible(AppContact::query(), AppContact::class)->count(),
                'tasks' => $this->visible(AppTask::query(), AppTask::class)->where('status', '!=', 'done')->count(),
                'popups' => $this->visible(AppPopup::query(), AppPopup::class)->where('active', true)->count(),
                'participants' => Personen::aktiv()->teilnehmer()->count(),
            ],
            'currentProjectId' => $user->current_team_id,
        ]);
    }

    public function files(Request $request)
    {
        $parentId = $request->integer('folder') ?: null;
        $items = $this->visible(AppFile::query(), AppFile::class)
            ->where('parent_id', $parentId)
            ->with(['owner:id,username,email', 'shares.person:id,vorname,nachname'])
            ->orderByRaw("type = 'folder' desc")
            ->orderBy('name')
            ->get();

        return $this->workspace('files', [
            'items' => $items,
            'currentFolder' => $parentId ? AppFile::find($parentId) : null,
        ]);
    }

    public function createFolder(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:app_files,id'],
            ...$this->visibilityRules(),
        ]);

        AppFile::create($this->ownedPayload($data) + [
            'type' => 'folder',
            'size' => 0,
        ]);

        return back()->with('success', 'Ordner wurde angelegt.');
    }

    public function uploadFile(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'parent_id' => ['nullable', 'exists:app_files,id'],
            ...$this->visibilityRules(),
        ]);

        $uploadedFile = $request->file('file');
        $path = $uploadedFile->store('apps/files');

        AppFile::create($this->ownedPayload($data) + [
            'type' => 'file',
            'name' => $request->input('name') ?: $uploadedFile->getClientOriginalName(),
            'original_name' => $uploadedFile->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $uploadedFile->getMimeType(),
            'size' => $uploadedFile->getSize(),
        ]);

        return back()->with('success', 'Datei wurde hochgeladen.');
    }

    public function downloadFile(AppFile $file)
    {
        abort_unless($file->type === 'file' && $this->canSee($file, AppFile::class), 404);
        abort_unless($file->path && Storage::exists($file->path), 404);

        return Storage::download($file->path, $file->original_name ?: $file->name);
    }

    public function deleteFile(AppFile $file)
    {
        abort_unless($this->canManage($file), 403);
        $this->deleteFileTree($file);

        return back()->with('success', 'Eintrag wurde geloescht.');
    }

    public function share(Request $request, string $type, int $id)
    {
        [$modelClass, $item] = $this->resolveShareable($type, $id);
        abort_unless($this->canManage($item), 403);

        $data = $request->validate([
            'person_id' => ['nullable', 'exists:personens,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'permission' => ['required', Rule::in(['view', 'edit'])],
            'message' => ['nullable', 'string', 'max:2000'],
            'send_email' => ['nullable', 'boolean'],
        ]);

        abort_if(empty($data['person_id']) && empty($data['email']), 422, 'Bitte Person oder E-Mail angeben.');

        $share = AppShare::updateOrCreate(
            [
                'shareable_type' => $modelClass,
                'shareable_id' => $item->id,
                'person_id' => $data['person_id'] ?? null,
                'email' => $data['email'] ?? null,
            ],
            [
                'shared_by_user_id' => Auth::id(),
                'permission' => $data['permission'],
                'message' => $data['message'] ?? null,
                'sent_at' => !empty($data['send_email']) ? now() : null,
            ]
        );

        if (!empty($data['send_email']) && !empty($data['email'])) {
            $this->sendShareMail($data['email'], $item, $data['message'] ?? null);
            $share->update(['sent_at' => now()]);
        }

        return back()->with('success', 'Freigabe wurde gespeichert.');
    }

    public function mailFile(Request $request, AppFile $file)
    {
        abort_unless($file->type === 'file' && $this->canManage($file), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_unless($file->path && Storage::exists($file->path), 404);

        Mail::raw($data['message'] ?: 'Im Anhang findest du die freigegebene Datei.', function ($message) use ($data, $file) {
            $message->to($data['email'])
                ->subject('Datei von ZBB Apps: ' . $file->name)
                ->attach(storage_path('app/' . $file->path), [
                    'as' => $file->original_name ?: $file->name,
                    'mime' => $file->mime_type,
                ]);
        });

        return back()->with('success', 'Datei wurde per Mail versendet.');
    }

    public function calendar()
    {
        $year = (int) request('year', now()->year);

        $this->ensureDefaultCalendars();

        return Inertia::render('Apps/CalendarYear', [
            ...$this->baseProps(),
            'year' => $year,
            'items' => $this->calendarEventsForYear($year),
            'calendars' => $this->visible(AppCalendar::query(), AppCalendar::class)
                ->orderBy('project_id')
                ->orderBy('name')
                ->get(),
            'styles' => AppCalendarStyle::where('owner_user_id', Auth::id())->orderBy('label')->get(),
        ]);
    }

    public function calendarEvents(Request $request)
    {
        $year = (int) $request->integer('year', now()->year);

        $this->ensureDefaultCalendars();

        return response()->json([
            'year' => $year,
            'items' => $this->calendarEventsForYear($year),
        ]);
    }

    public function storeCalendarCalendar(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'background_color' => ['required', 'string', 'max:20'],
            'text_color' => ['required', 'string', 'max:20'],
            ...$this->visibilityRules(),
        ]);

        AppCalendar::create($this->ownedPayload($data));

        return back()->with('success', 'Kalender wurde angelegt.');
    }

    public function storeCalendarStyle(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'background_color' => ['required', 'string', 'max:20'],
            'text_color' => ['required', 'string', 'max:20'],
        ]);

        AppCalendarStyle::updateOrCreate(
            ['owner_user_id' => Auth::id(), 'label' => $data['label']],
            $data + ['owner_user_id' => Auth::id()]
        );

        return back()->with('success', 'Farbe wurde gespeichert.');
    }

    public function storeCalendar(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'calendar_id' => ['nullable', 'exists:app_calendars,id'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['nullable', 'boolean'],
            'include_weekends' => ['nullable', 'boolean'],
            'excluded_dates' => ['nullable', 'array'],
            'excluded_dates.*' => ['date_format:Y-m-d'],
            'location' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'background_color' => ['nullable', 'string', 'max:20'],
            'text_color' => ['nullable', 'string', 'max:20'],
            ...$this->visibilityRules(),
        ]);

        AppCalendarEvent::create($this->calendarEventPayload($data));
        return back()->with('success', 'Termin wurde angelegt.');
    }

    public function updateCalendar(Request $request, AppCalendarEvent $event)
    {
        abort_unless($this->canManage($event), 403);
        $event->update($this->calendarEventPayload($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'calendar_id' => ['nullable', 'exists:app_calendars,id'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['nullable', 'boolean'],
            'include_weekends' => ['nullable', 'boolean'],
            'excluded_dates' => ['nullable', 'array'],
            'excluded_dates.*' => ['date_format:Y-m-d'],
            'location' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'background_color' => ['nullable', 'string', 'max:20'],
            'text_color' => ['nullable', 'string', 'max:20'],
            ...$this->visibilityRules(),
        ])));

        return back()->with('success', 'Termin wurde aktualisiert.');
    }

    public function destroyCalendar(AppCalendarEvent $event)
    {
        abort_unless($this->canManage($event), 403);
        $event->delete();
        return back()->with('success', 'Termin wurde geloescht.');
    }

    public function contacts()
    {
        return $this->workspace('contacts', [
            'items' => $this->visible(AppContact::query(), AppContact::class)
                ->with(['owner:id,username,email', 'shares.person:id,vorname,nachname'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeContact(Request $request)
    {
        AppContact::create($this->ownedPayload($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            ...$this->visibilityRules(),
        ])));

        return back()->with('success', 'Kontakt wurde gespeichert.');
    }

    public function updateContact(Request $request, AppContact $contact)
    {
        abort_unless($this->canManage($contact), 403);
        $contact->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            ...$this->visibilityRules(),
        ]));

        return back()->with('success', 'Kontakt wurde aktualisiert.');
    }

    public function destroyContact(AppContact $contact)
    {
        abort_unless($this->canManage($contact), 403);
        $contact->delete();
        return back()->with('success', 'Kontakt wurde geloescht.');
    }

    public function tasks()
    {
        return $this->workspace('tasks', [
            'items' => $this->visible(AppTask::query(), AppTask::class)
                ->with(['owner:id,username,email', 'assignee:id,vorname,nachname', 'shares.person:id,vorname,nachname'])
                ->orderByRaw("status = 'done' asc")
                ->orderByRaw("priority = 'high' desc")
                ->orderBy('due_at')
                ->get(),
        ]);
    }

    public function storeTask(Request $request)
    {
        AppTask::create($this->ownedPayload($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_person_id' => ['nullable', 'exists:personens,id'],
            'status' => ['required', Rule::in(['open', 'progress', 'done'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'due_at' => ['nullable', 'date'],
            ...$this->visibilityRules(),
        ])));

        return back()->with('success', 'Aufgabe wurde angelegt.');
    }

    public function updateTask(Request $request, AppTask $task)
    {
        abort_unless($this->canManage($task), 403);
        $task->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_person_id' => ['nullable', 'exists:personens,id'],
            'status' => ['required', Rule::in(['open', 'progress', 'done'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'due_at' => ['nullable', 'date'],
            ...$this->visibilityRules(),
        ]));

        return back()->with('success', 'Aufgabe wurde aktualisiert.');
    }

    public function destroyTask(AppTask $task)
    {
        abort_unless($this->canManage($task), 403);
        $task->delete();
        return back()->with('success', 'Aufgabe wurde geloescht.');
    }

    public function popups()
    {
        return $this->workspace('popups', [
            'items' => $this->visible(AppPopup::query(), AppPopup::class)
                ->with(['owner:id,username,email', 'shares.person:id,vorname,nachname'])
                ->orderByDesc('active')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function storePopup(Request $request)
    {
        AppPopup::create($this->ownedPayload($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'level' => ['required', Rule::in(['info', 'success', 'warning', 'danger'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'active' => ['nullable', 'boolean'],
            ...$this->visibilityRules(),
        ])));

        return back()->with('success', 'Popup wurde angelegt.');
    }

    public function updatePopup(Request $request, AppPopup $popup)
    {
        abort_unless($this->canManage($popup), 403);
        $popup->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'level' => ['required', Rule::in(['info', 'success', 'warning', 'danger'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'active' => ['nullable', 'boolean'],
            ...$this->visibilityRules(),
        ]));

        return back()->with('success', 'Popup wurde aktualisiert.');
    }

    public function destroyPopup(AppPopup $popup)
    {
        abort_unless($this->canManage($popup), 403);
        $popup->delete();
        return back()->with('success', 'Popup wurde geloescht.');
    }

    private function workspace(string $section, array $payload = [])
    {
        return Inertia::render('Apps/Workspace', [
            ...$this->baseProps(),
            'section' => $section,
            ...$payload,
        ]);
    }

    private function baseProps(): array
    {
        return [
            'projects' => Projekt::orderBy('name')->get(['id', 'name']),
            'people' => Personen::whereHas('user')
                ->orderBy('nachname')
                ->orderBy('vorname')
                ->get(['id', 'vorname', 'nachname']),
            'visibilityOptions' => [
                ['value' => 'private', 'label' => 'Privat'],
                ['value' => 'all', 'label' => 'Fuer alle sichtbar'],
                ['value' => 'team', 'label' => 'Team'],
                ['value' => 'project', 'label' => 'Projekt'],
            ],
        ];
    }

    private function visibilityRules(): array
    {
        return [
            'visibility' => ['required', Rule::in($this->visibility)],
            'project_id' => ['nullable', 'exists:projekts,id'],
            'team_id' => ['nullable', 'integer'],
        ];
    }

    private function ownedPayload(array $data): array
    {
        $user = Auth::user();
        $visibility = $data['visibility'] ?? 'private';

        $data['owner_user_id'] = $user->id;
        $data['team_id'] = $visibility === 'team' ? ($data['team_id'] ?? $user->current_team_id) : null;
        $data['project_id'] = $visibility === 'project' ? ($data['project_id'] ?? $user->current_team_id) : null;

        return $data;
    }

    private function calendarEventPayload(array $data): array
    {
        $data = $this->ownedPayload($data);
        $calendar = !empty($data['calendar_id']) ? AppCalendar::find($data['calendar_id']) : null;
        $style = AppCalendarStyle::where('owner_user_id', Auth::id())->where('label', $data['title'])->first();

        if ($calendar && empty($data['project_id'])) {
            $data['project_id'] = $calendar->project_id;
        }

        $data['background_color'] = $data['background_color'] ?? $style?->background_color ?? $calendar?->background_color ?? $data['color'] ?? '#ff7a00';
        $data['text_color'] = $data['text_color'] ?? $style?->text_color ?? $calendar?->text_color ?? '#ffffff';
        $data['include_weekends'] = (bool) ($data['include_weekends'] ?? false);
        $data['excluded_dates'] = array_values(array_unique($data['excluded_dates'] ?? []));

        return $data;
    }

    private function ensureDefaultCalendars(): void
    {
        $user = Auth::user();

        AppCalendar::firstOrCreate(
            ['owner_user_id' => $user->id, 'project_id' => null, 'name' => 'Mein Kalender'],
            [
                'background_color' => '#ff7a00',
                'text_color' => '#ffffff',
                'visibility' => 'private',
            ]
        );

        Projekt::whereIn('id', $user->projekte()->pluck('projekts.id'))
            ->get(['id', 'name'])
            ->each(function (Projekt $project) use ($user) {
                AppCalendar::firstOrCreate(
                    ['owner_user_id' => $user->id, 'project_id' => $project->id, 'name' => $project->name],
                    [
                        'background_color' => $this->projectColor($project->id),
                        'text_color' => '#ffffff',
                        'visibility' => 'project',
                    ]
                );
            });
    }

    private function calendarEventsForYear(int $year)
    {
        return $this->visible(AppCalendarEvent::query(), AppCalendarEvent::class)
            ->with(['owner:id,username,email', 'calendar:id,name,background_color,text_color,project_id', 'shares.person:id,vorname,nachname'])
            ->whereDate('starts_at', '<=', $year . '-12-31')
            ->where(function (Builder $q) use ($year) {
                $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $year . '-01-01');
            })
            ->orderBy('starts_at')
            ->get();
    }

    private function projectColor(int $id): string
    {
        $colors = ['#ef4444', '#2563eb', '#16a34a', '#9333ea', '#f97316', '#0891b2', '#be123c', '#4f46e5'];

        return $colors[$id % count($colors)];
    }

    private function visible(Builder $query, string $modelClass): Builder
    {
        $user = Auth::user();
        $personId = $user->person_id;
        $teamId = $user->current_team_id;

        return $query->where(function (Builder $q) use ($user, $personId, $teamId, $modelClass) {
            $q->where('owner_user_id', $user->id)
                ->orWhere('visibility', 'all');

            if ($teamId) {
                $q->orWhere(function (Builder $team) use ($teamId) {
                    $team->where('visibility', 'team')->where('team_id', $teamId);
                })->orWhere(function (Builder $project) use ($teamId) {
                    $project->where('visibility', 'project')->where('project_id', $teamId);
                });
            }

            $q->orWhereHas('shares', function (Builder $share) use ($personId, $user, $modelClass) {
                $share->where('shareable_type', $modelClass)
                    ->where(function (Builder $shareTarget) use ($personId, $user) {
                        if ($personId) {
                            $shareTarget->where('person_id', $personId);
                        }

                        $shareTarget->orWhere('email', $user->email);
                    });
            });
        });
    }

    private function canSee($item, string $modelClass): bool
    {
        return $this->visible($modelClass::query()->whereKey($item->id), $modelClass)->exists();
    }

    private function canManage($item): bool
    {
        return (int) $item->owner_user_id === (int) Auth::id();
    }

    private function deleteFileTree(AppFile $file): void
    {
        $file->children()->get()->each(fn (AppFile $child) => $this->deleteFileTree($child));

        if ($file->type === 'file' && $file->path) {
            Storage::delete($file->path);
        }

        $file->shares()->delete();
        $file->delete();
    }

    private function resolveShareable(string $type, int $id): array
    {
        $map = [
            'file' => AppFile::class,
            'event' => AppCalendarEvent::class,
            'contact' => AppContact::class,
            'task' => AppTask::class,
            'popup' => AppPopup::class,
        ];

        abort_unless(isset($map[$type]), 404);
        $modelClass = $map[$type];

        return [$modelClass, $modelClass::findOrFail($id)];
    }

    private function sendShareMail(string $email, $item, ?string $body): void
    {
        Mail::raw($body ?: 'Ein Eintrag wurde in ZBB Apps fuer dich freigegeben.', function ($message) use ($email, $item) {
            $message->to($email)->subject('Freigabe in ZBB Apps: ' . ($item->title ?? $item->name));
        });
    }
}
