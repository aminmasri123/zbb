<?php

namespace Tests\Unit\Services\Scheduling;

use App\Services\Scheduling\AreaRotationScheduleGenerator;
use DomainException;
use PHPUnit\Framework\TestCase;

class AreaRotationScheduleGeneratorTest extends TestCase
{
    public function test_it_calculates_the_area_duration_from_the_remaining_day(): void
    {
        $result = (new AreaRotationScheduleGenerator)->generate([
            'start_time' => '09:00',
            'end_time' => '13:00',
            'slot_minutes' => 15,
            'groups' => ['G1', 'G2', 'G3'],
            'areas' => [
                ['bereich_id' => 1, 'name' => 'IT'],
                ['bereich_id' => 2, 'name' => 'Kunst'],
                ['bereich_id' => 3, 'name' => 'Sport'],
            ],
            'events' => [
                ['title' => 'Begrüßung', 'type' => 'shared', 'start_time' => '09:00', 'end_time' => '09:30'],
            ],
        ]);

        $this->assertSame('automatic', $result['config']['duration_mode']);
        $this->assertSame(70, $result['config']['calculated_area_duration_minutes']);
        $this->assertSame(3, $result['config']['rotation_count']);
        $this->assertSame(0, $result['config']['unallocated_minutes']);
        $this->assertSame([70], array_values(array_unique(array_column($result['config']['areas'], 'duration_minutes'))));
    }

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

    public function test_it_staggers_breaks_for_two_group_halves(): void
    {
        $result = (new AreaRotationScheduleGenerator)->generate([
            'start_time' => '09:00',
            'end_time' => '13:00',
            'slot_minutes' => 30,
            'groups' => ['G1', 'G2', 'G3', 'G4', 'G5', 'G6'],
            'areas' => [
                ['bereich_id' => 1, 'name' => 'IT'],
                ['bereich_id' => 2, 'name' => 'Kunst'],
                ['bereich_id' => 3, 'name' => 'Sport'],
            ],
            'events' => [
                [
                    'title' => 'Pause 1', 'type' => 'break', 'group_scope' => 'first_half',
                    'start_time' => '10:00', 'end_time' => '10:30',
                ],
                [
                    'title' => 'Pause 2', 'type' => 'break', 'group_scope' => 'second_half',
                    'start_time' => '10:30', 'end_time' => '11:00',
                ],
            ],
        ]);

        $breaks = array_values(array_filter($result['entries'], fn ($entry) => $entry['type'] === 'break'));
        $areaEntries = array_values(array_filter($result['entries'], fn ($entry) => $entry['type'] === 'area'));

        $this->assertCount(2, $breaks);
        $this->assertSame(['G1', 'G2', 'G3'], $breaks[0]['meta']['group_labels']);
        $this->assertSame(['G4', 'G5', 'G6'], $breaks[1]['meta']['group_labels']);
        $this->assertSame(35, $result['config']['calculated_area_duration_minutes']);

        foreach ($result['config']['groups'] as $group) {
            $this->assertEqualsCanonicalizing(
                ['IT', 'Kunst', 'Sport'],
                array_values(array_unique(array_column(
                    array_filter($areaEntries, fn ($entry) => $entry['group_key'] === $group),
                    'title'
                )))
            );
        }

        $this->assertNotEmpty(array_filter($areaEntries, fn ($entry) =>
            in_array($entry['group_key'], ['G4', 'G5', 'G6'], true)
            && $entry['start_time'] < '10:30' && $entry['end_time'] > '10:00'
        ));
        foreach ($result['config']['groups'] as $group) {
            $groupEntries = array_values(array_filter($areaEntries, fn ($entry) => $entry['group_key'] === $group));
            foreach (array_unique(array_column($groupEntries, 'bereich_id')) as $areaId) {
                $segments = array_values(array_filter($groupEntries, fn ($entry) => $entry['bereich_id'] === $areaId));
                usort($segments, fn ($left, $right) => $left['start_time'] <=> $right['start_time']);
                for ($index = 1; $index < count($segments); $index++) {
                    $this->assertNotEmpty(array_filter($breaks, fn ($break) =>
                        in_array($group, $break['meta']['group_labels'], true)
                        && $break['start_time'] === $segments[$index - 1]['end_time']
                        && $break['end_time'] === $segments[$index]['start_time']
                    ), "{$group} darf einen Bereich nur wegen einer Pause unterbrechen.");
                }
            }
        }
    }

