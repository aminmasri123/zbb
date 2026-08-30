<?php

namespace Tests\Unit\Services\Scheduling;

use App\Services\Scheduling\AreaRotationScheduleGenerator;
use DomainException;
use PHPUnit\Framework\TestCase;

class AreaRotationScheduleGeneratorTest extends TestCase
{
    public function test_it_allows_shared_events_but_never_uses_an_area_twice_at_the_same_time(): void
    {
        $result = (new AreaRotationScheduleGenerator)->generate([
            'schedule_date' => '2026-09-01',
            'start_time' => '09:00',
            'end_time' => '13:30',
            'slot_minutes' => 15,
            'groups' => ['G1', 'G2', 'G3'],
            'areas' => [
                ['bereich_id' => 1, 'name' => 'IT', 'duration_minutes' => 45, 'supervisor_person_id' => 11],
                ['bereich_id' => 2, 'name' => 'Kunst', 'duration_minutes' => 45, 'supervisor_person_id' => 12],
                ['bereich_id' => 3, 'name' => 'Sport', 'duration_minutes' => 45, 'supervisor_person_id' => 13],
            ],
            'events' => [
                ['title' => 'Begrüßung', 'type' => 'shared', 'start_time' => '09:00', 'end_time' => '09:30'],
                ['title' => 'Standortbestimmung', 'type' => 'shared', 'start_time' => '12:00', 'end_time' => '12:30'],
            ],
        ]);

        $shared = array_values(array_filter($result['entries'], fn ($entry) => $entry['type'] === 'shared'));
        $areas = array_values(array_filter($result['entries'], fn ($entry) => $entry['type'] === 'area'));

        $this->assertCount(2, $shared);
        $this->assertSame(['G1', 'G2', 'G3'], $shared[0]['meta']['group_labels']);
        $this->assertCount(9, $areas);

        foreach (['G1', 'G2', 'G3'] as $group) {
            $this->assertEqualsCanonicalizing(
                ['IT', 'Kunst', 'Sport'],
                array_values(array_unique(array_column(array_filter($areas, fn ($entry) => $entry['group_key'] === $group), 'title')))
            );
        }

        foreach ($areas as $leftIndex => $left) {
            foreach ($areas as $rightIndex => $right) {
                if ($rightIndex <= $leftIndex || $left['bereich_id'] !== $right['bereich_id']) {
                    continue;
                }
                $this->assertFalse($left['start_time'] < $right['end_time'] && $left['end_time'] > $right['start_time']);
            }
        }
    }

    public function test_it_rejects_a_supervisor_used_in_parallel_areas(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('gleichzeitig in mehreren Bereichen');

        (new AreaRotationScheduleGenerator)->generate([
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_minutes' => 15,
            'groups' => ['G1', 'G2'],
            'areas' => [
                ['bereich_id' => 1, 'name' => 'IT', 'duration_minutes' => 60, 'supervisor_person_id' => 11],
                ['bereich_id' => 2, 'name' => 'Kunst', 'duration_minutes' => 60, 'supervisor_person_id' => 11],
            ],
            'events' => [],
        ]);
    }

    public function test_it_reports_when_the_day_is_too_short(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Zeitraum reicht');

        (new AreaRotationScheduleGenerator)->generate([
            'start_time' => '09:00',
            'end_time' => '10:00',
            'slot_minutes' => 15,
            'groups' => ['G1', 'G2'],
            'areas' => [
                ['bereich_id' => 1, 'name' => 'IT', 'duration_minutes' => 60],
                ['bereich_id' => 2, 'name' => 'Kunst', 'duration_minutes' => 60],
            ],
            'events' => [],
        ]);
    }
}
