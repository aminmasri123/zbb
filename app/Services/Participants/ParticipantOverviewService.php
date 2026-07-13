<?php

namespace App\Services\Participants;

use App\Models\GruppeHasPersonen;
use App\Models\AppTask;
use App\Models\Personen;
use App\Models\ProjektHasPersonen;
use App\Models\PersonenHasBildungsmassnahmen;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ParticipantOverviewService
{
    public function summaryForParticipantIds(Collection $participantIds, int $projectId, string $period): array
    {
        $participants = Personen::query()->whereIn('id', $participantIds)->get(['id']);
        $this->enrich($participants, $projectId, $period);

        return [
            'participants' => $participants->count(),
            'with_overdue_tasks' => $participants->filter(fn ($participant) => ($participant->overview['overdue_tasks'] ?? 0) > 0)->count(),
            'with_unexcused_absence' => $participants->filter(fn ($participant) => ($participant->overview['period']['unexcused_days'] ?? 0) > 0)->count(),
            'with_negative_balance' => $participants->filter(fn ($participant) => ($participant->overview['period']['balance_minutes'] ?? 0) < 0)->count(),
            'with_overdue_measure_follow_up' => $participants->filter(fn ($participant) => ($participant->overview['overdue_measure_follow_ups'] ?? 0) > 0)->count(),
            'open_tasks' => $participants->sum(fn ($participant) => $participant->overview['open_tasks'] ?? 0),
            'overdue_tasks' => $participants->sum(fn ($participant) => $participant->overview['overdue_tasks'] ?? 0),
            'period_balance_minutes' => $participants->sum(fn ($participant) => $participant->overview['period']['balance_minutes'] ?? 0),
            'active_measures' => $participants->sum(fn ($participant) => $participant->overview['active_measures'] ?? 0),
        ];
    }

    public function enrich(Collection $participants, int $projectId, string $period): void
    {
        $participantIds = $participants->pluck('id')->map(fn ($id) => (int) $id)->values();

        if ($participantIds->isEmpty()) {
            return;
        }

        $participations = ProjektHasPersonen::query()
            ->with(['standort:id,name', 'meta.betreuer:id,vorname,nachname', 'meta.projektbegleiter:id,vorname,nachname'])
            ->where('projekt_id', $projectId)
            ->whereIn('personen_id', $participantIds)
            ->get()
            ->keyBy('personen_id');

        $attendanceByParticipant = GruppeHasPersonen::query()
            ->with([
                'gruppe:id,projekt_id,bereich_id',
                'gruppe.bereich:id,name',
                'tag:id,datum',
                'status:id,status,abkuerzung,farben',
                'zeitgeplant:id,startzeit,endzeit',
                'zeittatsaechlich:id,startzeit,endzeit',
            ])
            ->whereIn('personen_id', $participantIds)
            ->whereHas('gruppe', fn ($query) => $query->where('projekt_id', $projectId))
            ->get()
            ->groupBy('personen_id');

        $tasksByParticipation = AppTask::query()
            ->whereIn('project_person_id', $participations->pluck('id'))
            ->where('status', '!=', 'done')
            ->get(['id', 'project_person_id', 'priority', 'due_at'])
            ->groupBy('project_person_id');

        $measuresByParticipation = PersonenHasBildungsmassnahmen::query()
            ->whereIn('projekt_person_id', $participations->pluck('id'))
            ->whereNull('archived_at')
            ->whereIn('status', ['geplant', 'laufend'])
            ->get(['id', 'projekt_person_id', 'typ', 'traeger', 'status', 'end', 'next_follow_up_at'])
            ->groupBy('projekt_person_id');

        $participants->each(function (Personen $participant) use ($participations, $attendanceByParticipant, $tasksByParticipation, $measuresByParticipation, $period): void {
            $participation = $participations->get($participant->id);
            $attendance = $attendanceByParticipant->get($participant->id, collect());
            $tasks = $participation ? $tasksByParticipation->get($participation->id, collect()) : collect();
            $measures = $participation ? $measuresByParticipation->get($participation->id, collect()) : collect();
            $groups = $attendance
                ->pluck('gruppe.bereich.name')
                ->filter()
                ->unique()
                ->values();

            $participant->setAttribute('overview', [
                'participation_status' => $participation?->status,
                'location' => $participation?->standort?->name,
                'supervisor' => $this->personName($participation?->meta?->betreuer),
                'project_coordinator' => $this->personName($participation?->meta?->projektbegleiter),
                'groups' => $groups,
                'open_tasks' => $tasks->count(),
                'overdue_tasks' => $tasks->filter(fn ($task) => $task->due_at && $task->due_at->isBefore(today()))->count(),
                'next_due_at' => $tasks->pluck('due_at')->filter()->sort()->first()?->toDateString(),
                'active_measures' => $measures->count(),
                'overdue_measure_follow_ups' => $measures->filter(fn ($measure) => $measure->next_follow_up_at && $measure->next_follow_up_at->isBefore(today()))->count(),
                'next_measure_follow_up_at' => $measures->pluck('next_follow_up_at')->filter()->sort()->first()?->toDateString(),
                'period' => $this->summarizeAttendance(
                    $attendance->filter(fn ($entry) => $entry->tag?->datum && Carbon::parse($entry->tag->datum)->format('Y-m') === $period)
                ),
                'total' => $this->summarizeAttendance($attendance),
            ]);
        });
    }

    public function availablePeriods(int $projectId): Collection
    {
        return GruppeHasPersonen::query()
            ->whereHas('gruppe', fn ($query) => $query->where('projekt_id', $projectId))
            ->with('tag:id,datum')
            ->get(['id', 'tage_id'])
            ->pluck('tag.datum')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m'))
            ->unique()
            ->sortDesc()
            ->values();
    }

    public function summarizeAttendance(Collection $entries): array
    {
        $planned = 0;
        $actual = 0;
        $presentDays = 0;
        $statusCounts = [];

        foreach ($entries as $entry) {
            $planned += $this->duration($entry->zeitgeplant?->startzeit, $entry->zeitgeplant?->endzeit);
            $status = trim((string) ($entry->status?->status ?: 'Ohne Status'));
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            if (mb_strtolower($status) !== 'anwesend') {
                continue;
            }

            $presentDays++;
            $actual += $this->duration($entry->zeittatsaechlich?->startzeit, $entry->zeittatsaechlich?->endzeit);
        }

        $days = $entries->count();

        return [
            'days' => $days,
            'planned_minutes' => $planned,
            'actual_minutes' => $actual,
            'balance_minutes' => $actual - $planned,
            'attendance_rate' => $days > 0 ? (int) round(($presentDays / $days) * 100) : null,
            'absence_days' => $days - $presentDays,
            'unexcused_days' => $statusCounts['unentschuldigt'] ?? 0,
            'status_counts' => $statusCounts,
        ];
    }

    private function duration(?string $start, ?string $end): int
    {
        if (!$start || !$end) {
            return 0;
        }

        $startTime = Carbon::parse($start);
        $endTime = Carbon::parse($end);

        return max(0, $startTime->diffInMinutes($endTime, false));
    }

    private function personName($person): ?string
    {
        if (!$person) {
            return null;
        }

        return trim($person->vorname . ' ' . $person->nachname) ?: null;
    }
}
