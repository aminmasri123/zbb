<?php

namespace App\Http\Controllers;

use App\Models\InternshipAttendance;
use App\Models\PersonenHasBildungsmassnahmen;
use App\Services\Projects\ActiveProjectContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InternshipAttendanceController extends Controller
{
    private const STATUSES = [
        'present',
        'absent_excused',
        'absent_unexcused',
        'sick',
        'vacation',
        'school',
        'holiday',
    ];

    public function __construct(private readonly ActiveProjectContext $activeProjectContext) {}

    public function updateWeek(Request $request, PersonenHasBildungsmassnahmen $measure)
    {
        $measure = $this->authorizedMeasure($request, $measure);
        $data = $request->validate([
            'week_start' => ['required', 'date'],
            'days' => ['required', 'array', 'min:1', 'max:7'],
            'days.*.date' => ['required', 'date'],
            'days.*.status' => ['nullable', Rule::in(self::STATUSES)],
            'days.*.planned_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'days.*.actual_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'days.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $weekStart = Carbon::parse($data['week_start'])->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $internshipStart = $measure->start->copy()->startOfDay();
        $internshipEnd = $measure->end->copy()->endOfDay();

        DB::transaction(function () use ($request, $measure, $data, $weekStart, $weekEnd, $internshipStart, $internshipEnd): void {
            foreach ($data['days'] as $day) {
                $date = Carbon::parse($day['date'])->startOfDay();
                if (! $date->betweenIncluded($weekStart, $weekEnd) || $date->isWeekend()) {
                    throw ValidationException::withMessages([
                        'days' => 'Es dürfen nur Werktage der ausgewählten Woche gespeichert werden.',
                    ]);
                }
                if (! $date->betweenIncluded($internshipStart, $internshipEnd)) {
                    throw ValidationException::withMessages([
                        'days' => 'Ein Anwesenheitstag liegt außerhalb des Praktikumszeitraums.',
                    ]);
                }

                $status = $day['status'] ?? null;
                if (blank($status)) {
                    InternshipAttendance::query()
                        ->where('education_measure_id', $measure->id)
                        ->whereDate('attendance_date', $date)
                        ->delete();

                    continue;
                }

                InternshipAttendance::query()->updateOrCreate(
                    [
                        'education_measure_id' => $measure->id,
                        'attendance_date' => $date->toDateString(),
                    ],
                    [
                        'status' => $status,
                        'planned_minutes' => $this->hoursToMinutes($day['planned_hours'] ?? null),
                        'actual_minutes' => $this->hoursToMinutes($day['actual_hours'] ?? null),
                        'note' => filled($day['note'] ?? null) ? trim($day['note']) : null,
                        'recorded_by_user_id' => $request->user()->id,
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Praktikumswoche wurde gespeichert.',
            'attendances' => $measure->attendances()->get(),
        ]);
    }

    private function authorizedMeasure(Request $request, PersonenHasBildungsmassnahmen $measure): PersonenHasBildungsmassnahmen
    {
        $project = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($project && $project->featureEnabled('internship_management'), 404);
        abort_unless($measure->typ === 'Praktikum' && ! $measure->archived_at, 404);

        $authorized = PersonenHasBildungsmassnahmen::query()
            ->whereKey($measure->id)
            ->whereHas('projektTeilnahme', fn (Builder $query) => $query->where('projekt_id', $project->id))
            ->whereHas('participant', fn (Builder $query) => $query->visibleForUser($request->user()))
            ->exists();
        abort_unless($authorized, 404);

        return $measure;
    }

    private function hoursToMinutes(mixed $hours): ?int
    {
        return $hours === null || $hours === '' ? null : (int) round((float) $hours * 60);
    }
}