    public function test_manual_event_times_do_not_have_to_match_the_selected_time_grid(): void
    {
        $result = (new AreaRotationScheduleGenerator)->generate([
            'start_time' => '09:00',
            'end_time' => '12:00',
            'slot_minutes' => 15,
            'groups' => ['G1'],
            'areas' => [
                ['bereich_id' => 1, 'name' => 'IT'],
            ],
            'events' => [
                [
                    'title' => 'Pause2 G1', 'type' => 'break', 'group_scope' => 'all',
                    'start_time' => '11:30', 'end_time' => '11:50',
                ],
            ],
        ]);

        $this->assertSame('11:30', $result['config']['events'][0]['start_time']);
        $this->assertSame('11:50', $result['config']['events'][0]['end_time']);
        $this->assertSame(160, $result['config']['calculated_area_duration_minutes']);
    }

    public function test_it_respects_a_manually_swapped_area_order(): void
    {
        $result = (new AreaRotationScheduleGenerator)->generate([
            'start_time' => '09:00',
            'end_time' => '13:00',
            'slot_minutes' => 1,
            'groups' => ['G1', 'G2'],
            'areas' => [
                ['bereich_id' => 1, 'name' => 'IT', 'duration_minutes' => 45],
                ['bereich_id' => 2, 'name' => 'Kunst', 'duration_minutes' => 45],
                ['bereich_id' => 3, 'name' => 'Sport', 'duration_minutes' => 45],
            ],
            'events' => [],
            'area_orders' => [
                'G1' => [3, 1, 2],
            ],
        ]);

        $g1Entries = array_values(array_filter(
            $result['entries'],
            fn ($entry) => $entry['type'] === 'area' && $entry['group_key'] === 'G1'
        ));

        $this->assertSame(['Sport', 'IT', 'Kunst'], array_column($g1Entries, 'title'));
        $this->assertSame([3, 1, 2], $result['config']['area_orders']['G1']);
    }

    public function test_it_distributes_remaining_minutes_to_adjacent_areas(): void
    {
        $result = (new AreaRotationScheduleGenerator)->generate([
            'start_time' => '08:30',
            'end_time' => '13:30',
            'slot_minutes' => 1,
            'groups' => ['G1', 'G2'],
            'areas' => [
                ['bereich_id' => 1, 'name' => 'IT'],
                ['bereich_id' => 2, 'name' => 'Kunst'],
                ['bereich_id' => 3, 'name' => 'Sport'],
                ['bereich_id' => 4, 'name' => 'Holz'],
            ],
            'events' => [
                ['title' => 'Begrüßung', 'type' => 'shared', 'group_scope' => 'all', 'start_time' => '08:30', 'end_time' => '08:45'],
                ['title' => 'Pause 1 G1', 'type' => 'break', 'group_scope' => 'first_half', 'start_time' => '09:30', 'end_time' => '09:45'],
                ['title' => 'Pause 1 G2', 'type' => 'break', 'group_scope' => 'second_half', 'start_time' => '10:00', 'end_time' => '10:15'],
                ['title' => 'Pause 2 G1', 'type' => 'break', 'group_scope' => 'first_half', 'start_time' => '11:30', 'end_time' => '11:50'],
                ['title' => 'Pause 2 G2', 'type' => 'break', 'group_scope' => 'second_half', 'start_time' => '11:55', 'end_time' => '12:15'],
                ['title' => 'Standort', 'type' => 'extra', 'group_scope' => 'all', 'start_time' => '13:15', 'end_time' => '13:30'],
            ],
        ]);

        $this->assertSame(0, $result['config']['unallocated_minutes']);
        $this->assertSame(58, $result['config']['actual_area_duration_min_minutes']);
        $this->assertSame(61, $result['config']['actual_area_duration_max_minutes']);
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
