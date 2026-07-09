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
use App\Models\AppTaskWorkflowTemplate;
use App\Models\Personen;
use App\Models\Projekt;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
        $currentFolder = $parentId ? $this->visible(AppFile::query(), AppFile::class)->whereKey($parentId)->firstOrFail() : null;

        $query = $this->visible(AppFile::query(), AppFile::class)
            ->where('parent_id', $parentId)
            ->with(['owner:id,username,email', 'shares.person:id,vorname,nachname']);

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
            'items' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'prev_page_url' => $items->previousPageUrl(),
                'next_page_url' => $items->nextPageUrl(),
            ],
            'currentFolder' => $currentFolder,
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

        $this->ensureUsableParent($data['parent_id'] ?? null);

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

    public function updateFile(Request $request, AppFile $file)
    {
        abort_unless($this->canManage($file), 403);

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

        $event = AppCalendarEvent::create($this->calendarEventPayload($data));

        return $this->calendarEventResponse($event, 'Termin wurde angelegt.');
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

        return $this->calendarEventResponse($event, 'Termin wurde aktualisiert.');
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
                ->with(['owner:id,username,email', 'assignee:id,vorname,nachname', 'workflowTemplate:id,name', 'shares.person:id,vorname,nachname'])
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

        $task->update($this->taskPayload($request->validate($this->taskRules()), $task));

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
            ->with(['owner:id,username,email', 'calendar:id,name,background_color,text_color,project_id', 'shares.person:id,vorname,nachname'])
            ->whereDate('starts_at', '<=', $year . '-12-31')
            ->where(function (Builder $q) use ($year) {
                $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $year . '-01-01');
            })
            ->orderBy('starts_at')
            ->get();
    }

    private function calendarEventResponse(AppCalendarEvent $event, string $message)
    {
        $event->load(['owner:id,username,email', 'calendar:id,name,background_color,text_color,project_id', 'shares.person:id,vorname,nachname']);

        return response()->json([
            'success' => true,
            'event' => $event,
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
            ->where('permission', 'edit')
            ->where(function (Builder $share) use ($user) {
                if ($user->person_id) {
                    $share->where('person_id', $user->person_id);
                }

                $share->orWhere('email', $user->email);
            })
            ->exists();
    }

    private function ensureUsableParent(?int $parentId): void
    {
        if (!$parentId) {
            return;
        }

        $parent = $this->visible(AppFile::query(), AppFile::class)->whereKey($parentId)->first();

        abort_unless($parent && $parent->type === 'folder' && $this->canManage($parent), 403);
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
