<?php

namespace App\Http\Controllers;

use App\Models\AppCalendarEvent;
use App\Models\AppCalendarEventAttendee;
use App\Models\AppCalendar;
use App\Models\AppCalendarStyle;
use App\Models\AppContact;
use App\Models\AppFile;
use App\Models\AppPopup;
use App\Models\AppShare;
use App\Models\AppTask;
use App\Models\AppTaskWorkflowTemplate;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\User;
use App\Notifications\ConfiguredEventNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
        $search = trim((string) $request->input('search', ''));
        $type = $request->input('type', 'all');
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';
        $currentFolder = $parentId
            ? $this->visible(AppFile::query(), AppFile::class)
                ->with(['owner:id,username,email', 'shares.person:id,vorname,nachname,typ', 'shares.team:id,name'])
                ->whereKey($parentId)
                ->firstOrFail()
            : null;

        $query = $this->visible(AppFile::query(), AppFile::class)
            ->where('parent_id', $parentId)
            ->with(['owner:id,username,email', 'shares.person:id,vorname,nachname,typ', 'shares.team:id,name']);

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if (in_array($type, ['file', 'folder'], true)) {
            $query->where('type', $type);
        }

        match ($sort) {
            'updated' => $query->orderByDesc('type')->orderBy('updated_at', $direction),
            'size' => $query->orderByDesc('type')->orderBy('size', $direction),
            default => $query->orderByDesc('type')->orderBy('name', $direction),
        };

        $items = $query->paginate(60)->withQueryString();

        return $this->workspace('files', [
            'items' => $items->getCollection()->map(fn (AppFile $file) => $this->filePayload($file))->values(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'prev_page_url' => $items->previousPageUrl(),
                'next_page_url' => $items->nextPageUrl(),
            ],
            'currentFolder' => $currentFolder ? $this->filePayload($currentFolder) : null,
            'breadcrumbs' => $this->fileBreadcrumbs($currentFolder),
            'fileFilters' => [
                'search' => $search,
                'type' => $type,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'fileStats' => $this->fileStats($parentId),
        ]);
    }

    public function createFolder(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:app_files,id'],
            ...$this->visibilityRules(),
        ]);

        $this->ensureUsableParent($data['parent_id'] ?? null);

        $folder = AppFile::create($this->fileOwnershipPayload($data, $data['parent_id'] ?? null) + [
            'type' => 'folder',
            'name' => $data['name'],
            'size' => 0,
        ]);

        $this->inheritFileShares($folder, $data['parent_id'] ?? null);

        return back()->with('success', 'Ordner wurde angelegt.');
    }

    public function uploadFile(Request $request)
    {
        $data = $request->validate([
            'file' => ['nullable', 'file', 'max:51200'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:51200'],
            'relative_path' => ['nullable', 'string', 'max:1024'],
            'relative_paths' => ['nullable', 'array'],
            'relative_paths.*' => ['nullable', 'string', 'max:1024'],
            'parent_id' => ['nullable', 'exists:app_files,id'],
            ...$this->visibilityRules(),
        ]);

        $this->ensureUsableParent($data['parent_id'] ?? null);

        $uploads = $this->collectAppFileUploads($request);

        if (empty($uploads)) {
            throw ValidationException::withMessages([
                'files' => 'Bitte Datei oder Ordner auswaehlen.',
            ]);
        }

        foreach ($uploads as $upload) {
            [$folders, $fileName] = $this->normalizeUploadPath($upload['relative_path'] ?? null, $upload['file']);
            $targetParentId = $this->ensureUploadFolderPath($folders, $data['parent_id'] ?? null, $data);
            $uploadedFile = $upload['file'];
            $path = $uploadedFile->store('apps/files');

            $file = AppFile::create($this->fileOwnershipPayload($data, $targetParentId) + [
                'type' => 'file',
                'name' => $request->input('name') ?: $fileName,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $uploadedFile->getMimeType(),
                'size' => $uploadedFile->getSize(),
            ]);

            $this->inheritFileShares($file, $targetParentId);
        }

        return back()->with('success', count($uploads) === 1 ? 'Datei wurde hochgeladen.' : count($uploads) . ' Dateien wurden hochgeladen.');
    }

    public function downloadFile(AppFile $file)
    {
        abort_unless($file->type === 'file' && $this->canSee($file, AppFile::class), 404);
        abort_unless($file->path && Storage::exists($file->path), 404);

        return Storage::download($file->path, $file->original_name ?: $file->name);
    }

    public function updateFile(Request $request, AppFile $file)
    {
        abort_unless($this->canWriteFile($file), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'exists:app_files,id'],
            ...$this->visibilityRules(),
        ]);

        abort_if((int) ($data['parent_id'] ?? 0) === (int) $file->id, 422, 'Ein Eintrag kann nicht in sich selbst verschoben werden.');
        abort_if($file->type === 'folder' && $this->isDescendantFolder((int) ($data['parent_id'] ?? 0), $file), 422, 'Ein Ordner kann nicht in einen eigenen Unterordner verschoben werden.');
        $this->ensureUsableParent($data['parent_id'] ?? null);

        $file->update($data);

        return back()->with('success', 'Datei wurde aktualisiert.');
    }

    public function transferFileOwner(Request $request, AppFile $file)
    {
        abort_unless($this->canOwn($file), 403);

        $data = $request->validate([
            'person_id' => ['required', 'exists:personens,id'],
        ]);

        $person = Personen::with('user:id,person_id,username,email')
            ->whereHas('user')
            ->findOrFail($data['person_id']);

        abort_unless($person->user, 422, 'Die ausgewaehlte Person hat keinen Benutzerzugang.');

        $this->transferFileTreeOwner($file, $person->user);

        return back()->with('success', $file->type === 'folder' ? 'Ordnerbesitzer wurde uebergeben.' : 'Dateibesitzer wurde uebergeben.');
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
            'person_ids' => ['nullable', 'array'],
            'person_ids.*' => ['integer', 'exists:personens,id'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'exists:projekts,id'],
            'emails' => ['nullable', 'string', 'max:2000'],
            'permission' => ['required', Rule::in(['view', 'edit', 'manage'])],
            'message' => ['nullable', 'string', 'max:2000'],
            'send_notification' => ['nullable', 'boolean'],
        ]);

        $personIds = collect($data['person_ids'] ?? [])->filter()->unique()->values();
        $teamIds = collect($data['team_ids'] ?? [])->filter()->unique()->values();
        $emails = $this->parseShareEmails($data['emails'] ?? '');

        if ($teamIds->isNotEmpty()) {
            $allowedTeamIds = Auth::user()->projekte()->pluck('projekts.id')->map(fn ($id) => (int) $id);
            abort_if($teamIds->diff($allowedTeamIds)->isNotEmpty(), 403, 'Dieses Team kann nicht freigegeben werden.');
        }

        if ($personIds->isEmpty() && $teamIds->isEmpty() && $emails->isEmpty()) {
            throw ValidationException::withMessages([
                'targets' => 'Bitte mindestens eine Person, ein Team oder eine E-Mail-Adresse auswaehlen.',
            ]);
        }

        $sharePayload = [
            'shared_by_user_id' => Auth::id(),
            'permission' => $data['permission'],
            'message' => $data['message'] ?? null,
            'sent_at' => ! empty($data['send_notification']) ? now() : null,
        ];

        foreach ($personIds as $personId) {
            AppShare::updateOrCreate(
                [
                    'shareable_type' => $modelClass,
                    'shareable_id' => $item->id,
                    'person_id' => $personId,
                    'email' => null,
                    'team_id' => null,
                ],
                $sharePayload
            );
        }

        foreach ($teamIds as $teamId) {
            AppShare::updateOrCreate(
                [
                    'shareable_type' => $modelClass,
                    'shareable_id' => $item->id,
                    'person_id' => null,
                    'email' => null,
                    'team_id' => $teamId,
                ],
                $sharePayload
            );
        }

        foreach ($emails as $email) {
            AppShare::updateOrCreate(
                [
                    'shareable_type' => $modelClass,
                    'shareable_id' => $item->id,
                    'person_id' => null,
                    'email' => $email,
                    'team_id' => null,
                ],
                $sharePayload
            );
        }

        $mailFailures = ! empty($data['send_notification'])
            ? $this->notifyAppShareRecipients($item, $data, $personIds, $teamIds, $emails)
            : 0;

        if ($item instanceof AppFile && $item->type === 'folder') {
            $this->syncFolderSharesToChildren($item);
        }

        $response = back()->with('success', 'Freigabe wurde gespeichert.');

        if ($mailFailures > 0) {
            $response->with('warning', 'Freigabe wurde gespeichert, aber mindestens eine Benachrichtigung konnte nicht versendet werden. Bitte Mail-Konfiguration pruefen.');
        }

        return $response;
    }

    public function mailFile(Request $request, AppFile $file)
    {
        abort_unless($file->type === 'file' && $this->canManage($file), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_unless($file->path && Storage::exists($file->path), 404);

        try {
            Mail::raw($data['message'] ?: 'Im Anhang findest du die freigegebene Datei.', function ($message) use ($data, $file) {
                $message->to($data['email'])
                    ->subject('Datei von ZBB Apps: ' . $file->name)
                    ->attach(storage_path('app/' . $file->path), [
                        'as' => $file->original_name ?: $file->name,
                        'mime' => $file->mime_type,
                    ]);
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Die Datei konnte nicht per Mail versendet werden. Bitte Mail-Konfiguration pruefen.');
        }

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
                ->get()
                ->map(fn (AppCalendar $calendar) => [
                    ...$calendar->toArray(),
                    'can_manage' => $this->canManage($calendar),
                ]),
            'calendarPeople' => Auth::user()->can('apps.calendar.project.assign') ? $this->calendarPeople() : [],
            'calendarCapabilities' => [
                'manage_project' => Auth::user()->can('apps.calendar.project.manage'),
                'assign_project' => Auth::user()->can('apps.calendar.project.assign'),
                'view_all_project' => Auth::user()->can('apps.calendar.project.view.all'),
                'respond' => Auth::user()->can('apps.calendar.respond'),
            ],
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

    public function exportCalendar(Request $request)
    {
        $year = (int) $request->integer('year', now()->year);
        $calendarId = $request->input('calendar');
        $calendarId = $calendarId && $calendarId !== 'all' ? (int) $calendarId : null;
        $includePersonalWithoutCalendar = false;

        if ($calendarId) {
            $calendar = AppCalendar::findOrFail($calendarId);
            abort_unless($this->canSee($calendar, AppCalendar::class), 403);
            $includePersonalWithoutCalendar = $calendar->owner_user_id === Auth::id() && $calendar->name === 'Mein Kalender' && ! $calendar->project_id;
        }

        $events = $this->calendarEventsForYear($year)
            ->when($calendarId, fn ($items) => $items->filter(fn ($event) => (int) $event->calendar_id === $calendarId || ($includePersonalWithoutCalendar && ! $event->calendar_id)))
            ->values();

        $spreadsheet = $this->calendarSpreadsheet($events, $year);
        $filename = 'Kalender_' . $year . ($calendarId ? '_Kalender_' . $calendarId : '_Alle') . '_' . now()->format('Ymd_His') . '.xlsx';
        $path = storage_path('app/tmp/' . $filename);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function previewCalendarImport(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'calendar_id' => ['nullable', 'exists:app_calendars,id'],
        ]);

        $this->ensureDefaultCalendars();
        $calendarId = $data['calendar_id'] ?? $this->personalCalendarId();
        if ($calendarId) {
            $calendar = AppCalendar::findOrFail($calendarId);
            abort_unless($this->canManage($calendar), 403);
        }

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $events = $this->extractCalendarImportEvents($spreadsheet, $calendarId);

        return response()->json([
            'success' => true,
            'events' => $events,
            'summary' => [
                'total' => count($events),
                'selected' => collect($events)->where('selected', true)->count(),
                'weekend' => collect($events)->where('is_weekend', true)->count(),
                'holiday' => collect($events)->where('is_holiday', true)->count(),
                'duplicates' => collect($events)->where('duplicate', true)->count(),
            ],
        ]);
    }

    public function confirmCalendarImport(Request $request)
    {
        $data = $request->validate([
            'calendar_id' => ['nullable', 'exists:app_calendars,id'],
            'events' => ['required', 'array', 'min:1', 'max:1000'],
            'events.*.title' => ['required', 'string', 'max:255'],
            'events.*.date' => ['required', 'date_format:Y-m-d'],
            'events.*.is_weekend' => ['nullable', 'boolean'],
            'events.*.is_holiday' => ['nullable', 'boolean'],
            'events.*.background_color' => ['nullable', 'string', 'max:20'],
            'events.*.text_color' => ['nullable', 'string', 'max:20'],
        ]);

        $this->ensureDefaultCalendars();
        $calendarId = $data['calendar_id'] ?? $this->personalCalendarId();
        if ($calendarId) {
            $calendar = AppCalendar::findOrFail($calendarId);
            abort_unless($this->canManage($calendar), 403);
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $calendarId, &$created, &$skipped) {
            foreach ($data['events'] as $eventData) {
                if ($this->calendarImportDuplicateExists($eventData['title'], $eventData['date'], $calendarId, $eventData['background_color'] ?? null, $eventData['text_color'] ?? null)) {
                    $skipped++;
                    continue;
                }

                AppCalendarEvent::create($this->calendarEventPayload([
                    'title' => $eventData['title'],
                    'calendar_id' => $calendarId,
                    'starts_at' => $eventData['date'] . ' 08:00:00',
                    'ends_at' => $eventData['date'] . ' 16:00:00',
                    'all_day' => true,
                    'include_weekends' => (bool) ($eventData['is_weekend'] ?? false),
                    'excluded_dates' => [],
                    'description' => 'Import aus Excel-Kalender',
                    'location' => '',
                    'background_color' => $eventData['background_color'] ?? null,
                    'text_color' => $eventData['text_color'] ?? null,
                    'visibility' => 'private',
                    'project_id' => null,
                    'team_id' => null,
                ]));
                $created++;
            }
        });

        return response()->json([
            'success' => true,
            'created' => $created,
            'skipped' => $skipped,
            'message' => $created . ' Termine importiert' . ($skipped ? ', ' . $skipped . ' Duplikate uebersprungen.' : '.'),
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

        AppCalendar::create([
            ...$data,
            'owner_user_id' => Auth::id(),
            'project_id' => null,
            'team_id' => null,
            'kind' => 'personal',
            'visibility' => 'private',
        ]);

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
        $data = $request->validate($this->calendarEventRules());
        $calendar = $this->calendarForEventData($data);

        $event = DB::transaction(function () use ($data, $calendar) {
            $event = AppCalendarEvent::create($this->calendarEventPayload(
                $this->applyCalendarScope($data, $calendar)
            ));
            $this->syncCalendarAttendees($event, $data, true);

            return $event;
        });

        return $this->calendarEventResponse($event, 'Termin wurde angelegt.');
    }

    public function updateCalendar(Request $request, AppCalendarEvent $event)
    {
        abort_unless($this->canManage($event), 403);
        $data = $request->validate($this->calendarEventRules());
        $calendar = $this->calendarForEventData($data);
        $datesChanged = Carbon::parse($event->starts_at)->toDateTimeString() !== Carbon::parse($data['starts_at'])->toDateTimeString()
            || Carbon::parse($event->ends_at ?: $event->starts_at)->toDateTimeString() !== Carbon::parse($data['ends_at'] ?: $data['starts_at'])->toDateTimeString();

        DB::transaction(function () use ($data, $calendar, $event, $datesChanged) {
            $payload = $this->calendarEventPayload($this->applyCalendarScope($data, $calendar));
            unset($payload['owner_user_id']);
            $event->update($payload);
            $this->syncCalendarAttendees($event, $data, $datesChanged);
        });

        return $this->calendarEventResponse($event, 'Termin wurde aktualisiert.');
    }

    public function respondCalendar(Request $request, AppCalendarEvent $event)
    {
        abort_unless($this->canSee($event, AppCalendarEvent::class), 404);

        $data = $request->validate([
            'response' => ['required', Rule::in(['accepted', 'declined'])],
            'response_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $attendee = $event->attendees()
            ->where('user_id', Auth::id())
            ->where('response_required', true)
            ->firstOrFail();

        $attendee->update([
            'response' => $data['response'],
            'response_note' => $data['response_note'] ?? null,
            'responded_at' => now(),
        ]);

        $owner = $event->owner;
        if ($owner && (int) $owner->id !== (int) Auth::id()) {
            $person = Auth::user()->person;
            $name = trim(($person?->vorname ?? '') . ' ' . ($person?->nachname ?? '')) ?: (Auth::user()->username ?: Auth::user()->email);
            $owner->notify(new ConfiguredEventNotification([
                'message' => $name . ' hat den Termin „' . $event->title . '“ ' . ($data['response'] === 'accepted' ? 'zugesagt.' : 'abgesagt.'),
                'link' => route('apps.calendar', ['year' => $event->starts_at->year]),
                'id' => $event->id,
                'typ' => 'Kalender',
                'event_key' => 'apps.calendar.response',
            ]));
        }

        return $this->calendarEventResponse($event, $data['response'] === 'accepted' ? 'Termin wurde zugesagt.' : 'Termin wurde abgesagt.');
    }

    public function moveCalendar(Request $request, AppCalendarEvent $event)
    {
        abort_unless($this->canManage($event), 403);

        $data = $request->validate([
            'mode' => ['required', Rule::in(['single', 'group'])],
            'source_date' => ['nullable', 'date_format:Y-m-d'],
            'target_date' => ['required', 'date_format:Y-m-d'],
        ]);

        DB::transaction(function () use ($data, $event) {
            $event->refresh();

            if ($data['mode'] === 'group') {
                $this->moveCalendarGroup($event, $data['target_date']);
                return;
            }

            abort_if(empty($data['source_date']), 422, 'Der Ursprungstag fehlt.');
            $this->moveCalendarSingleDay($event, $data['source_date'], $data['target_date']);
        });

        $this->resetCalendarResponses($event->refresh());

        return response()->json(['success' => true]);
    }

    public function copyCalendar(Request $request, AppCalendarEvent $event)
    {
        abort_unless($this->canManage($event), 403);

        $data = $request->validate([
            'ranges' => ['required', 'array', 'min:1'],
            'ranges.*.start_date' => ['required', 'date_format:Y-m-d'],
            'ranges.*.end_date' => ['required', 'date_format:Y-m-d'],
            'include_weekends' => ['nullable', 'boolean'],
        ]);

        foreach ($data['ranges'] as $range) {
            abort_if($range['end_date'] < $range['start_date'], 422, 'Das Bis-Datum darf nicht vor dem Von-Datum liegen.');
        }

        DB::transaction(function () use ($data, $event) {
            $event->refresh();

            foreach ($data['ranges'] as $range) {
                $this->copyCalendarRange($event, $range['start_date'], $range['end_date'], (bool) ($data['include_weekends'] ?? false));
            }
        });

        return response()->json(['success' => true]);
    }

    public function destroyCalendar(AppCalendarEvent $event)
    {
        abort_unless($this->canManage($event), 403);
        $id = $event->id;
        $event->delete();

        return response()->json([
            'success' => true,
            'id' => $id,
            'message' => 'Termin wurde geloescht.',
        ]);
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
                ->with(['owner:id,username,email', 'assignee:id,vorname,nachname', 'workflowTemplate:id,name', 'participation.teilnehmer:id,vorname,nachname', 'shares.person:id,vorname,nachname'])
                ->orderByRaw("status = 'done' asc")
                ->orderByRaw("priority = 'high' desc")
                ->orderBy('due_at')
                ->orderBy('sort_order')
                ->get(),
            'taskTemplates' => $this->visible(AppTaskWorkflowTemplate::query(), AppTaskWorkflowTemplate::class)
                ->with(['owner:id,username,email', 'steps.assignee:id,vorname,nachname'])
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'taskColumns' => $this->taskColumns(),
        ]);
    }

    public function storeTask(Request $request)
    {
        $data = $this->taskPayload($request->validate($this->taskRules()));
        AppTask::create($data);

        return back()->with('success', 'Aufgabe wurde angelegt.');
    }

    public function updateTask(Request $request, AppTask $task)
    {
        abort_unless($this->canWorkOnTask($task), 403);
        $validated = $request->validate($this->taskRules());
        if ($task->project_person_id && !empty($validated['assignee_person_id'])) {
            $validAssignee = Personen::query()
                ->mitarbeiter()
                ->whereKey($validated['assignee_person_id'])
                ->whereHas('projekte', fn ($query) => $query->where('projekts.id', $task->project_id))
                ->exists();
            abort_unless($validAssignee, 422, 'Die verantwortliche Person muss dem Projekt der Teilnahme zugewiesen sein.');
        }

        $task->update($this->taskPayload($validated, $task));

        return back()->with('success', 'Aufgabe wurde aktualisiert.');
    }

    public function destroyTask(AppTask $task)
    {
        abort_unless($this->canManage($task), 403);
        $task->delete();
        return back()->with('success', 'Aufgabe wurde geloescht.');
    }

    public function storeTaskWorkflowTemplate(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.title' => ['required', 'string', 'max:255'],
            'steps.*.description' => ['nullable', 'string', 'max:2000'],
            'steps.*.assignee_person_id' => ['nullable', 'exists:personens,id'],
            'steps.*.status' => ['required', Rule::in(['open', 'progress', 'done'])],
            'steps.*.priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'steps.*.due_offset_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            ...$this->visibilityRules(),
        ]);

        DB::transaction(function () use ($data) {
            $steps = $data['steps'];
            unset($data['steps']);

            $template = AppTaskWorkflowTemplate::create($this->ownedPayload($data));

            foreach ($steps as $index => $step) {
                $template->steps()->create([
                    'title' => $step['title'],
                    'description' => $step['description'] ?? null,
                    'assignee_person_id' => $step['assignee_person_id'] ?? null,
                    'status' => $step['status'],
                    'priority' => $step['priority'],
                    'due_offset_days' => $step['due_offset_days'] ?? null,
                    'sort_order' => $index,
                ]);
            }
        });

        return back()->with('success', 'Workflow-Vorlage wurde gespeichert.');
    }

    public function applyTaskWorkflowTemplate(Request $request, AppTaskWorkflowTemplate $template)
    {
        abort_unless($this->canSee($template, AppTaskWorkflowTemplate::class), 403);

        $data = $request->validate([
            'project_id' => ['required', 'exists:projekts,id'],
            'assignee_person_id' => ['nullable', 'exists:personens,id'],
            'start_date' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($data, $template) {
            $template->load('steps');
            $baseDate = !empty($data['start_date']) ? Carbon::parse($data['start_date']) : now();

            foreach ($template->steps as $index => $step) {
                AppTask::create([
                    'owner_user_id' => Auth::id(),
                    'assignee_person_id' => $data['assignee_person_id'] ?: $step->assignee_person_id,
                    'project_id' => $data['project_id'],
                    'team_id' => null,
                    'workflow_template_id' => $template->id,
                    'title' => $step->title,
                    'description' => $step->description,
                    'status' => $step->status,
                    'priority' => $step->priority,
                    'sort_order' => $index,
                    'due_at' => $step->due_offset_days !== null ? $baseDate->copy()->addDays($step->due_offset_days)->toDateString() : null,
                    'visibility' => 'project',
                ]);
            }
        });

        return back()->with('success', 'Workflow wurde ins Projekt kopiert.');
    }

    public function destroyTaskWorkflowTemplate(AppTaskWorkflowTemplate $template)
    {
        abort_unless($this->canManage($template), 403);
        $template->update(['active' => false]);
        return back()->with('success', 'Workflow-Vorlage wurde deaktiviert.');
    }

    private function taskRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_person_id' => ['nullable', 'exists:personens,id'],
            'status' => ['required', Rule::in(['open', 'progress', 'done'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'due_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            ...$this->visibilityRules(),
        ];
    }

    private function taskPayload(array $data, ?AppTask $task = null): array
    {
        $payload = $task ? $data : $this->ownedPayload($data);
        $previousStatus = $task?->status;

        if ($task?->project_person_id) {
            $payload['project_id'] = $task->project_id;
            $payload['team_id'] = null;
            $payload['visibility'] = 'project';
        }

        if (($payload['status'] ?? null) === 'progress' && $previousStatus !== 'progress' && empty($task?->started_at)) {
            $payload['started_at'] = now();
        }

        if (($payload['status'] ?? null) === 'done' && $previousStatus !== 'done') {
            $payload['completed_at'] = now();
        } elseif (($payload['status'] ?? null) !== 'done') {
            $payload['completed_at'] = null;
        }

        return $payload;
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
        $user = Auth::user();

        return [
            'projects' => Projekt::orderBy('name')->get(['id', 'name']),
            'people' => Personen::with('user:id,person_id,username,email')
                ->whereHas('user')
                ->orderBy('nachname')
                ->orderBy('vorname')
                ->get(['id', 'vorname', 'nachname', 'typ']),
            'shareTeams' => $user
                ? $user->projekte()
                    ->orderBy('projekts.name')
                    ->get(['projekts.id', 'projekts.name'])
                : collect(),
            'visibilityOptions' => [
                ['value' => 'private', 'label' => 'Privat'],
                ['value' => 'all', 'label' => 'Fuer alle sichtbar'],
                ['value' => 'team', 'label' => 'Team'],
                ['value' => 'project', 'label' => 'Projekt'],
            ],
        ];
    }

    private function taskColumns(): array
    {
        return [
            ['value' => 'open', 'label' => 'Offen', 'hint' => 'Noch nicht gestartet'],
            ['value' => 'progress', 'label' => 'In Bearbeitung', 'hint' => 'Wird gerade gemacht'],
            ['value' => 'done', 'label' => 'Erledigt', 'hint' => 'Abgeschlossen'],
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

    private function fileOwnershipPayload(array $data, ?int $parentId): array
    {
        return $this->ownedPayload([
            'parent_id' => $parentId,
            'visibility' => $data['visibility'] ?? 'private',
            'project_id' => $data['project_id'] ?? null,
            'team_id' => $data['team_id'] ?? null,
        ]);
    }

    private function parseShareEmails(?string $emails)
    {
        $items = collect(preg_split('/[\s,;]+/', (string) $emails, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($email) => strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values();

        $invalid = $items->reject(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));

        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages([
                'emails' => 'Ungueltige E-Mail-Adresse: ' . $invalid->first(),
            ]);
        }

        return $items;
    }

    private function notifyAppShareRecipients($item, array $data, $personIds, $teamIds, $emails): int
    {
        $recipientUsers = collect();

        if ($personIds->isNotEmpty()) {
            $recipientUsers = $recipientUsers->merge(
                User::whereIn('person_id', $personIds)->get(['id', 'person_id', 'email'])
            );
        }

        if ($teamIds->isNotEmpty()) {
            $recipientUsers = $recipientUsers->merge(
                User::whereHas('projekte', fn (Builder $query) => $query->whereIn('projekts.id', $teamIds))
                    ->get(['id', 'person_id', 'email'])
            );
        }

        if ($emails->isNotEmpty()) {
            $recipientUsers = $recipientUsers->merge(
                User::whereIn('email', $emails)->get(['id', 'person_id', 'email'])
            );
        }

        $recipientUsers = $recipientUsers
            ->filter(fn (User $user) => (int) $user->id !== (int) Auth::id())
            ->unique('id')
            ->values();

        if ($recipientUsers->isNotEmpty()) {
            Notification::send($recipientUsers, new ConfiguredEventNotification([
                'message' => $this->shareNotificationMessage($item),
                'link' => $this->shareNotificationLink($item),
                'id' => $item->id,
                'typ' => $this->shareNotificationType($item),
                'event_key' => 'apps.share',
            ]));
        }

        $recipients = collect($emails)->merge($recipientUsers->pluck('email'));
        $failures = 0;

        foreach ($recipients->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->unique() as $email) {
            try {
                $this->sendShareMail($email, $item, $data['message'] ?? null);
            } catch (\Throwable $exception) {
                $failures++;
                report($exception);
            }
        }

        return $failures;
    }

    private function shareNotificationMessage($item): string
    {
        $sender = Auth::user()?->name ?: 'Ein Benutzer';
        $name = $item->title ?? $item->name ?? 'Eintrag';

        return $sender . ' hat "' . $name . '" fuer dich freigegeben.';
    }

    private function shareNotificationType($item): string
    {
        return match (true) {
            $item instanceof AppFile => 'Dateimanager',
            $item instanceof AppCalendarEvent => 'Kalender',
            $item instanceof AppContact => 'Kontakte',
            $item instanceof AppTask => 'Taskmanager',
            $item instanceof AppTaskWorkflowTemplate => 'Workflow',
            $item instanceof AppPopup => 'Popup',
            default => 'Apps',
        };
    }

    private function shareNotificationLink($item): ?string
    {
        return match (true) {
            $item instanceof AppFile && $item->type === 'folder' => route('apps.files', ['folder' => $item->id]),
            $item instanceof AppFile && $item->parent_id => route('apps.files', ['folder' => $item->parent_id]),
            $item instanceof AppFile => route('apps.files'),
            $item instanceof AppCalendarEvent => route('apps.calendar'),
            $item instanceof AppContact => route('apps.contacts'),
            $item instanceof AppTask,
            $item instanceof AppTaskWorkflowTemplate => route('apps.tasks'),
            $item instanceof AppPopup => route('apps.popups'),
            default => route('apps.index'),
        };
    }

    private function inheritFileShares(AppFile $file, ?int $parentId): void
    {
        if (! $parentId) {
            return;
        }

        $parent = AppFile::with('shares')->find($parentId);

        if (! $parent) {
            return;
        }

        foreach ($parent->shares as $share) {
            AppShare::updateOrCreate(
                [
                    'shareable_type' => AppFile::class,
                    'shareable_id' => $file->id,
                    'person_id' => $share->person_id,
                    'email' => $share->email,
                    'team_id' => $share->team_id,
                ],
                [
                    'shared_by_user_id' => $share->shared_by_user_id,
                    'permission' => $share->permission,
                    'message' => $share->message,
                    'sent_at' => $share->sent_at,
                ]
            );
        }
    }

    private function syncFolderSharesToChildren(AppFile $folder): void
    {
        $folder->loadMissing('shares');

        $folder->children()->get()->each(function (AppFile $child) use ($folder) {
            foreach ($folder->shares as $share) {
                AppShare::updateOrCreate(
                    [
                        'shareable_type' => AppFile::class,
                        'shareable_id' => $child->id,
                        'person_id' => $share->person_id,
                        'email' => $share->email,
                        'team_id' => $share->team_id,
                    ],
                    [
                        'shared_by_user_id' => $share->shared_by_user_id,
                        'permission' => $share->permission,
                        'message' => $share->message,
                        'sent_at' => $share->sent_at,
                    ]
                );
            }

            if ($child->type === 'folder') {
                $this->syncFolderSharesToChildren($child);
            }
        });
    }

    private function transferFileTreeOwner(AppFile $file, User $newOwner): void
    {
        $file->update(['owner_user_id' => $newOwner->id]);

        if ($file->type !== 'folder') {
            return;
        }

        $file->children()->get()->each(fn (AppFile $child) => $this->transferFileTreeOwner($child, $newOwner));
    }

    private function collectAppFileUploads(Request $request): array
    {
        $uploads = [];

        if ($request->hasFile('file')) {
            $uploads[] = [
                'file' => $request->file('file'),
                'relative_path' => $request->input('relative_path'),
            ];
        }

        foreach ($request->file('files', []) as $index => $file) {
            if ($file instanceof UploadedFile) {
                $uploads[] = [
                    'file' => $file,
                    'relative_path' => $request->input("relative_paths.{$index}"),
                ];
            }
        }

        return $uploads;
    }

    private function normalizeUploadPath(?string $relativePath, UploadedFile $file): array
    {
        $path = trim(str_replace('\\', '/', (string) ($relativePath ?: $file->getClientOriginalName())), '/');
        $segments = array_values(array_filter(explode('/', $path), fn ($segment) => trim($segment) !== '' && ! in_array($segment, ['.', '..'], true)));
        $fileSegment = array_pop($segments) ?: $file->getClientOriginalName();

        return [
            array_map(fn ($segment) => $this->sanitizeUploadSegment($segment, 'Ordner'), $segments),
            $this->sanitizeUploadSegment($fileSegment, $file->getClientOriginalName() ?: 'Datei'),
        ];
    }

    private function sanitizeUploadSegment(string $segment, string $fallback): string
    {
        $segment = str_replace(["\0", '/', '\\'], '', $segment);
        $segment = preg_replace('/[\r\n\t]+/', ' ', $segment) ?: '';
        $segment = preg_replace('/\s+/', ' ', $segment) ?: '';
        $segment = trim($segment, " .");

        if ($segment === '' || in_array($segment, ['.', '..'], true)) {
            $segment = $fallback;
        }

        return function_exists('mb_substr') ? mb_substr($segment, 0, 255) : substr($segment, 0, 255);
    }

    private function ensureUploadFolderPath(array $folders, ?int $rootParentId, array $data): ?int
    {
        $parentId = $rootParentId;

        foreach ($folders as $folderName) {
            $folder = AppFile::firstOrCreate(
                [
                    'owner_user_id' => Auth::id(),
                    'parent_id' => $parentId,
                    'type' => 'folder',
                    'name' => $folderName,
                ],
                $this->fileOwnershipPayload($data, $parentId) + [
                    'size' => 0,
                ]
            );

            if ($folder->wasRecentlyCreated) {
                $this->inheritFileShares($folder, $parentId);
            }

            $parentId = $folder->id;
        }

        return $parentId;
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

    private function calendarEventRules(): array
    {
        return [
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
            'audience' => ['nullable', Rule::in(['owner', 'assignees', 'project'])],
            'responsible_user_ids' => ['nullable', 'array'],
            'responsible_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'viewer_user_ids' => ['nullable', 'array'],
            'viewer_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'send_notification' => ['nullable', 'boolean'],
            ...$this->visibilityRules(),
        ];
    }

    private function calendarForEventData(array &$data): AppCalendar
    {
        $calendarId = $data['calendar_id'] ?? $this->personalCalendarId();
        abort_unless($calendarId, 422, 'Bitte einen Kalender auswaehlen.');

        $calendar = AppCalendar::findOrFail($calendarId);
        abort_unless($this->canManage($calendar), 403);
        $data['calendar_id'] = $calendar->id;

        return $calendar;
    }

    private function applyCalendarScope(array $data, AppCalendar $calendar): array
    {
        if ($calendar->kind === 'project' && $calendar->project_id) {
            $data['visibility'] = 'project';
            $data['project_id'] = $calendar->project_id;
            $data['team_id'] = null;
            $data['audience'] = $data['audience'] ?? 'assignees';

            return $data;
        }

        $data['audience'] = 'owner';

        return $data;
    }

    private function syncCalendarAttendees(AppCalendarEvent $event, array $data, bool $resetResponses): void
    {
        if (! $event->project_id || $event->calendar?->kind !== 'project') {
            $event->attendees()->delete();
            return;
        }

        $responsibleIds = collect($data['responsible_user_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique();
        $viewerIds = collect($data['viewer_user_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->diff($responsibleIds);
        $requestedIds = $responsibleIds->merge($viewerIds)->unique()->values();
        $currentIds = $event->attendees()->pluck('user_id')->map(fn ($id) => (int) $id)->sort()->values();

        if (! Auth::user()->can('apps.calendar.project.assign')) {
            abort_unless($requestedIds->sort()->values()->all() === $currentIds->all(), 403, 'Dir fehlt die Berechtigung, Projekttermine zuzuweisen.');
            return;
        }

        $allowedIds = User::whereHas('projekte', fn (Builder $query) => $query->where('projekts.id', $event->project_id))
            ->whereIn('id', $requestedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($requestedIds->diff($allowedIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'responsible_user_ids' => 'Alle ausgewaehlten Personen muessen dem Projekt zugeordnet sein.',
            ]);
        }

        $existing = $event->attendees()->get()->keyBy('user_id');
        $event->attendees()->whereNotIn('user_id', $requestedIds)->delete();
        $notifyIds = collect();

        foreach ($requestedIds as $userId) {
            $responsible = $responsibleIds->contains($userId);
            $current = $existing->get($userId);
            $requiresReset = $responsible && ($resetResponses || ! $current || $current->access_level !== 'responsible');

            AppCalendarEventAttendee::updateOrCreate(
                ['event_id' => $event->id, 'user_id' => $userId],
                [
                    'assigned_by_user_id' => Auth::id(),
                    'access_level' => $responsible ? 'responsible' : 'viewer',
                    'response_required' => $responsible,
                    'response' => $responsible ? ($requiresReset ? 'pending' : $current->response) : 'accepted',
                    'response_note' => $requiresReset ? null : $current?->response_note,
                    'responded_at' => $requiresReset ? null : $current?->responded_at,
                ]
            );

            if (! $current || $requiresReset) {
                $notifyIds->push($userId);
            }
        }

        if (($data['send_notification'] ?? true) && $notifyIds->isNotEmpty()) {
            $recipients = User::whereIn('id', $notifyIds)->whereKeyNot(Auth::id())->get();
            foreach ($recipients as $recipient) {
                $responsible = $responsibleIds->contains((int) $recipient->id);
                $recipient->notify(new ConfiguredEventNotification([
                    'message' => $responsible
                        ? 'Du wurdest dem Termin „' . $event->title . '“ am ' . $event->starts_at->format('d.m.Y') . ' zugewiesen. Bitte sage zu oder ab.'
                        : 'Der Termin „' . $event->title . '“ am ' . $event->starts_at->format('d.m.Y') . ' wurde fuer dich freigegeben.',
                    'link' => route('apps.calendar', ['year' => $event->starts_at->year]),
                    'id' => $event->id,
                    'typ' => 'Kalender',
                    'event_key' => $responsible ? 'apps.calendar.assignment' : 'apps.calendar.shared',
                ]));
            }
        }
    }

    private function calendarPeople()
    {
        $projectIds = $this->calendarProjectIds(Auth::user());
        $query = User::query()
            ->with(['person:id,vorname,nachname', 'projekte:id,name'])
            ->whereHas('projekte');

        if (! Auth::user()->hasRole('Administrator')) {
            $query->whereHas('projekte', fn (Builder $projects) => $projects->whereIn('projekts.id', $projectIds));
        }

        return $query->orderBy('username')
            ->get(['id', 'person_id', 'username', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => trim(($user->person?->vorname ?? '') . ' ' . ($user->person?->nachname ?? '')) ?: ($user->username ?: $user->email),
                'project_ids' => $user->projekte->pluck('id')->map(fn ($id) => (int) $id)->values(),
            ]);
    }

    private function calendarEventViewPayload(AppCalendarEvent $event): array
    {
        $canManage = $this->canManage($event);
        $myAssignment = $event->attendees->firstWhere('user_id', Auth::id());
        $attendees = $canManage
            ? $event->attendees
            : $event->attendees->where('user_id', Auth::id())->values();

        return [
            ...$event->toArray(),
            'attendees' => $attendees->map(fn (AppCalendarEventAttendee $attendee) => [
                'id' => $attendee->id,
                'user_id' => $attendee->user_id,
                'name' => trim(($attendee->user?->person?->vorname ?? '') . ' ' . ($attendee->user?->person?->nachname ?? ''))
                    ?: ($attendee->user?->username ?: $attendee->user?->email),
                'access_level' => $attendee->access_level,
                'response_required' => $attendee->response_required,
                'response' => $attendee->response,
                'response_note' => $attendee->response_note,
                'responded_at' => $attendee->responded_at,
            ])->values(),
            'can_manage' => $canManage,
            'can_respond' => (bool) ($myAssignment?->response_required && Auth::user()->can('apps.calendar.respond')),
            'my_assignment' => $myAssignment ? [
                'response' => $myAssignment->response,
                'response_note' => $myAssignment->response_note,
                'access_level' => $myAssignment->access_level,
            ] : null,
            'response_summary' => [
                'pending' => $event->attendees->where('response_required', true)->where('response', 'pending')->count(),
                'accepted' => $event->attendees->where('response_required', true)->where('response', 'accepted')->count(),
                'declined' => $event->attendees->where('response_required', true)->where('response', 'declined')->count(),
            ],
        ];
    }

    private function moveCalendarGroup(AppCalendarEvent $event, string $targetDate): void
    {
        $start = Carbon::parse($event->starts_at);
        $end = Carbon::parse($event->ends_at ?: $event->starts_at);
        $deltaDays = (int) Carbon::parse($start->toDateString())->diffInDays(Carbon::parse($targetDate), false);

        $excludedDates = collect($event->excluded_dates ?: [])
            ->map(fn (string $date) => Carbon::parse($date)->addDays($deltaDays)->toDateString())
            ->unique()
            ->sort()
            ->values()
            ->all();

        $event->update([
            'starts_at' => $start->addDays($deltaDays),
            'ends_at' => $end->addDays($deltaDays),
            'excluded_dates' => $excludedDates,
        ]);
    }

    private function moveCalendarSingleDay(AppCalendarEvent $event, string $sourceDate, string $targetDate): void
    {
        $startDate = Carbon::parse($event->starts_at)->toDateString();
        $endDate = Carbon::parse($event->ends_at ?: $event->starts_at)->toDateString();

        abort_unless($sourceDate >= $startDate && $sourceDate <= $endDate, 422, 'Der Ursprungstag gehoert nicht zu diesem Termin.');

        $excludedDates = collect($event->excluded_dates ?: [])
            ->push($sourceDate)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $startTime = Carbon::parse($event->starts_at)->format('H:i:s');
        $endTime = Carbon::parse($event->ends_at ?: $event->starts_at)->format('H:i:s');
        $singleEvent = $event->replicate();
        $singleEvent->starts_at = Carbon::parse($targetDate . ' ' . $startTime);
        $singleEvent->ends_at = Carbon::parse($targetDate . ' ' . $endTime);
        $singleEvent->include_weekends = false;
        $singleEvent->excluded_dates = [];

        $event->update(['excluded_dates' => $excludedDates]);
        $singleEvent->save();
        $this->copyCalendarAttendees($event, $singleEvent);
    }

    private function copyCalendarRange(AppCalendarEvent $event, string $startDate, string $endDate, bool $includeWeekends): void
    {
        $startTime = Carbon::parse($event->starts_at)->format('H:i:s');
        $endTime = Carbon::parse($event->ends_at ?: $event->starts_at)->format('H:i:s');

        $copy = $event->replicate();
        $copy->starts_at = Carbon::parse($startDate . ' ' . $startTime);
        $copy->ends_at = Carbon::parse($endDate . ' ' . $endTime);
        $copy->include_weekends = $includeWeekends;
        $copy->excluded_dates = [];
        $copy->save();
        $this->copyCalendarAttendees($event, $copy);
    }

    private function copyCalendarAttendees(AppCalendarEvent $source, AppCalendarEvent $target): void
    {
        foreach ($source->attendees()->get() as $attendee) {
            $target->attendees()->create([
                'user_id' => $attendee->user_id,
                'assigned_by_user_id' => Auth::id(),
                'access_level' => $attendee->access_level,
                'response_required' => $attendee->response_required,
                'response' => $attendee->response_required ? 'pending' : 'accepted',
            ]);
        }
    }

    private function resetCalendarResponses(AppCalendarEvent $event): void
    {
        $userIds = $event->attendees()
            ->where('response_required', true)
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return;
        }

        $event->attendees()->where('response_required', true)->update([
            'response' => 'pending',
            'response_note' => null,
            'responded_at' => null,
            'updated_at' => now(),
        ]);

        $recipients = User::whereIn('id', $userIds)->whereKeyNot(Auth::id())->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ConfiguredEventNotification([
                'message' => 'Der Termin „' . $event->title . '“ wurde verschoben. Bitte bestaetige den neuen Termin.',
                'link' => route('apps.calendar', ['year' => $event->starts_at->year]),
                'id' => $event->id,
                'typ' => 'Kalender',
                'event_key' => 'apps.calendar.changed',
            ]));
        }
    }

    private function ensureDefaultCalendars(): void
    {
        $user = Auth::user();

        AppCalendar::firstOrCreate(
            ['owner_user_id' => $user->id, 'project_id' => null, 'kind' => 'personal', 'name' => 'Mein Kalender'],
            [
                'background_color' => '#ff7a00',
                'text_color' => '#ffffff',
                'visibility' => 'private',
            ]
        );

        Projekt::whereIn('id', $this->calendarProjectIds($user))
            ->get(['id', 'name'])
            ->each(function (Projekt $project) use ($user) {
                AppCalendar::firstOrCreate(
                    ['project_id' => $project->id, 'kind' => 'project'],
                    [
                        'owner_user_id' => $user->id,
                        'name' => $project->name,
                        'background_color' => $this->projectColor($project->id),
                        'text_color' => '#ffffff',
                        'visibility' => 'project',
                    ]
                );
            });
    }

    private function personalCalendarId(): ?int
    {
        return AppCalendar::where('owner_user_id', Auth::id())
            ->whereNull('project_id')
            ->where('name', 'Mein Kalender')
            ->value('id');
    }

    private function calendarEventsForYear(int $year)
    {
        return $this->visible(AppCalendarEvent::query(), AppCalendarEvent::class)
            ->with([
                'owner:id,person_id,username,email',
                'calendar:id,name,background_color,text_color,project_id,kind',
                'shares.person:id,vorname,nachname',
                'attendees.user:id,person_id,username,email',
                'attendees.user.person:id,vorname,nachname',
            ])
            ->whereDate('starts_at', '<=', $year . '-12-31')
            ->where(function (Builder $q) use ($year) {
                $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $year . '-01-01');
            })
            ->orderBy('starts_at')
            ->get()
            ->map(fn (AppCalendarEvent $event) => $this->calendarEventViewPayload($event));
    }

    private function calendarEventResponse(AppCalendarEvent $event, string $message)
    {
        $event->load([
            'owner:id,person_id,username,email',
            'calendar:id,name,background_color,text_color,project_id,kind',
            'shares.person:id,vorname,nachname',
            'attendees.user:id,person_id,username,email',
            'attendees.user.person:id,vorname,nachname',
        ]);

        return response()->json([
            'success' => true,
            'event' => $this->calendarEventViewPayload($event),
            'message' => $message,
        ]);
    }

    private function extractCalendarImportEvents(Spreadsheet $spreadsheet, ?int $calendarId): array
    {
        $monthMap = [
            'januar' => 1,
            'februar' => 2,
            'maerz' => 3,
            'märz' => 3,
            'april' => 4,
            'mai' => 5,
            'juni' => 6,
            'juli' => 7,
            'august' => 8,
            'september' => 9,
            'oktober' => 10,
            'november' => 11,
            'dezember' => 12,
        ];

        $events = [];
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $highestRow = $sheet->getHighestDataRow();

            for ($row = 1; $row <= $highestRow; $row++) {
                for ($slot = 0; $slot < 6; $slot++) {
                    $baseColumn = 1 + ($slot * 4);
                    $monthName = mb_strtolower(trim((string) $sheet->getCell([$baseColumn, $row])->getFormattedValue()));

                    if (! isset($monthMap[$monthName])) {
                        continue;
                    }

                    $year = $this->calendarImportYear($sheet, $row);
                    if (! $year) {
                        continue;
                    }

                    $month = $monthMap[$monthName];
                    $lastDay = Carbon::create($year, $month, 1)->endOfMonth()->day;
                    $holidays = $this->germanHolidays($year);

                    for ($dayOffset = 1; $dayOffset <= 31; $dayOffset++) {
                        $dayRow = $row + $dayOffset;
                        $dayNumber = (int) $sheet->getCell([$baseColumn, $dayRow])->getCalculatedValue();
                        if ($dayNumber < 1 || $dayNumber > $lastDay) {
                            continue;
                        }

                        $date = Carbon::create($year, $month, $dayNumber)->startOfDay();
                        $iso = $date->toDateString();
                        $importCell = $sheet->getCell([$baseColumn + 2, $dayRow]);
                        $cellEvents = $this->calendarImportCellEvents($importCell);

                        foreach ($cellEvents as $cellEvent) {
                            $title = trim($cellEvent['title']);
                            if ($title === '') {
                                continue;
                            }

                            $isHoliday = isset($holidays[$iso]);
                            $isHolidayEvent = $isHoliday && $this->normalizeImportText($title) === $this->normalizeImportText($holidays[$iso]);
                            $duplicate = $this->calendarImportDuplicateExists($title, $iso, $calendarId, $cellEvent['background_color'], $cellEvent['text_color']);
                            $events[] = [
                                'key' => sha1($sheet->getTitle() . '|' . $iso . '|' . $title . '|' . ($cellEvent['background_color'] ?? '') . '|' . count($events)),
                                'date' => $iso,
                                'weekday' => $this->germanWeekday($date),
                                'title' => $title,
                                'calendar_id' => $calendarId,
                                'background_color' => $cellEvent['background_color'],
                                'text_color' => $cellEvent['text_color'],
                                'is_weekend' => $date->isWeekend(),
                                'is_holiday' => $isHoliday,
                                'is_holiday_event' => $isHolidayEvent,
                                'holiday_name' => $holidays[$iso] ?? null,
                                'duplicate' => $duplicate,
                                'selected' => ! $date->isWeekend() && ! $isHoliday && ! $duplicate,
                            ];
                        }
                    }
                }
            }
        }

        return array_values($events);
    }

    private function calendarImportCellEvents($cell): array
    {
        $fallbackColor = $this->calendarImportFontColor($cell->getWorksheet()->getStyle($cell->getCoordinate())->getFont());
        $value = $cell->getValue();

        if ($value instanceof RichText) {
            $lines = [['title' => '', 'background_color' => null, 'text_color' => null]];

            foreach ($value->getRichTextElements() as $element) {
                $color = $this->calendarImportFontColor($element->getFont()) ?? $fallbackColor;
                $parts = preg_split('/(\R)/u', $element->getText(), -1, PREG_SPLIT_DELIM_CAPTURE);

                foreach ($parts ?: [] as $part) {
                    if (preg_match('/^\R$/u', $part)) {
                        $lines[] = ['title' => '', 'background_color' => null, 'text_color' => null];
                        continue;
                    }

                    $lastIndex = array_key_last($lines);
                    $lines[$lastIndex]['title'] .= $part;
                    if (trim($part) !== '' && ! $lines[$lastIndex]['background_color'] && $color) {
                        $lines[$lastIndex]['background_color'] = $color;
                        $lines[$lastIndex]['text_color'] = $this->calendarContrastTextColor($color);
                    }
                }
            }

            return $this->expandCalendarImportLines($lines);
        }

        $texts = preg_split('/\R+/', trim((string) $cell->getFormattedValue()));

        $lines = array_map(fn (string $title) => [
            'title' => trim($title),
            'background_color' => $fallbackColor,
            'text_color' => $fallbackColor ? $this->calendarContrastTextColor($fallbackColor) : null,
        ], $texts ?: []);

        return $this->expandCalendarImportLines($lines);
    }

    private function expandCalendarImportLines(array $lines): array
    {
        $events = [];

        foreach ($lines as $line) {
            foreach ($this->splitCalendarImportTitle($line['title'] ?? '') as $title) {
                $events[] = [
                    'title' => $title,
                    'background_color' => $line['background_color'] ?? null,
                    'text_color' => $line['text_color'] ?? null,
                ];
            }
        }

        return $events;
    }

    private function splitCalendarImportTitle(string $title): array
    {
        return array_values(array_filter(array_map(
            fn (string $part) => trim($part),
            preg_split('/\s+\/\s+/', trim($title)) ?: []
        ), fn (string $part) => $part !== ''));
    }

    private function calendarImportFontColor($font): ?string
    {
        if (! $font || ! $font->getColor()) {
            return null;
        }

        return $this->calendarImportHexColor($font->getColor()->getRGB());
    }

    private function calendarImportHexColor(?string $color): ?string
    {
        $color = strtoupper(ltrim((string) $color, '#'));
        if (strlen($color) === 8) {
            $color = substr($color, 2);
        }

        if (! preg_match('/^[A-F0-9]{6}$/', $color) || in_array($color, ['000000', 'FFFFFF'], true)) {
            return null;
        }

        return '#' . $color;
    }

    private function calendarContrastTextColor(string $backgroundColor): string
    {
        $color = ltrim($backgroundColor, '#');
        if (! preg_match('/^[A-Fa-f0-9]{6}$/', $color)) {
            return '#ffffff';
        }

        $red = hexdec(substr($color, 0, 2));
        $green = hexdec(substr($color, 2, 2));
        $blue = hexdec(substr($color, 4, 2));
        $brightness = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $brightness > 160 ? '#111827' : '#ffffff';
    }

    private function calendarImportYear($sheet, int $monthRow): ?int
    {
        for ($row = $monthRow; $row >= max(1, $monthRow - 3); $row--) {
            for ($column = 1; $column <= 24; $column++) {
                $value = (string) $sheet->getCell([$column, $row])->getFormattedValue();
                if (preg_match('/\b(20\d{2})\b/', $value, $match)) {
                    return (int) $match[1];
                }
            }
        }

        return null;
    }

    private function calendarImportDuplicateExists(string $title, string $date, ?int $calendarId, ?string $backgroundColor = null, ?string $textColor = null): bool
    {
        return $this->visible(AppCalendarEvent::query(), AppCalendarEvent::class)
            ->where('title', $title)
            ->where('calendar_id', $calendarId)
            ->when($backgroundColor, fn (Builder $query) => $query->where('background_color', $backgroundColor))
            ->when($textColor, fn (Builder $query) => $query->where('text_color', $textColor))
            ->whereDate('starts_at', $date)
            ->exists();
    }

    private function normalizeImportText(string $value): string
    {
        $value = mb_strtolower(trim(preg_replace('/\s+/', ' ', $value)));

        return strtr($value, [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
        ]);
    }

    private function calendarSpreadsheet($events, int $year): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kalender ' . $year);
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        $holidays = $this->germanHolidays($year);
        $title = 'Kalender ' . $year;

        $this->writeCalendarHalfYear($sheet, $events, $year, 0, 1, $title, $monthNames, $holidays);
        $this->writeCalendarHalfYear($sheet, $events, $year, 6, 34, $title, $monthNames, $holidays);

        for ($month = 0; $month < 6; $month++) {
            $baseColumn = 1 + ($month * 4);
            $sheet->getColumnDimensionByColumn($baseColumn)->setWidth(3);
            $sheet->getColumnDimensionByColumn($baseColumn + 1)->setWidth(4);
            $sheet->getColumnDimensionByColumn($baseColumn + 2)->setWidth(27);
            $sheet->getColumnDimensionByColumn($baseColumn + 3)->setWidth(3);
        }

        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.35)->setRight(0.25)->setBottom(0.35)->setLeft(0.25);
        $sheet->getPageSetup()->setPrintArea('A1:X66');

        return $spreadsheet;
    }

    private function writeCalendarHalfYear($sheet, $events, int $year, int $startMonth, int $startRow, string $title, array $monthNames, array $holidays): void
    {
        $sheet->setCellValue([1, $startRow], $title);
        $sheet->getRowDimension($startRow)->setRowHeight(45);
        $sheet->getStyle($this->cellAddress(1, $startRow))->getFont()->setBold(true)->setSize(30);
        $sheet->getStyle($this->cellAddress(1, $startRow))->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        $monthHeaderRow = $startRow + 1;
        $firstDayRow = $startRow + 2;
        $sheet->getRowDimension($monthHeaderRow)->setRowHeight(22.5);

        for ($slot = 0; $slot < 6; $slot++) {
            $month = $startMonth + $slot + 1;
            $baseColumn = 1 + ($slot * 4);
            $lastDay = Carbon::create($year, $month, 1)->endOfMonth()->day;

            $sheet->setCellValue([$baseColumn, $monthHeaderRow], $monthNames[$month - 1]);
            $sheet->getStyle($this->cellAddress($baseColumn, $monthHeaderRow))->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle([$baseColumn, $monthHeaderRow, $baseColumn + 3, $monthHeaderRow])
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            for ($dayNumber = 1; $dayNumber <= 31; $dayNumber++) {
                $row = $firstDayRow + $dayNumber - 1;
                $range = [$baseColumn, $row, $baseColumn + 3, $row];
                $sheet->getRowDimension($row)->setRowHeight(18.75);
                $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('D9D9D9');

                if ($dayNumber > $lastDay) {
                    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
                    continue;
                }

                $date = Carbon::create($year, $month, $dayNumber)->startOfDay();
                $iso = $date->toDateString();
                $dayEvents = $this->calendarEventsForDay($events, $iso);
                $holiday = $holidays[$iso] ?? null;
                $fillColor = $this->calendarDayFillColor($date, $holiday);

                $sheet->setCellValue([$baseColumn, $row], $dayNumber);
                $sheet->setCellValue([$baseColumn + 1, $row], $this->germanWeekday($date));
                $sheet->setCellValue([$baseColumn + 2, $row], $this->calendarDayRichText($dayEvents, $holiday));
                $sheet->setCellValue([$baseColumn + 3, $row], ($dayNumber === 1 || $date->isMonday()) ? $date->isoWeek() : '');

                $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fillColor);
                $sheet->getStyle([$baseColumn, $row, $baseColumn + 1, $row])->getFont()->setBold(true);
                $sheet->getStyle($this->cellAddress($baseColumn + 2, $row))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                if ($holiday) {
                    $sheet->getStyle([$baseColumn, $row, $baseColumn + 1, $row])->getFont()->setBold(true)->getColor()->setRGB('CC0000');
                    $sheet->getStyle($this->cellAddress($baseColumn + 3, $row))->getFont()->setBold(true)->getColor()->setRGB('CC0000');
                } elseif ($dayEvents->isNotEmpty()) {
                    $sheet->getStyle($this->cellAddress($baseColumn + 2, $row))->getFont()
                        ->setBold(true)
                        ->setSize($dayEvents->count() > 1 ? 8 : 10);
                }
            }
        }
    }

    private function calendarDayFillColor(Carbon $date, ?string $holiday): string
    {
        if ($holiday) {
            return 'FFD9D9';
        }

        if ($date->isSunday()) {
            return 'FFCC99';
        }

        if ($date->isSaturday()) {
            return 'FFFFCC';
        }

        return 'FFFFFF';
    }

    private function calendarEventsForDay($events, string $iso)
    {
        return $events->filter(function (AppCalendarEvent $event) use ($iso) {
            $start = Carbon::parse($event->starts_at)->toDateString();
            $end = Carbon::parse($event->ends_at ?: $event->starts_at)->toDateString();
            $date = Carbon::parse($iso);

            if ($iso < $start || $iso > $end) {
                return false;
            }

            if (in_array($iso, $event->excluded_dates ?: [], true)) {
                return false;
            }

            return $event->include_weekends || ! $date->isWeekend();
        })->values();
    }

    private function calendarDayText($events, ?string $holiday): string
    {
        $lines = [];

        if ($holiday) {
            $lines[] = $holiday;
        }

        foreach ($events as $event) {
            $lines[] = $event->title;
        }

        return implode("\n", $lines);
    }

    private function calendarDayRichText($events, ?string $holiday)
    {
        if (! $holiday && $events->isEmpty()) {
            return '';
        }

        $richText = new RichText();

        if ($holiday) {
            $holidayRun = $richText->createTextRun($holiday);
            $holidayRun->getFont()->setBold(true)->getColor()->setRGB('CC0000');
        }

        foreach ($events as $index => $event) {
            if ($holiday || $index > 0) {
                $richText->createText("\n");
            }

            $run = $richText->createTextRun($event->title);
            $run->getFont()
                ->setBold(true)
                ->setSize($events->count() > 1 ? 8 : 10)
                ->getColor()
                ->setRGB($this->excelColor($event->background_color ?: $event->calendar?->background_color ?: '#0070C0', '0070C0'));
        }

        return $richText;
    }

    private function excelColor(?string $color, string $fallback): string
    {
        $color = ltrim((string) $color, '#');

        return preg_match('/^[A-Fa-f0-9]{6}$/', $color) ? strtoupper($color) : $fallback;
    }

    private function cellAddress(int $column, int $row): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column) . $row;
    }

    private function germanHolidays(int $year): array
    {
        $easter = $this->easterDate($year);

        return [
            $year . '-01-01' => 'Neujahr',
            $year . '-01-06' => 'Hl. Drei Koenige',
            $year . '-05-01' => 'Tag der Arbeit',
            $year . '-08-15' => 'Mariae Himmelfahrt',
            $year . '-10-03' => 'Tag der Deutschen Einheit',
            $year . '-11-01' => 'Allerheiligen',
            $year . '-12-25' => '1. Weihnachtstag',
            $year . '-12-26' => '2. Weihnachtstag',
            $easter->copy()->subDays(48)->toDateString() => 'Rosenmontag',
            $easter->copy()->subDays(2)->toDateString() => 'Karfreitag',
            $easter->copy()->addDay()->toDateString() => 'Ostermontag',
            $easter->copy()->addDays(39)->toDateString() => 'Christi Himmelfahrt',
            $easter->copy()->addDays(50)->toDateString() => 'Pfingstmontag',
            $easter->copy()->addDays(60)->toDateString() => 'Fronleichnam',
        ];
    }

    private function easterDate(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day)->startOfDay();
    }

    private function germanWeekday(Carbon $date): string
    {
        return ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][$date->dayOfWeek];
    }

    private function visibilityLabel(?string $visibility): string
    {
        return match ($visibility) {
            'all' => 'Fuer alle',
            'team' => 'Team',
            'project' => 'Projekt',
            default => 'Privat',
        };
    }

    private function projectColor(int $id): string
    {
        $colors = ['#ef4444', '#2563eb', '#16a34a', '#9333ea', '#f97316', '#0891b2', '#be123c', '#4f46e5'];

        return $colors[$id % count($colors)];
    }

    private function userTeamIds(User $user)
    {
        return $user->projekte()->pluck('projekts.id')->map(fn ($id) => (int) $id)->filter()->unique()->values();
    }

    private function calendarProjectIds(User $user)
    {
        if ($user->hasRole('Administrator')) {
            return Projekt::pluck('id')->map(fn ($id) => (int) $id)->values();
        }

        if ($user->hasRole('Abteilungsleitung') && $user->abteilung_id && $user->can('apps.calendar.project.view.all')) {
            return Projekt::where('abteilung_id', $user->abteilung_id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();
        }

        return $this->userTeamIds($user);
    }

    private function visible(Builder $query, string $modelClass): Builder
    {
        $user = Auth::user();
        $personId = $user->person_id;
        $teamId = $user->current_team_id;
        $teamIds = in_array($modelClass, [AppCalendarEvent::class, AppCalendar::class], true)
            ? $this->calendarProjectIds($user)
            : $this->userTeamIds($user);

        if ($modelClass === AppCalendarEvent::class) {
            return $this->visibleCalendarEvents($query, $user, $teamIds);
        }

        if ($modelClass === AppCalendar::class) {
            return $this->visibleCalendars($query, $user, $teamIds);
        }

        return $query->where(function (Builder $q) use ($user, $personId, $teamId, $teamIds, $modelClass) {
            $q->where('owner_user_id', $user->id)
                ->orWhere('visibility', 'all');

            if ($teamId) {
                $q->orWhere(function (Builder $team) use ($teamId) {
                    $team->where('visibility', 'team')->where('team_id', $teamId);
                })->orWhere(function (Builder $project) use ($teamId) {
                    $project->where('visibility', 'project')->where('project_id', $teamId);
                });
            }

            $q->orWhereHas('shares', function (Builder $share) use ($personId, $user, $teamIds, $modelClass) {
                $share->where('shareable_type', $modelClass)
                    ->where(function (Builder $shareTarget) use ($personId, $user, $teamIds) {
                        if ($personId) {
                            $shareTarget->where('person_id', $personId);
                        }

                        $shareTarget->orWhere('email', $user->email);

                        if ($teamIds->isNotEmpty()) {
                            $shareTarget->orWhereIn('team_id', $teamIds);
                        }
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
        if ($item instanceof AppCalendarEvent) {
            if ($item->project_id) {
                return $this->canManageProjectCalendar((int) $item->project_id);
            }

            return (int) $item->owner_user_id === (int) Auth::id();
        }

        if ($item instanceof AppCalendar) {
            if ($item->kind === 'project' && $item->project_id) {
                return $this->canManageProjectCalendar((int) $item->project_id);
            }

            return (int) $item->owner_user_id === (int) Auth::id();
        }

        if ((int) $item->owner_user_id === (int) Auth::id()) {
            return true;
        }

        if ($item instanceof AppFile) {
            return $this->effectiveSharePermission($item) === 'manage';
        }

        return false;
    }

    private function visibleCalendarEvents(Builder $query, User $user, $projectIds): Builder
    {
        $canViewAll = $user->can('apps.calendar.project.view.all');
        $isAdministrator = $user->hasRole('Administrator');

        return $query->where(function (Builder $visible) use ($user, $projectIds, $canViewAll, $isAdministrator) {
            $visible->where('owner_user_id', $user->id)
                ->orWhere('visibility', 'all')
                ->orWhereHas('attendees', fn (Builder $attendees) => $attendees->where('user_id', $user->id));

            if ($isAdministrator) {
                $visible->orWhereNotNull('project_id');
            } elseif ($projectIds->isNotEmpty()) {
                $visible->orWhere(function (Builder $project) use ($projectIds) {
                    $project->where('audience', 'project')->whereIn('project_id', $projectIds);
                });

                if ($canViewAll) {
                    $visible->orWhereIn('project_id', $projectIds);
                }
            }

            $visible->orWhereHas('shares', function (Builder $share) use ($user, $projectIds) {
                $share->where('shareable_type', AppCalendarEvent::class)
                    ->where(function (Builder $target) use ($user, $projectIds) {
                        if ($user->person_id) {
                            $target->where('person_id', $user->person_id);
                        }
                        $target->orWhere('email', $user->email);
                        if ($projectIds->isNotEmpty()) {
                            $target->orWhereIn('team_id', $projectIds);
                        }
                    });
            });
        });
    }

    private function visibleCalendars(Builder $query, User $user, $projectIds): Builder
    {
        $canViewAll = $user->can('apps.calendar.project.view.all');
        $isAdministrator = $user->hasRole('Administrator');

        return $query->where(function (Builder $visible) use ($user, $projectIds, $canViewAll, $isAdministrator) {
            $visible->where(function (Builder $personal) use ($user) {
                $personal->where('kind', 'personal')->where('owner_user_id', $user->id);
            });

            if ($isAdministrator) {
                $visible->orWhere('kind', 'project');
            } elseif ($projectIds->isNotEmpty()) {
                $visible->orWhere(function (Builder $project) use ($user, $projectIds, $canViewAll) {
                    $project->where('kind', 'project')->whereIn('project_id', $projectIds);

                    if (! $canViewAll) {
                        $project->where(function (Builder $allowed) use ($user) {
                            $allowed->whereHas('events.attendees', fn (Builder $attendees) => $attendees->where('user_id', $user->id))
                                ->orWhereHas('events', fn (Builder $events) => $events->where('audience', 'project'));
                        });
                    }
                });
            }
        });
    }

    private function canManageProjectCalendar(int $projectId): bool
    {
        $user = Auth::user();

        if (! $user->can('apps.calendar.project.manage')) {
            return false;
        }

        return $this->calendarProjectIds($user)->contains($projectId);
    }

    private function canOwn($item): bool
    {
        return (int) $item->owner_user_id === (int) Auth::id();
    }

    private function canWriteFile(AppFile $file): bool
    {
        if ($this->canOwn($file)) {
            return true;
        }

        return in_array($this->effectiveSharePermission($file), ['edit', 'manage'], true);
    }

    private function effectiveSharePermission(AppFile $file): ?string
    {
        $user = Auth::user();
        $teamIds = $this->userTeamIds($user);
        $rank = ['view' => 1, 'edit' => 2, 'manage' => 3];
        $best = null;

        $shares = $file->relationLoaded('shares') ? $file->shares : $file->shares()->get();

        foreach ($shares as $share) {
            $matchesPerson = $user->person_id && (int) $share->person_id === (int) $user->person_id;
            $matchesEmail = $share->email && strcasecmp((string) $share->email, (string) $user->email) === 0;
            $matchesTeam = $share->team_id && $teamIds->contains((int) $share->team_id);

            if (! $matchesPerson && ! $matchesEmail && ! $matchesTeam) {
                continue;
            }

            if (($rank[$share->permission] ?? 0) > ($rank[$best] ?? 0)) {
                $best = $share->permission;
            }
        }

        return $best;
    }

    private function canWorkOnTask(AppTask $task): bool
    {
        $user = Auth::user();

        if ($this->canManage($task)) {
            return true;
        }

        if ($user->person_id && (int) $task->assignee_person_id === (int) $user->person_id) {
            return true;
        }

        return $task->shares()
            ->whereIn('permission', ['edit', 'manage'])
            ->where(function (Builder $share) use ($user) {
                if ($user->person_id) {
                    $share->where('person_id', $user->person_id);
                }

                $share->orWhere('email', $user->email);

                $teamIds = $this->userTeamIds($user);
                if ($teamIds->isNotEmpty()) {
                    $share->orWhereIn('team_id', $teamIds);
                }
            })
            ->exists();
    }

    private function ensureUsableParent(?int $parentId): void
    {
        if (!$parentId) {
            return;
        }

        $parent = $this->visible(AppFile::query(), AppFile::class)->with('shares')->whereKey($parentId)->first();

        abort_unless($parent && $parent->type === 'folder' && $this->canWriteFile($parent), 403);
    }

    private function isDescendantFolder(int $parentId, AppFile $folder): bool
    {
        while ($parentId) {
            if ($parentId === (int) $folder->id) {
                return true;
            }

            $parentId = (int) (AppFile::whereKey($parentId)->value('parent_id') ?: 0);
        }

        return false;
    }

    private function filePayload(AppFile $file): array
    {
        $file->loadMissing(['owner:id,username,email', 'shares.person:id,vorname,nachname,typ', 'shares.team:id,name']);

        $payload = $file->toArray();
        $canOwn = $this->canOwn($file);
        $canWrite = $this->canWriteFile($file);
        $canManage = $this->canManage($file);

        $payload['can'] = [
            'own' => $canOwn,
            'write' => $canWrite,
            'manage' => $canManage,
            'share' => $canManage,
            'delete' => $canManage,
            'transfer_owner' => $canOwn,
        ];
        $payload['effective_permission'] = $canOwn ? 'owner' : $this->effectiveSharePermission($file);
        $payload['shares'] = $canManage
            ? $file->shares->map(fn (AppShare $share) => $this->sharePayload($share))->values()
            : [];

        return $payload;
    }

    private function sharePayload(AppShare $share): array
    {
        $type = 'email';
        $label = $share->email;
        $detail = null;
        $targetId = null;

        if ($share->person_id) {
            $type = 'person';
            $targetId = $share->person_id;
            $label = trim(($share->person?->vorname ?? '') . ' ' . ($share->person?->nachname ?? ''));
            $detail = $share->person?->typ === 'teilnehmer' ? 'Teilnehmer' : 'Person';
        } elseif ($share->team_id) {
            $type = 'team';
            $targetId = $share->team_id;
            $label = $share->team?->name ?: 'Team #' . $share->team_id;
            $detail = 'Team';
        }

        return [
            'id' => $share->id,
            'target_type' => $type,
            'target_id' => $targetId,
            'target_label' => $label ?: 'Unbekannt',
            'target_detail' => $detail,
            'permission' => $share->permission,
            'permission_label' => $this->sharePermissionLabel($share->permission),
            'message' => $share->message,
            'sent_at' => $share->sent_at?->toDateTimeString(),
        ];
    }

    private function sharePermissionLabel(?string $permission): string
    {
        return match ($permission) {
            'manage' => 'Alles',
            'edit' => 'Schreiben',
            default => 'Lesen',
        };
    }

    private function fileBreadcrumbs(?AppFile $folder): array
    {
        $breadcrumbs = [];

        while ($folder) {
            array_unshift($breadcrumbs, [
                'id' => $folder->id,
                'name' => $folder->name,
            ]);

            $folder = $folder->parent;
        }

        return $breadcrumbs;
    }

    private function fileStats(?int $parentId): array
    {
        $items = $this->visible(AppFile::query(), AppFile::class)
            ->where('parent_id', $parentId)
            ->selectRaw("count(*) as total")
            ->selectRaw("sum(type = 'folder') as folders")
            ->selectRaw("sum(type = 'file') as files")
            ->selectRaw("coalesce(sum(size), 0) as size")
            ->first();

        return [
            'total' => (int) ($items->total ?? 0),
            'folders' => (int) ($items->folders ?? 0),
            'files' => (int) ($items->files ?? 0),
            'size' => (int) ($items->size ?? 0),
        ];
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
            'workflow' => AppTaskWorkflowTemplate::class,
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
