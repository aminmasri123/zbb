<?php

namespace Tests\Unit;

use App\Models\AppTask;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Standort;
use App\Models\User;
use App\Services\Participants\ParticipantOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantOverviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_present_and_unexcused_days_without_counting_absence_as_actual_time(): void
    {
        $entries = collect([
            $this->entry('anwesend', '08:00:00', '16:00:00', '08:00:00', '16:00:00'),
            $this->entry('unentschuldigt', '08:00:00', '16:00:00', '08:00:00', '16:00:00'),
        ]);

        $summary = app(ParticipantOverviewService::class)->summarizeAttendance($entries);

        $this->assertSame(2, $summary['days']);
        $this->assertSame(960, $summary['planned_minutes']);
        $this->assertSame(480, $summary['actual_minutes']);
        $this->assertSame(-480, $summary['balance_minutes']);
        $this->assertSame(50, $summary['attendance_rate']);
        $this->assertSame(1, $summary['absence_days']);
        $this->assertSame(1, $summary['unexcused_days']);
    }

    public function test_summary_counts_open_and_overdue_tasks_only_from_the_selected_project(): void
    {
        $location = Standort::factory()->create();
        $project = Projekt::factory()->create();
        $foreignProject = Projekt::factory()->create();
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $owner = User::factory()->create();
        $participation = ProjektHasPersonen::query()->create(['projekt_id' => $project->id, 'personen_id' => $participant->id, 'standort_id' => $location->id, 'status' => 'aktiv']);
        $foreignParticipation = ProjektHasPersonen::query()->create(['projekt_id' => $foreignProject->id, 'personen_id' => $participant->id, 'standort_id' => $location->id, 'status' => 'aktiv']);

        AppTask::query()->create(['owner_user_id' => $owner->id, 'project_id' => $project->id, 'project_person_id' => $participation->id, 'title' => 'Überfällig', 'status' => 'open', 'priority' => 'high', 'due_at' => today()->subDay(), 'visibility' => 'project']);
        AppTask::query()->create(['owner_user_id' => $owner->id, 'project_id' => $project->id, 'project_person_id' => $participation->id, 'title' => 'Später', 'status' => 'open', 'priority' => 'normal', 'due_at' => today()->addDay(), 'visibility' => 'project']);
        AppTask::query()->create(['owner_user_id' => $owner->id, 'project_id' => $foreignProject->id, 'project_person_id' => $foreignParticipation->id, 'title' => 'Fremdes Projekt', 'status' => 'open', 'priority' => 'high', 'due_at' => today()->subWeek(), 'visibility' => 'project']);

        $summary = app(ParticipantOverviewService::class)->summaryForParticipantIds(collect([$participant->id]), $project->id, now()->format('Y-m'));

        $this->assertSame(1, $summary['participants']);
        $this->assertSame(2, $summary['open_tasks']);
        $this->assertSame(1, $summary['overdue_tasks']);
        $this->assertSame(1, $summary['with_overdue_tasks']);
    }

    private function entry(string $status, string $plannedStart, string $plannedEnd, string $actualStart, string $actualEnd): object
    {
        return (object) [
            'status' => (object) ['status' => $status],
            'zeitgeplant' => (object) ['startzeit' => $plannedStart, 'endzeit' => $plannedEnd],
            'zeittatsaechlich' => (object) ['startzeit' => $actualStart, 'endzeit' => $actualEnd],
        ];
    }
}
