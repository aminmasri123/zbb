<?php

namespace App\Services\Participants;

use App\Models\GruppeHasPersonen;
use App\Models\AppTask;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\ParticipantPortalInvitation;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\PersonenHasBildungsmassnahmen;
use App\Models\User;
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

        $participants->loadMissing('kontaktes.kontakttyp');

        $participations = ProjektHasPersonen::query()
            ->with(['standort:id,name', 'meta.betreuer:id,vorname,nachname', 'meta.projektbegleiter:id,vorname,nachname'])
            ->where('projekt_id', $projectId)
            ->whereIn('personen_id', $participantIds)
            ->get()
            ->keyBy('personen_id');

        $attendanceByParticipant = GruppeHasPersonen::query()
            ->with([
                'gruppe:id,projekt_id,bereich_id,personen_id,anfangsdatum,enddatum',
                'gruppe.bereich:id,name',
                'gruppe.betreuer:id,vorname,nachname',
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

        $portalAccountPersonIds = User::query()
            ->whereIn('person_id', $participantIds)
            ->pluck('person_id')
            ->map(fn ($id) => (int) $id)
            ->flip();
        $latestPortalInvitations = ParticipantPortalInvitation::query()
            ->whereIn('project_person_id', $participations->pluck('id'))
            ->whereNull('accepted_at')
            ->latest('created_at')
            ->get(['id', 'project_person_id', 'email', 'expires_at', 'created_at'])
            ->unique('project_person_id')
            ->keyBy('project_person_id');

        $project = Projekt::query()->with('partners:id,name')->find($projectId);
        $partnerIds = $project?->partners?->pluck('id')->filter()->values() ?? collect();
        $schoolRowsByParticipant = PersonenIstSchueler::query()
            ->with(['schule:id,name', 'bereichsauswahl', 'einteilungen.bereich:id,name'])
            ->whereIn('person_id', $participantIds)
            ->when($partnerIds->isNotEmpty(), fn ($query) => $query->whereIn('schule_id', $partnerIds))
            ->get()
            ->sortByDesc(fn ($row) => sprintf('%s|%s', $row->schuljahr ?? '', $row->teil ?? ''))
            ->groupBy('person_id');

        $participants->each(function (Personen $participant) use ($participations, $attendanceByParticipant, $tasksByParticipation, $measuresByParticipation, $schoolRowsByParticipant, $portalAccountPersonIds, $latestPortalInvitations, $period): void {
            $participation = $participations->get($participant->id);
            $portalInvitation = $participation ? $latestPortalInvitations->get($participation->id) : null;
            $attendance = $attendanceByParticipant->get($participant->id, collect());
            $schoolRows = $schoolRowsByParticipant->get($participant->id, collect());
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
                'participation_id' => $participation?->id,
                'portal_account_exists' => $portalAccountPersonIds->has((int) $participant->id),
                'portal_invitation_email' => $portalInvitation?->email,
                'portal_invitation_expires_at' => $portalInvitation?->expires_at?->toISOString(),
                'participant_email' => $participant->kontaktes
                    ?->first(fn ($contact) => in_array(mb_strtolower(trim((string) $contact->kontakttyp?->name)), ['email', 'e-mail'], true))
                    ?->wert,
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
                'school' => $this->summarizeSchool($schoolRows, $attendance),
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

    private function summarizeSchool(Collection $schoolRows, Collection $attendance): array
    {
        $hasSchoolRows = $schoolRows->isNotEmpty();

        return [
            'has_school_data' => $hasSchoolRows,
            'classes' => $schoolRows->pluck('klasse')->filter()->unique()->values(),
            'schools' => $schoolRows->pluck('schule.name')->filter()->unique()->values(),
            'contexts' => $schoolRows
                ->map(fn ($row) => trim(($row->schuljahr ?? '') . ' Teil ' . ($row->teil ?? '')))
                ->filter()
                ->unique()
                ->values(),
            'parental_consent_received' => $hasSchoolRows
                ? $schoolRows->every(fn ($row) => (bool) $row->eee)
                : null,
            'selection_submitted' => $schoolRows->contains(fn ($row) => filled($row->bereichsauswahl?->submitted_at)),
            'visited_areas' => $this->visitedAreas($schoolRows, $attendance),
        ];
    }

    private function visitedAreas(Collection $schoolRows, Collection $attendance): Collection
    {
        $fromGroups = $attendance
            ->filter(fn ($entry) => $entry->gruppe?->bereich?->name)
            ->groupBy('gruppe_id')
            ->map(function (Collection $entries) {
                $group = $entries->first()?->gruppe;
                if (!$group?->bereich?->name) {
                    return null;
                }

                $parts = [$group->bereich->name];
                $dateRange = $this->dateRangeLabel($group->anfangsdatum, $group->enddatum);
                if ($dateRange) {
                    $parts[] = $dateRange;
                }

                $supervisor = $this->personName($group->betreuer);
                if ($supervisor) {
                    $parts[] = $supervisor;
                }

                return implode(' ', $parts);
            })
            ->filter()
            ->unique()
            ->values();

        if ($fromGroups->isNotEmpty()) {
            return $fromGroups;
        }

        return $schoolRows
            ->flatMap(fn ($row) => $row->einteilungen ?? collect())
            ->sortBy('runde')
            ->map(function ($einteilung) {
                $name = $einteilung->bereich?->name;
                if (!$name) {
                    return null;
                }

                return $einteilung->runde
                    ? $name . ' (Runde ' . $einteilung->runde . ')'
                    : $name;
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function dateRangeLabel(?string $start, ?string $end): ?string
    {
        if (!$start && !$end) {
            return null;
        }

        $startLabel = $start ? Carbon::parse($start)->format('d.m.') : '?';
        $endLabel = $end ? Carbon::parse($end)->format('d.m.') : '?';

        return $startLabel === $endLabel ? $startLabel : $startLabel . ' - ' . $endLabel;
    }

    private function personName($person): ?string
    {
        if (!$person) {
            return null;
        }

        return trim($person->vorname . ' ' . $person->nachname) ?: null;
    }
}
