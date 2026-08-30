<?php

namespace App\Http\Controllers;

use App\Models\Anwesenheitsstatuten;
use App\Models\AppCalendar;
use App\Models\AppCalendarEvent;
use App\Models\AppCalendarEventAttendee;
use App\Models\BopPhaseParticipant;
use App\Models\BopPhaseSchedule;
use App\Models\BopRun;
use App\Models\BopTimetable;
use App\Models\EinteilungSetting;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Partner;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\Tage;
use App\Models\User;
use App\Models\Zeiten;
use App\Notifications\ConfiguredEventNotification;
use App\Services\Projects\ActiveProjectContext;
use App\Services\Scheduling\AreaRotationScheduleGenerator;
use App\Services\Scheduling\BopTimetableSpreadsheetExporter;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BopRunController extends Controller
{
    private const PHASES = [
        'pa_preparation',
        'pa',
        'pa_feedback',
        'roll_day',
        'workshop_days',
        'wt_feedback',
    ];

    public function __construct(private readonly ActiveProjectContext $activeProjectContext) {}

    public function show(Request $request, Partner $partner)
    {
        $context = $request->validate([
            'schuljahr' => ['required', 'string', 'max:20'],
            'teil' => ['required', 'string', 'max:40'],
        ]);
        $schuljahr = $context['schuljahr'];
        $teil = $context['teil'];
        $project = $this->bopProject($request, $partner);
        $students = $this->students($partner, $schuljahr, $teil);
        $run = BopRun::with(['phases.participants', 'phases.timetables.entries'])
            ->where('projekt_id', $project->id)
            ->where('partner_id', $partner->id)
            ->forSchuljahr($schuljahr)
            ->where('teil', $teil)
            ->first() ?? ($teil !== '_all' ? BopRun::with(['phases.participants', 'phases.timetables.entries'])
            ->where('projekt_id', $project->id)
            ->where('partner_id', $partner->id)
            ->forSchuljahr($schuljahr)
            ->where('teil', '_all')
            ->first() : null);

        return response()->json($this->payload($project, $partner, $schuljahr, $teil, $students, $run));
    }

    public function update(Request $request, Partner $partner)
    {
        $context = $request->validate([
            'schuljahr' => ['required', 'string', 'max:20'],
            'teil' => ['required', 'string', 'max:40'],
            'original_schuljahr' => ['nullable', 'string', 'max:20'],
        ]);
        $schuljahr = $context['schuljahr'];
        $teil = $context['teil'];
        $originalSchuljahr = $context['original_schuljahr'] ?? null;
        $project = $this->bopProject($request, $partner);
        $students = $this->students($partner, $schuljahr, $teil);
        $data = $request->validate([
            'school_type' => ['required', Rule::in(['Gemeinschaftsschule', 'Förderschule'])],
            'status' => ['nullable', Rule::in(['planning', 'confirmed', 'completed'])],
            'planned_classes' => ['nullable', 'array', 'max:100'],
            'planned_classes.*.name' => ['required', 'string', 'max:50'],
            'planned_classes.*.expected_participants' => ['nullable', 'integer', 'min:0', 'max:500'],
            'planned_classes.*.part' => ['required', 'string', 'max:40'],
            'parts' => ['nullable', 'array', 'min:1', 'max:20'],
            'parts.*' => ['string', 'max:40'],
            'phases' => ['required', 'array', 'size:6'],
            'phases.*.phase_type' => ['required', Rule::in(self::PHASES), 'distinct'],
            'phases.*.dates' => ['nullable', 'array', 'max:60'],
            'phases.*.dates.*' => ['date_format:Y-m-d'],
            'phases.*.scope_type' => ['required', Rule::in(['school', 'classes', 'participants'])],
            'phases.*.selected_classes' => ['nullable', 'array'],
            'phases.*.selected_classes.*' => ['string', 'max:50'],
            'phases.*.days_per_class' => ['nullable', 'integer', 'min:1', 'max:20'],
            'phases.*.class_date_assignments' => ['nullable', 'array'],
            'phases.*.part_date_assignments' => ['nullable', 'array'],
            'phases.*.participant_ids' => ['nullable', 'array'],
            'phases.*.participant_ids.*' => ['integer'],
            'phases.*.group_mode' => ['required', Rule::in(['none', 'school', 'class', 'balanced', 'existing_assignment'])],
            'phases.*.group_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'phases.*.supervisor_person_id' => ['nullable', 'integer', 'exists:personens,id'],
            'phases.*.bereich_id' => ['nullable', 'integer', 'exists:bereiches,id'],
            'phases.*.raum_id' => ['nullable', 'integer', 'exists:raeumes,id'],
            'phases.*.start_time' => ['nullable', 'date_format:H:i'],
            'phases.*.end_time' => ['nullable', 'date_format:H:i', 'after:phases.*.start_time'],
            'phases.*.generate_groups' => ['nullable', 'boolean'],
            'phases.*.publish_to_calendar' => ['nullable', 'boolean'],
            'phases.*.notes' => ['nullable', 'string', 'max:3000'],
        ]);

        if (! $project->rule('participant_parts_enabled', false)) {
            $data['parts'] = ['1'];
            $data['planned_classes'] = collect($data['planned_classes'] ?? [])
                ->map(fn ($class) => [...$class, 'part' => '1'])
                ->all();
            $data['phases'] = collect($data['phases'])
                ->map(function ($phase) {
                    $phase['part_date_assignments'] = ($phase['phase_type'] ?? null) === 'workshop_days'
                        ? ['1' => array_values($phase['dates'] ?? [])]
                        : [];

                    return $phase;
                })->all();
        }

        $knownStudentIds = $students->pluck('id')->map(fn ($id) => (int) $id);
        $parts = collect($data['parts'] ?? ['1'])->map(fn ($part) => trim((string) $part))->filter()->unique()->values();
        $unknownPlannedParts = collect($data['planned_classes'] ?? [])->pluck('part')->map(fn ($part) => trim((string) $part))->diff($parts);
        if ($unknownPlannedParts->isNotEmpty()) {
            throw ValidationException::withMessages(['planned_classes' => 'Mindestens eine Klasse ist einem unbekannten Teil zugeordnet.']);
        }

        $sourceRun = $originalSchuljahr ? BopRun::query()
            ->where('projekt_id', $project->id)
            ->where('partner_id', $partner->id)
            ->forSchuljahr($originalSchuljahr)
            ->where('teil', $teil)
            ->first() : null;
        if ($sourceRun && $originalSchuljahr !== $schuljahr && BopRun::query()
            ->where('projekt_id', $project->id)
            ->where('partner_id', $partner->id)
            ->forSchuljahr($schuljahr)
            ->where('teil', $teil)
            ->where('id', '!=', $sourceRun->id)->exists()) {
            throw ValidationException::withMessages([
                'schuljahr' => "Für das Schuljahr {$schuljahr} existiert bereits eine Planung. Bitte diese zuerst öffnen oder löschen.",
            ]);
        }

        $run = DB::transaction(function () use ($data, $parts, $project, $partner, $schuljahr, $teil, $students, $knownStudentIds, $sourceRun) {
            $run = $sourceRun ?: BopRun::firstOrNew([
                'projekt_id' => $project->id,
                'partner_id' => $partner->id,
                'schuljahr' => $schuljahr,
                'teil' => $teil,
            ]);
            if (! $run->exists) {
                $run->created_by_user_id = Auth::id();
            }
            $run->schuljahr = $schuljahr;
            $run->fill([
                'school_type' => $data['school_type'],
                'parts' => $parts->all(),
                'planned_classes' => collect($data['planned_classes'] ?? [])
                    ->map(fn ($class) => [
                        'name' => trim((string) $class['name']),
                        'expected_participants' => (int) ($class['expected_participants'] ?? 0),
                        'part' => trim((string) ($class['part'] ?? '1')),
                    ])->filter(fn ($class) => $class['name'] !== '')->unique('name')->values()->all(),
                'status' => $data['status'] ?? 'planning',
                'updated_by_user_id' => Auth::id(),
            ])->save();

            foreach ($data['phases'] as $phaseData) {
                $participantIds = $this->resolveParticipantIds($phaseData, $students);
                if ($participantIds->diff($knownStudentIds)->isNotEmpty()) {
                    throw ValidationException::withMessages(['phases' => 'Mindestens ein Teilnehmer gehoert nicht zu dieser Schule, diesem Schuljahr und Teil.']);
                }

                $dates = collect($phaseData['dates'] ?? [])->filter()->unique()->sort()->values()->all();
                $classDateAssignments = $this->validatedClassDateAssignments(
                    $phaseData,
                    $dates,
                    ($data['status'] ?? 'planning') === 'confirmed'
                );
                $partDateAssignments = $this->validatedPartDateAssignments(
                    $phaseData, $dates, $parts, ($data['status'] ?? 'planning') === 'confirmed'
                );
                $phase = BopPhaseSchedule::updateOrCreate(
                    ['bop_run_id' => $run->id, 'phase_type' => $phaseData['phase_type']],
                    [
                        'dates' => $dates,
                        'scope_type' => $phaseData['scope_type'],
                        'selected_classes' => array_values($phaseData['selected_classes'] ?? []),
                        'days_per_class' => (int) ($phaseData['days_per_class'] ?? 2),
                        'class_date_assignments' => $classDateAssignments,
                        'part_date_assignments' => $partDateAssignments,
                        'group_mode' => $phaseData['group_mode'],
                        'group_count' => $phaseData['group_count'] ?? null,
                        'supervisor_person_id' => $phaseData['supervisor_person_id'] ?? null,
                        'bereich_id' => $phaseData['bereich_id'] ?? null,
                        'raum_id' => $phaseData['raum_id'] ?? null,
                        'start_time' => $phaseData['start_time'] ?? '08:00',
                        'end_time' => $phaseData['end_time'] ?? '16:00',
                        'generate_groups' => (bool) ($phaseData['generate_groups'] ?? false),
                        'publish_to_calendar' => (bool) ($phaseData['publish_to_calendar'] ?? false),
                        'einteilung_setting_id' => $phaseData['phase_type'] === 'workshop_days'
                            ? EinteilungSetting::where([
                                'projekt_id' => $project->id,
                                'partner_id' => $partner->id,
                                'schuljahr' => $schuljahr,
                                'teil' => $teil,
                            ])->value('id')
                            : null,
                        'notes' => $phaseData['notes'] ?? null,
                    ]
                );

                if ($dates === []) {
                    $phase->timetables()->delete();
                } else {
                    $phase->timetables()->whereNotIn('schedule_date', $dates)->delete();
                }

                $this->syncParticipants($phase, $students->whereIn('id', $participantIds)->values());

                if ($phase->generate_groups && ! in_array($phase->group_mode, ['none', 'existing_assignment'], true) && $dates !== []) {
                    $this->syncGeneratedGroups($phase, $project, $partner);
                }

                $this->syncCalendarProjection($phase, $project, $partner);
            }

            $allDates = $run->phases()->get()->flatMap(fn (BopPhaseSchedule $phase) => $phase->dates ?? [])->filter()->sort()->values();
            $run->update([
                'first_visit_date' => $allDates->first(),
                'last_visit_date' => $allDates->last(),
                'updated_by_user_id' => Auth::id(),
            ]);

            return $run;
        });

        $run->load(['phases.participants', 'phases.timetables.entries']);

        return response()->json([
            'message' => 'BOP-Ablauf, Termine, Teilnehmer und Gruppen wurden gespeichert.',
            'previous_schuljahr' => $sourceRun && $originalSchuljahr !== $schuljahr ? $originalSchuljahr : null,
            ...$this->payload($project, $partner, $schuljahr, $teil, $students, $run),
        ]);
    }

    public function updateParticipantStatus(Request $request, BopPhaseParticipant $participant)
    {
        $participant->load('phase.run.partner');
        $this->bopProject($request, $participant->phase->run->partner);
        $data = $request->validate([
            'completion_status' => ['required', Rule::in(['planned', 'completed', 'excused', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $participant->update([
            ...$data,
            'completed_at' => $data['completion_status'] === 'completed' ? now() : null,
        ]);

        return response()->json(['message' => 'Teilnehmerstatus wurde gespeichert.', 'participant' => $participant->fresh()]);
    }

    public function reset(Request $request, Partner $partner)
    {
        $data = $request->validate([
            'schuljahr' => ['required', 'string', 'max:20'],
            'teil' => ['nullable', 'string', 'max:40'],
            'mode' => ['required', Rule::in(['dates', 'full'])],
        ]);
        $project = $this->bopProject($request, $partner);
        $teil = $data['teil'] ?? '_all';
        $run = BopRun::with('phases')
            ->where('projekt_id', $project->id)
            ->where('partner_id', $partner->id)
            ->forSchuljahr($data['schuljahr'])
            ->where('teil', $teil)
            ->firstOrFail();

        DB::transaction(function () use ($run, $data) {
            foreach ($run->phases as $phase) {
                AppCalendarEvent::where('source_type', BopPhaseSchedule::class)->where('source_id', $phase->id)->delete();
                if ($data['mode'] === 'dates') {
                    $phase->timetables()->delete();
                    $phase->update([
                        'dates' => [], 'class_date_assignments' => [], 'part_date_assignments' => [],
                        'publish_to_calendar' => false, 'calendar_event_id' => null, 'generate_groups' => false,
                    ]);
                }
            }

            if ($data['mode'] === 'full') {
                $run->delete();
            } else {
                $run->update([
                    'first_visit_date' => null, 'last_visit_date' => null,
                    'status' => 'planning', 'updated_by_user_id' => Auth::id(),
                ]);
            }
        });

        $students = $this->students($partner, $data['schuljahr'], $teil);
        $freshRun = $data['mode'] === 'full' ? null : BopRun::with(['phases.participants', 'phases.timetables.entries'])->find($run->id);

        return response()->json([
            ...$this->payload($project, $partner, $data['schuljahr'], $teil, $students, $freshRun),
            'reset' => true, 'reset_mode' => $data['mode'],
            'message' => $data['mode'] === 'full' ? 'Die gesamte BOP-Planung wurde zurückgesetzt.' : 'Alle Planungstermine wurden zurückgesetzt.',
        ]);
    }

    public function generateGroups(Request $request, Partner $partner, string $phaseType)
    {
        abort_unless(in_array($phaseType, self::PHASES, true), 404);
        $context = $request->validate([
            'schuljahr' => ['required', 'string', 'max:20'],
            'teil' => ['required', 'string', 'max:40'],
        ]);
        $project = $this->bopProject($request, $partner);
        $run = BopRun::query()
            ->where('projekt_id', $project->id)
            ->where('partner_id', $partner->id)
            ->forSchuljahr($context['schuljahr'])
            ->whereIn('teil', [$context['teil'], '_all'])
            ->orderByRaw('CASE WHEN teil = ? THEN 0 ELSE 1 END', [$context['teil']])
            ->firstOrFail();
        $phase = $run->phases()->where('phase_type', $phaseType)->firstOrFail();

        if (empty($phase->dates) || in_array($phase->group_mode, ['none', 'existing_assignment'], true)) {
            throw ValidationException::withMessages([
                'phase' => 'Bitte zuerst Termine und eine Gruppenstrategie im BOP-Ablauf speichern.',
            ]);
        }

        DB::transaction(function () use ($phase, $project, $partner) {
            $phase->update(['generate_groups' => true]);
            $this->syncGeneratedGroups($phase, $project, $partner);
        });

        return response()->json([
            'message' => 'Die Anwesenheitsgruppen wurden aus dem gespeicherten BOP-Ablauf erzeugt.',
            'groups' => $phase->groups()->count(),
        ]);
    }

    public function generateTimetable(
        Request $request,
        Partner $partner,
        AreaRotationScheduleGenerator $generator
    ) {
        $context = $request->validate([
            'schuljahr' => ['required', 'string', 'max:20'],
            'teil' => ['required', 'string', 'max:40'],
            'schedule_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', Rule::in([1, 5, 10, 15, 30])],
            'groups' => ['required', 'array', 'min:1', 'max:50'],
            'groups.*' => ['required', 'string', 'max:80', 'distinct'],
            'areas' => ['required', 'array', 'min:1', 'max:50'],
            'areas.*.bereich_id' => ['required', 'integer', 'distinct', 'exists:bereiches,id'],
            'areas.*.duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'areas.*.supervisor_person_id' => ['nullable', 'integer', 'exists:personens,id'],
            'events' => ['nullable', 'array', 'max:30'],
            'events.*.title' => ['required', 'string', 'max:150'],
            'events.*.type' => ['required', Rule::in(['shared', 'break', 'extra'])],
            'events.*.group_scope' => ['nullable', Rule::in(['all', 'first_half', 'second_half'])],
            'events.*.start_time' => ['required', 'date_format:H:i'],
            'events.*.end_time' => ['required', 'date_format:H:i'],
            'area_orders' => ['nullable', 'array'],
            'area_orders.*' => ['array'],
            'area_orders.*.*' => ['integer'],
            'persist' => ['nullable', 'boolean'],
        ]);

        $project = $this->bopProject($request, $partner);
        $run = BopRun::query()
            ->where('projekt_id', $project->id)
            ->where('partner_id', $partner->id)
            ->forSchuljahr($context['schuljahr'])
            ->whereIn('teil', [$context['teil'], '_all'])
            ->orderByRaw('CASE WHEN teil = ? THEN 0 ELSE 1 END', [$context['teil']])
            ->first();

        $areaLookup = $project->bereiche->keyBy('id');
        $unknownAreaIds = collect($context['areas'])->pluck('bereich_id')->map(fn ($id) => (int) $id)->diff($areaLookup->keys());
        if ($unknownAreaIds->isNotEmpty()) {
            throw ValidationException::withMessages(['areas' => 'Mindestens ein Bereich gehört nicht zum aktiven BOP-Projekt.']);
        }

        $supervisorLookup = $project->mitarbeiter->keyBy('id');
        $unknownSupervisorIds = collect($context['areas'])->pluck('supervisor_person_id')->filter()
            ->map(fn ($id) => (int) $id)->diff($supervisorLookup->keys());
        if ($unknownSupervisorIds->isNotEmpty()) {
            throw ValidationException::withMessages(['areas' => 'Mindestens ein Anleiter gehört nicht zum aktiven BOP-Projekt.']);
        }

        $generatorInput = [
            ...$context,
            'areas' => collect($context['areas'])->map(function (array $area) use ($areaLookup, $supervisorLookup) {
                $supervisorId = $area['supervisor_person_id'] ?? null;
                $supervisor = $supervisorId ? $supervisorLookup->get((int) $supervisorId) : null;

                return [
                    ...$area,
                    'name' => $areaLookup->get((int) $area['bereich_id'])?->name,
                    'supervisor_name' => $supervisor
                        ? trim(($supervisor->vorname ?? '').' '.($supervisor->nachname ?? '')) : null,
                ];
            })->values()->all(),
            'events' => array_values($context['events'] ?? []),
        ];

        try {
            $generated = $generator->generate($generatorInput);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['timetable' => $exception->getMessage()]);
        }

        $breakDefaults = collect($generated['config']['events'] ?? [])
            ->where('type', 'break')
            ->map(fn (array $event) => [
                'title' => $event['title'],
                'type' => 'break',
                'group_scope' => $event['group_scope'] ?? 'all',
                'start_time' => $event['start_time'],
                'end_time' => $event['end_time'],
            ])->values()->all();

        if (! ($context['persist'] ?? false)) {
            return response()->json([
                'message' => 'Die Zeitplanvorschau wurde konfliktfrei erzeugt.',
                'persisted' => false,
                'timetable' => $generated,
            ]);
        }

        $timetable = DB::transaction(function () use ($run, $project, $partner, $context, $generated, $breakDefaults) {
            $run ??= BopRun::query()->create([
                'projekt_id' => $project->id,
                'partner_id' => $partner->id,
                'schuljahr' => $context['schuljahr'],
                'teil' => $context['teil'],
                'school_type' => 'Gemeinschaftsschule',
                'parts' => [$context['teil'] === '_all'
                    ? '1'
                    : trim((string) preg_replace('/^Teil\s*/i', '', $context['teil']))],
                'first_visit_date' => $context['schedule_date'],
                'last_visit_date' => $context['schedule_date'],
                'status' => 'planning',
                'created_by_user_id' => Auth::id(),
                'updated_by_user_id' => Auth::id(),
            ]);
            $phase = $run->phases()->firstOrCreate(
                ['phase_type' => 'workshop_days'],
                [
                    'dates' => [],
                    'scope_type' => 'school',
                    'selected_classes' => [],
                    'part_date_assignments' => [],
                    'group_mode' => 'existing_assignment',
                    'start_time' => $context['start_time'],
                    'end_time' => $context['end_time'],
                    'generate_groups' => false,
                    'publish_to_calendar' => false,
                ]
            );
            $dates = collect($phase->dates ?? [])->push($context['schedule_date'])->filter()->unique()->sort()->values();
            $phase->update(['dates' => $dates->all()]);
            $allDates = $run->phases()->get()->flatMap(fn (BopPhaseSchedule $item) => $item->dates ?? [])->filter()->sort()->values();
            $run->update([
                'first_visit_date' => $allDates->first(),
                'last_visit_date' => $allDates->last(),
                'break_defaults' => $breakDefaults,
                'updated_by_user_id' => Auth::id(),
            ]);

            $timetable = BopTimetable::updateOrCreate(
                [
                    'bop_phase_schedule_id' => $phase->id,
                    'schedule_date' => $context['schedule_date'],
                ],
                [
                    'slot_minutes' => $generated['slot_minutes'],
                    'config' => $generated['config'],
                    'generated_by_user_id' => Auth::id(),
                ]
            );
            $timetable->entries()->delete();
            $timetable->entries()->createMany($generated['entries']);

            return $timetable->fresh('entries');
        });

        return response()->json([
            'message' => 'Der Zeitplan wurde konfliktfrei erzeugt und gespeichert.',
            'persisted' => true,
            'timetable' => $timetable,
            'break_defaults' => $breakDefaults,
        ]);
    }

    public function exportTimetableExcel(
        Request $request,
        Partner $partner,
        BopTimetableSpreadsheetExporter $exporter
    ) {
        $data = $request->validate([
            'schedule_date' => ['required', 'date_format:Y-m-d'],
            'config' => ['required', 'array'],
            'config.start_time' => ['required', 'date_format:H:i'],
            'config.end_time' => ['required', 'date_format:H:i', 'after:config.start_time'],
            'config.groups' => ['required', 'array', 'min:1', 'max:50'],
            'config.groups.*' => ['required', 'string', 'max:80', 'distinct'],
            'entries' => ['required', 'array', 'max:5000'],
            'entries.*.group_key' => ['nullable', 'string', 'max:80'],
            'entries.*.type' => ['required', Rule::in(['shared', 'break', 'extra', 'area'])],
            'entries.*.title' => ['required', 'string', 'max:150'],
            'entries.*.start_time' => ['required', 'date_format:H:i,H:i:s'],
            'entries.*.end_time' => ['required', 'date_format:H:i,H:i:s'],
            'entries.*.bereich_id' => ['nullable', 'integer'],
            'entries.*.meta' => ['nullable', 'array'],
            'entries.*.meta.group_labels' => ['nullable', 'array'],
            'entries.*.meta.group_labels.*' => ['string', 'max:80'],
            'entries.*.meta.supervisor_name' => ['nullable', 'string', 'max:150'],
        ]);
        $this->bopProject($request, $partner);
        $path = $exporter->create($data, $partner->name);
        $safeSchoolName = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $partner->name) ?: 'Schule';
        $filename = "Zeitplan_{$safeSchoolName}_{$data['schedule_date']}.xlsx";

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function bopProject(Request $request, Partner $partner): Projekt
    {
        $project = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($project && str_contains(mb_strtoupper($project->name), 'BOP'), 404, 'Diese Funktion ist nur im Projekt BOP verfuegbar.');
        abort_unless($project->partners()->whereKey($partner->id)->exists(), 404);

        return $project->loadMissing(['bereiche:id,name', 'raeume:id,name', 'mitarbeiter:id,vorname,nachname']);
    }

    private function students(Partner $partner, string $schuljahr, string $teil): Collection
    {
        return PersonenIstSchueler::with('person:id,vorname,nachname')
            ->where('schule_id', $partner->id)
            ->forSchuljahr($schuljahr)
            ->when($teil !== '_all', fn (Builder $query) => $query->where('teil', $teil))
            ->whereHas('person', fn (Builder $query) => $query->where('aktiv', true))
            ->get()
            ->sortBy(fn (PersonenIstSchueler $student) => sprintf('%s|%s|%s', $student->klasse, $student->person?->nachname, $student->person?->vorname), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function resolveParticipantIds(array $phase, Collection $students): Collection
    {
        return (match ($phase['scope_type']) {
            'classes' => $students->whereIn('klasse', $phase['selected_classes'] ?? [])->pluck('id'),
            'participants' => collect($phase['participant_ids'] ?? [])->map(fn ($id) => (int) $id),
            default => $students->pluck('id'),
        })->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function syncParticipants(BopPhaseSchedule $phase, Collection $students): void
    {
        $studentIds = $students->pluck('id');
        $phase->participants()->whereNotIn('personen_ist_schueler_id', $studentIds)->delete();
        $groupCount = max(1, (int) ($phase->group_count ?: 1));

        foreach ($students->values() as $index => $student) {
            $groupKey = match ($phase->group_mode) {
                'school' => 'Gesamte Schule',
                'class' => $student->klasse ?: 'Ohne Klasse',
                'balanced' => 'Gruppe '.(($index % $groupCount) + 1),
                default => null,
            };
            BopPhaseParticipant::updateOrCreate(
                ['bop_phase_schedule_id' => $phase->id, 'personen_ist_schueler_id' => $student->id],
                ['class_name' => $student->klasse, 'group_key' => $groupKey]
            );
        }
    }

    private function syncGeneratedGroups(BopPhaseSchedule $phase, Projekt $project, Partner $partner): void
    {
        $phase->load('participants.student');
        $supervisorId = $phase->supervisor_person_id ?: Auth::user()->person_id ?: $project->mitarbeiter->first()?->id;
        $bereichId = $phase->bereich_id ?: $project->bereiche->first()?->id;
        $raumId = $phase->raum_id ?: $project->raeume->first()?->id;

        if (! $supervisorId || ! $bereichId || ! $raumId) {
            throw ValidationException::withMessages([
                'phases' => 'Fuer die Gruppengenerierung werden Betreuung, Bereich und Raum benoetigt.',
            ]);
        }

        $dates = collect($phase->dates)->sort()->values();
        $plannedTime = Zeiten::firstOrCreate(['startzeit' => $phase->start_time ?: '08:00', 'endzeit' => $phase->end_time ?: '16:00']);
        $status = Anwesenheitsstatuten::where('status', 'unentschuldigt')->first() ?: Anwesenheitsstatuten::first();
        abort_unless($status, 422, 'Es ist kein Anwesenheitsstatus eingerichtet.');

        foreach ($phase->participants->groupBy(fn ($participant) => $participant->group_key ?: 'BOP-Gruppe') as $groupKey => $participants) {
            $group = Gruppe::updateOrCreate(
                ['bop_phase_schedule_id' => $phase->id, 'bemerkung' => 'BOP: '.$this->phaseLabel($phase->phase_type).' · '.$groupKey],
                [
                    'personen_id' => $supervisorId,
                    'bereich_id' => $bereichId,
                    'projekt_id' => $project->id,
                    'partner_id' => $partner->id,
                    'raum_id' => $raumId,
                    'anfangsdatum' => $dates->first(),
                    'enddatum' => $dates->last(),
                    'startzeit' => $phase->start_time ?: '08:00',
                    'endzeit' => $phase->end_time ?: '16:00',
                ]
            );

            $groupDates = $dates;
            if ($phase->phase_type === 'workshop_days' && ! empty($phase->part_date_assignments)) {
                $className = $participants->pluck('class_name')->filter()->unique()->first();
                $plannedClass = collect($phase->run->planned_classes ?? [])->firstWhere('name', $className);
                $part = (string) ($plannedClass['part'] ?? '1');
                $assignedDates = collect($phase->part_date_assignments[$part] ?? [])->filter()->sort()->values();
                if ($assignedDates->isNotEmpty()) {
                    $groupDates = $assignedDates;
                }
            } elseif ($phase->scope_type === 'classes' && $phase->group_mode === 'class') {
                $className = $participants->pluck('class_name')->filter()->unique()->sole();
                $assignedDates = collect($phase->class_date_assignments[$className] ?? [])->filter()->sort()->values();
                if ($assignedDates->isNotEmpty()) {
                    $groupDates = $assignedDates;
                }
            }

            foreach ($groupDates as $date) {
                $day = Carbon::parse($date);
                $tag = Tage::firstOrCreate(['datum' => $date], ['wochentag' => $day->locale('de')->dayName]);
                foreach ($participants as $participant) {
                    if (! $participant->student?->person_id) {
                        continue;
                    }
                    GruppeHasPersonen::firstOrCreate(
                        ['personen_id' => $participant->student->person_id, 'gruppe_id' => $group->id, 'tage_id' => $tag->id],
                        [
                            'user_id' => Auth::id(),
                            'zeitgeplant_id' => $plannedTime->id,
                            'zeittatsaechlich_id' => null,
                            'anwesenheitsstatuten_id' => $status->id,
                            'bemerkung' => null,
                        ]
                    );
                }
            }
        }
    }

    private function syncCalendarProjection(BopPhaseSchedule $phase, Projekt $project, Partner $partner): void
    {
        $dates = collect($phase->dates ?? [])->sort()->values();
        if (! $phase->publish_to_calendar || $dates->isEmpty()) {
            if ($phase->calendar_event_id) {
                AppCalendarEvent::whereKey($phase->calendar_event_id)->delete();
                $phase->update(['calendar_event_id' => null]);
            }

            return;
        }

        $calendar = AppCalendar::firstOrCreate(
            ['project_id' => $project->id, 'kind' => 'project'],
            [
                'owner_user_id' => Auth::id(), 'name' => $project->name,
                'background_color' => '#f97316', 'text_color' => '#ffffff', 'visibility' => 'project',
            ]
        );
        $eventLookup = ['source_type' => BopPhaseSchedule::class, 'source_id' => $phase->id];
        $existingEvent = AppCalendarEvent::where($eventLookup)->first();
        $nextStart = $dates->first().' '.($phase->start_time ?: '08:00').':00';
        $nextEnd = $dates->last().' '.($phase->end_time ?: '16:00').':00';
        $materiallyChanged = ! $existingEvent
            || Carbon::parse($existingEvent->starts_at)->toDateTimeString() !== Carbon::parse($nextStart)->toDateTimeString()
            || Carbon::parse($existingEvent->ends_at ?: $existingEvent->starts_at)->toDateTimeString() !== Carbon::parse($nextEnd)->toDateTimeString();
        $colors = $this->phaseCalendarColors($phase->phase_type);
        $event = AppCalendarEvent::updateOrCreate(
            $eventLookup,
            [
                'owner_user_id' => Auth::id(), 'calendar_id' => $calendar->id, 'project_id' => $project->id,
                'title' => $this->phaseCalendarTitle($phase->phase_type, $partner->name),
                'description' => 'Optional aus dem BOP-Durchlauf uebernommen. Die BOP-Daten bleiben fuehrend.',
                'starts_at' => $nextStart,
                'ends_at' => $nextEnd,
                'all_day' => false, 'include_weekends' => false, 'excluded_dates' => [],
                'visibility' => 'project', 'audience' => 'assignees',
                'background_color' => $colors['background'], 'text_color' => $colors['text'],
            ]
        );
        $phase->update(['calendar_event_id' => $event->id]);

        $user = $phase->supervisor_person_id ? User::where('person_id', $phase->supervisor_person_id)->first() : null;
        $existingAttendee = $user ? $event->attendees()->where('user_id', $user->id)->first() : null;
        $event->attendees()->when($user, fn ($query) => $query->where('user_id', '!=', $user->id))->delete();
        if ($user && (int) $user->id !== (int) Auth::id()) {
            AppCalendarEventAttendee::updateOrCreate(
                ['event_id' => $event->id, 'user_id' => $user->id],
                [
                    'assigned_by_user_id' => Auth::id(), 'access_level' => 'responsible', 'response_required' => true,
                    'response' => ($materiallyChanged || ! $existingAttendee) ? 'pending' : $existingAttendee->response,
                    'response_note' => $materiallyChanged ? null : $existingAttendee?->response_note,
                    'responded_at' => $materiallyChanged ? null : $existingAttendee?->responded_at,
                ]
            );
            if ($materiallyChanged || ! $existingAttendee) {
                Notification::send([$user], new ConfiguredEventNotification([
                    'message' => 'Du wurdest fuer „'.$event->title.'“ eingeplant. Bitte sage im Kalender zu oder ab.',
                    'link' => route('apps.calendar', ['year' => Carbon::parse($event->starts_at)->year]),
                    'id' => $event->id, 'typ' => 'Kalender', 'event_key' => 'apps.calendar.assignment',
                ]));
            }
        }
    }

    private function payload(Projekt $project, Partner $partner, string $schuljahr, string $teil, Collection $students, ?BopRun $run): array
    {
        $phaseMap = $run?->phases?->keyBy('phase_type') ?? collect();
        $normalisePart = fn ($part) => trim((string) preg_replace('/^Teil\s*/i', '', (string) $part));
        $suggestedParts = $students->pluck('teil')->map($normalisePart)->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
        $suggestedClasses = $students
            ->filter(fn ($student) => filled($student->klasse))
            ->groupBy(fn ($student) => trim((string) $student->klasse))
            ->map(function (Collection $classStudents, string $className) use ($normalisePart) {
                $part = (string) ($classStudents->pluck('teil')
                    ->map($normalisePart)
                    ->filter()
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->first() ?: '1');

                return [
                    'name' => $className,
                    'expected_participants' => $classStudents->pluck('person_id')->filter()->unique()->count(),
                    'part' => $part,
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return [
            'run' => $run,
            'context' => ['project_id' => $project->id, 'partner_id' => $partner->id, 'school_name' => $partner->name, 'schuljahr' => $schuljahr, 'teil' => $teil],
            'school_type_suggestion' => $students->isNotEmpty() && $students->filter(fn ($student) => $student->foerderschueler || $student->foederschueler)->count() > ($students->count() / 2)
                ? 'Förderschule' : 'Gemeinschaftsschule',
            'classes' => $students->pluck('klasse')->filter()
                ->merge(collect($run?->planned_classes ?? [])->pluck('name'))
                ->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'suggested_parts' => $suggestedParts->isNotEmpty() ? $suggestedParts : collect(['1']),
            'participant_parts_enabled' => $project->rule('participant_parts_enabled', false),
            'suggested_planned_classes' => $suggestedClasses,
            'students' => $students->map(fn ($student) => [
                'id' => $student->id, 'person_id' => $student->person_id, 'class_name' => $student->klasse, 'part' => $student->teil,
                'name' => trim(($student->person?->nachname ?? '').', '.($student->person?->vorname ?? '')),
            ]),
            'phases' => collect(self::PHASES)->map(function ($type) use ($phaseMap) {
                $phase = $phaseMap->get($type);

                return $phase ? [
                    ...$phase->toArray(),
                    'participant_ids' => $phase->participants->pluck('personen_ist_schueler_id')->map(fn ($id) => (int) $id)->values(),
                ] : $this->defaultPhase($type);
            })->values(),
            'options' => [
                'areas' => $project->bereiche->map->only(['id', 'name'])->values(),
                'rooms' => $project->raeume->map->only(['id', 'name'])->values(),
                'supervisors' => $project->mitarbeiter->map(fn ($person) => ['id' => $person->id, 'name' => trim($person->vorname.' '.$person->nachname)])->values(),
            ],
        ];
    }

    private function defaultPhase(string $type): array
    {
        return [
            'phase_type' => $type, 'dates' => [], 'scope_type' => 'school', 'selected_classes' => [],
            'days_per_class' => $type === 'pa' ? 2 : 1, 'class_date_assignments' => [], 'part_date_assignments' => [],
            'participant_ids' => [], 'group_mode' => in_array($type, ['pa_feedback', 'wt_feedback'], true) ? 'none' : ($type === 'workshop_days' ? 'existing_assignment' : 'class'),
            'group_count' => 1, 'supervisor_person_id' => null, 'bereich_id' => null, 'raum_id' => null,
            'start_time' => '08:00', 'end_time' => '16:00', 'generate_groups' => false,
            'publish_to_calendar' => false, 'notes' => null,
        ];
    }

    private function validatedClassDateAssignments(array $phase, array $dates, bool $requireComplete): array
    {
        if (($phase['phase_type'] ?? null) === 'workshop_days' || ($phase['scope_type'] ?? null) !== 'classes') {
            return [];
        }

        $selectedClasses = collect($phase['selected_classes'] ?? [])->map(fn ($class) => (string) $class);
        $validDates = collect($dates);
        $daysPerClass = (int) ($phase['days_per_class'] ?? 2);
        $assignments = collect($phase['class_date_assignments'] ?? [])
            ->only($selectedClasses->all())
            ->map(fn ($assignedDates) => collect($assignedDates)->filter()->unique()->sort()->values()->all());

        foreach ($assignments as $className => $assignedDates) {
            if (collect($assignedDates)->diff($validDates)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'phases' => "Die PA-Terminzuordnung der Klasse {$className} enthält einen unbekannten Termin.",
                ]);
            }
            if (count($assignedDates) > $daysPerClass) {
                throw ValidationException::withMessages([
                    'phases' => "Für die Klasse {$className} dürfen höchstens {$daysPerClass} PA-Tage gewählt werden.",
                ]);
            }
        }

        if ($requireComplete) {
            foreach ($selectedClasses as $className) {
                if (count($assignments->get($className, [])) !== $daysPerClass) {
                    throw ValidationException::withMessages([
                        'phases' => "Bitte der Klasse {$className} genau {$daysPerClass} PA-Tage zuordnen, bevor der Ablauf bestätigt wird.",
                    ]);
                }
            }
        }

        return $assignments->all();
    }

    private function validatedPartDateAssignments(array $phase, array $dates, Collection $parts, bool $requireComplete): array
    {
        if (($phase['phase_type'] ?? null) !== 'workshop_days') {
            return [];
        }

        $validDates = collect($dates);
        $assignments = collect($phase['part_date_assignments'] ?? [])->only($parts->all())
            ->map(fn ($assignedDates) => collect($assignedDates)->filter()->unique()->sort()->values()->all());
        foreach ($assignments as $part => $assignedDates) {
            if (collect($assignedDates)->diff($validDates)->isNotEmpty()) {
                throw ValidationException::withMessages(['phases' => "Teil {$part} enthält einen unbekannten Werkstatttag."]);
            }
        }
        if ($requireComplete) {
            foreach ($parts as $part) {
                if (empty($assignments->get($part, []))) {
                    throw ValidationException::withMessages(['phases' => "Bitte Teil {$part} mindestens einen Werkstatttag zuordnen."]);
                }
            }
        }

        return $assignments->all();
    }

    private function phaseLabel(string $type): string
    {
        return [
            'pa_preparation' => 'Vorbereitung PA', 'pa' => 'Potenzialanalyse',
            'pa_feedback' => 'Feedbackgespraech PA', 'roll_day' => 'Rolltag',
            'workshop_days' => 'Werkstatttage', 'wt_feedback' => 'Feedbackgespraech WT',
        ][$type] ?? $type;
    }

    private function phaseCalendarColors(string $type): array
    {
        return match ($type) {
            'pa_preparation' => ['background' => '#6b7280', 'text' => '#ffffff'],
            'pa' => ['background' => '#2563eb', 'text' => '#ffffff'],
            'pa_feedback' => ['background' => '#9333ea', 'text' => '#ffffff'],
            'roll_day' => ['background' => '#dc2626', 'text' => '#ffffff'],
            'workshop_days' => ['background' => '#16a34a', 'text' => '#ffffff'],
            'wt_feedback' => ['background' => '#111827', 'text' => '#ffffff'],
            default => ['background' => '#f97316', 'text' => '#ffffff'],
        };
    }

    private function phaseCalendarTitle(string $type, string $schoolName): string
    {
        $prefix = match ($type) {
            'pa_preparation' => 'Vorb. PA',
            'pa' => 'PA',
            'pa_feedback' => 'Feedb.',
            'roll_day' => 'Rolltag',
            'workshop_days' => 'BO.',
            'wt_feedback' => 'Feedb. BO.',
            default => $this->phaseLabel($type),
        };

        return trim($prefix.' '.$schoolName);
    }
}
