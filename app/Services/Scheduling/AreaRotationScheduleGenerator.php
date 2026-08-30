<?php

namespace App\Services\Scheduling;

use DomainException;

class AreaRotationScheduleGenerator
{
    /**
     * Generate a daily area rotation. Fixed activities may apply to all groups
     * or to one automatically determined half of the groups.
     */
    public function generate(array $input): array
    {
        $slotMinutes = (int) ($input['slot_minutes'] ?? 15);
        $planningStep = 1;
        $dayStart = $this->toMinutes((string) $input['start_time']);
        $dayEnd = $this->toMinutes((string) $input['end_time']);
        $groups = array_values(array_unique(array_map(
            fn ($group) => trim((string) $group),
            $input['groups'] ?? []
        )));
        $groups = array_values(array_filter($groups, fn ($group) => $group !== ''));
        $areas = array_values($input['areas'] ?? []);

        if ($dayEnd <= $dayStart) {
            throw new DomainException('Die Endzeit muss nach der Startzeit liegen.');
        }
        if (! in_array($slotMinutes, [1, 5, 10, 15, 30], true)) {
            throw new DomainException('Die Zeitgenauigkeit muss 1, 5, 10, 15 oder 30 Minuten betragen.');
        }
        if ($groups === []) {
            throw new DomainException('Bitte mindestens eine Gruppe angeben.');
        }
        if ($areas === []) {
            throw new DomainException('Bitte mindestens einen Bereich auswählen.');
        }

        $events = $this->normaliseEvents(
            $input['events'] ?? [],
            $dayStart,
            $dayEnd,
            $groups
        );
        usort($events, fn ($left, $right) => $left['start'] <=> $right['start']);
        $this->assertEventsDoNotOverlap($events);

        $automaticDuration = count(array_filter(
            $areas,
            fn ($area) => ! isset($area['duration_minutes']) || (int) $area['duration_minutes'] <= 0
        )) > 0;

        foreach ($areas as $index => &$area) {
            $area['bereich_id'] = (int) $area['bereich_id'];
            $area['name'] = trim((string) ($area['name'] ?? ('Bereich '.$area['bereich_id'])));
            $area['duration_minutes'] = $automaticDuration ? 0 : (int) $area['duration_minutes'];
            $area['supervisor_person_id'] = ($area['supervisor_person_id'] ?? null) !== null
                && ($area['supervisor_person_id'] ?? '') !== ''
                ? (int) $area['supervisor_person_id'] : null;
            $area['supervisor_name'] = trim((string) ($area['supervisor_name'] ?? ''));
            $area['_index'] = $index;

            if (! $automaticDuration
                && $area['duration_minutes'] < 1) {
                throw new DomainException("Die Dauer für {$area['name']} muss mindestens eine Minute betragen.");
            }
            if (! $automaticDuration && $area['duration_minutes'] > ($dayEnd - $dayStart)) {
                throw new DomainException("Die Dauer für {$area['name']} ist länger als der Planungstag.");
            }
        }
        unset($area);

        if (count(array_unique(array_column($areas, 'bereich_id'))) !== count($areas)) {
            throw new DomainException('Ein Bereich wurde mehrfach ausgewählt.');
        }

        $this->assertSupervisorAssignmentsAreUnique($areas, $groups);

        $blockedSlots = $this->blockedSlots($groups, $events, $dayStart, $dayEnd, $planningStep);
        $slotAssignments = null;
        $calculatedDuration = null;

        if ($automaticDuration) {
            $largestDurationSlots = $this->largestAutomaticDurationInSlots(
                $groups,
                $areas,
                $blockedSlots,
                intdiv($dayEnd - $dayStart, $planningStep)
            );

            for ($durationSlots = $largestDurationSlots; $durationSlots >= 1; $durationSlots--) {
                foreach ($areas as &$area) {
                    $area['duration_minutes'] = $durationSlots * $planningStep;
                }
                unset($area);

                $slotAssignments = $this->scheduleSlots($groups, $areas, $blockedSlots, $dayStart, $dayEnd, $planningStep);
                if ($slotAssignments !== null) {
                    $calculatedDuration = $durationSlots * $planningStep;
                    break;
                }
            }

            if ($slotAssignments === null) {
                throw new DomainException('Nach Abzug der Pausen und Aktivitäten bleibt nicht genug Zeit für alle Bereichsrotationen.');
            }
        } else {
            $slotAssignments = $this->scheduleSlots($groups, $areas, $blockedSlots, $dayStart, $dayEnd, $planningStep);
            if ($slotAssignments === null) {
                throw new DomainException('Der Zeitraum reicht für alle Bereichsrotationen nicht aus. Bitte den Tag verlängern, Bereiche kürzen oder Gruppen reduzieren.');
            }
        }

        $entries = array_map(fn ($event) => [
            'group_key' => null,
            'type' => $event['type'],
            'title' => $event['title'],
            'bereich_id' => null,
            'supervisor_person_id' => null,
            'start_time' => $this->formatMinutes($event['start']),
            'end_time' => $this->formatMinutes($event['end']),
            'meta' => [
                'group_scope' => $event['group_scope'],
                'group_labels' => $event['group_labels'],
            ],
        ], $events);
        $entries = array_merge(
            $entries,
            $this->areaEntriesFromSlots($slotAssignments, $groups, $areas, $dayStart, $planningStep)
        );

        $this->assertGeneratedEntriesDoNotConflict($entries);

        usort($entries, fn ($left, $right) => [
            $left['start_time'], $left['group_key'] ?? '', $left['title'],
        ] <=> [
            $right['start_time'], $right['group_key'] ?? '', $right['title'],
        ]);

        $minimumFreeSlots = min(array_map(
            fn ($groupIndex) => count(array_filter($blockedSlots[$groupIndex], fn ($blocked) => ! $blocked)),
            array_keys($groups)
        ));
        $usedMinutesPerGroup = array_sum(array_column($areas, 'duration_minutes'));

        return [
            'schedule_date' => $input['schedule_date'] ?? null,
            'slot_minutes' => $slotMinutes,
            'config' => [
                'start_time' => $this->formatMinutes($dayStart),
                'end_time' => $this->formatMinutes($dayEnd),
                'groups' => $groups,
                'duration_mode' => $automaticDuration ? 'automatic' : 'manual',
                'calculated_area_duration_minutes' => $calculatedDuration,
                'rotation_count' => max(count($groups), count($areas)),
                'unallocated_minutes' => max(0, ($minimumFreeSlots * $planningStep) - $usedMinutesPerGroup),
                'areas' => array_map(fn ($area) => array_diff_key($area, ['_index' => true]), $areas),
                'events' => array_map(fn ($event) => [
                    'title' => $event['title'],
                    'type' => $event['type'],
                    'group_scope' => $event['group_scope'],
                    'group_labels' => $event['group_labels'],
                    'start_time' => $this->formatMinutes($event['start']),
                    'end_time' => $this->formatMinutes($event['end']),
                ], $events),
            ],
            'entries' => $entries,
        ];
    }

    private function normaliseEvents(
        array $events,
        int $dayStart,
        int $dayEnd,
        array $groups
    ): array {
        return array_values(array_map(function (array $event) use ($dayStart, $dayEnd, $groups) {
            $start = $this->toMinutes((string) $event['start_time']);
            $end = $this->toMinutes((string) $event['end_time']);
            $title = trim((string) ($event['title'] ?? ''));
            $type = (string) ($event['type'] ?? 'shared');
            $groupScope = (string) ($event['group_scope'] ?? 'all');

            if ($title === '') {
                throw new DomainException('Jede Aktivität benötigt einen Namen.');
            }
            if (! in_array($type, ['shared', 'break', 'extra'], true)) {
                throw new DomainException("Die Aktivitätsart für {$title} ist ungültig.");
            }
            if (! in_array($groupScope, ['all', 'first_half', 'second_half'], true)) {
                throw new DomainException("Die Gruppenauswahl für {$title} ist ungültig.");
            }
            if ($start < $dayStart || $end > $dayEnd || $end <= $start) {
                throw new DomainException("Die Zeit für {$title} liegt außerhalb des Planungstages.");
            }
            $halfSize = (int) ceil(count($groups) / 2);
            $groupLabels = match ($groupScope) {
                'first_half' => array_slice($groups, 0, $halfSize),
                'second_half' => array_slice($groups, $halfSize),
                default => $groups,
            };
            if ($groupLabels === []) {
                throw new DomainException("Für {$title} enthält die ausgewählte Gruppenhälfte keine Gruppe.");
            }

            return [
                'title' => $title,
                'type' => $type,
                'group_scope' => $groupScope,
                'group_labels' => $groupLabels,
                'start' => $start,
                'end' => $end,
            ];
        }, $events));
    }

    private function assertEventsDoNotOverlap(array $events): void
    {
        foreach ($events as $leftIndex => $left) {
            foreach ($events as $rightIndex => $right) {
                if ($rightIndex <= $leftIndex) {
                    continue;
                }
                $sameGroups = array_intersect($left['group_labels'], $right['group_labels']) !== [];
                if ($sameGroups && $this->overlaps($left['start'], $left['end'], $right['start'], $right['end'])) {
                    throw new DomainException("Die Aktivitäten {$left['title']} und {$right['title']} überschneiden sich für mindestens eine Gruppe.");
                }
            }
        }
    }

    private function assertSupervisorAssignmentsAreUnique(array $areas, array $groups): void
    {
        if (count($groups) < 2) {
            return;
        }

        $assigned = [];
        foreach ($areas as $area) {
            $supervisorId = $area['supervisor_person_id'];
            if (! $supervisorId) {
                continue;
            }
            if (isset($assigned[$supervisorId])) {
                $name = $area['supervisor_name'] ?: 'Der ausgewählte Anleiter';
                throw new DomainException("{$name} wäre gleichzeitig in mehreren Bereichen eingeplant.");
            }
            $assigned[$supervisorId] = true;
        }
    }

    private function blockedSlots(
        array $groups,
        array $events,
        int $dayStart,
        int $dayEnd,
        int $slotMinutes
    ): array {
        $slotCount = intdiv($dayEnd - $dayStart, $slotMinutes);
        $blocked = array_fill(0, count($groups), array_fill(0, $slotCount, false));
        $groupIndexes = array_flip($groups);

        foreach ($events as $event) {
            $firstSlot = intdiv($event['start'] - $dayStart, $slotMinutes);
            $lastSlot = intdiv($event['end'] - $dayStart, $slotMinutes);
            foreach ($event['group_labels'] as $group) {
                $groupIndex = $groupIndexes[$group];
                for ($slot = $firstSlot; $slot < $lastSlot; $slot++) {
                    $blocked[$groupIndex][$slot] = true;
                }
            }
        }

        return $blocked;
    }

    private function largestAutomaticDurationInSlots(
        array $groups,
        array $areas,
        array $blockedSlots,
        int $slotCount
    ): int {
        $areaCount = count($areas);
        $perGroupBound = min(array_map(
            fn ($slots) => intdiv(count(array_filter($slots, fn ($blocked) => ! $blocked)), $areaCount),
            $blockedSlots
        ));

        $capacity = 0;
        for ($slot = 0; $slot < $slotCount; $slot++) {
            $availableGroups = count(array_filter(
                array_keys($groups),
                fn ($groupIndex) => ! $blockedSlots[$groupIndex][$slot]
            ));
            $capacity += min($availableGroups, $areaCount);
        }
        $globalBound = intdiv($capacity, count($groups) * $areaCount);

        return min($perGroupBound, $globalBound);
    }

    private function scheduleSlots(
        array $groups,
        array $areas,
        array $blockedSlots,
        int $dayStart,
        int $dayEnd,
        int $slotMinutes
    ): ?array {
        $slotCount = intdiv($dayEnd - $dayStart, $slotMinutes);
        $remaining = [];
        foreach ($groups as $groupIndex => $group) {
            foreach ($areas as $areaIndex => $area) {
                $remaining[$groupIndex][$areaIndex] = intdiv($area['duration_minutes'], $slotMinutes);
            }
        }

        $futureFree = array_fill(0, count($groups), array_fill(0, $slotCount + 1, 0));
        foreach (array_keys($groups) as $groupIndex) {
            for ($slot = $slotCount - 1; $slot >= 0; $slot--) {
                $futureFree[$groupIndex][$slot] = $futureFree[$groupIndex][$slot + 1]
                    + ($blockedSlots[$groupIndex][$slot] ? 0 : 1);
            }
        }

        $assignments = array_fill(0, $slotCount, []);
        $previousArea = array_fill(0, count($groups), null);

        for ($slot = 0; $slot < $slotCount; $slot++) {
            $availableGroups = array_values(array_filter(
                array_keys($groups),
                fn ($groupIndex) => ! $blockedSlots[$groupIndex][$slot]
                    && array_sum($remaining[$groupIndex]) > 0
            ));
            usort($availableGroups, function ($left, $right) use ($futureFree, $remaining, $slot) {
                $leftSlack = $futureFree[$left][$slot] - array_sum($remaining[$left]);
                $rightSlack = $futureFree[$right][$slot] - array_sum($remaining[$right]);

                return [$leftSlack, $left] <=> [$rightSlack, $right];
            });

            $areaToGroup = [];
            foreach ($availableGroups as $groupIndex) {
                $seenAreas = [];
                $this->matchGroupToArea(
                    $groupIndex,
                    $areaToGroup,
                    $seenAreas,
                    $remaining,
                    $previousArea
                );
            }

            $currentArea = array_fill(0, count($groups), null);
            foreach ($areaToGroup as $areaIndex => $groupIndex) {
                $assignments[$slot][$groupIndex] = $areaIndex;
                $remaining[$groupIndex][$areaIndex]--;
                $currentArea[$groupIndex] = $areaIndex;
            }
            $previousArea = $currentArea;
        }

        foreach ($remaining as $groupRemaining) {
            if (array_sum($groupRemaining) > 0) {
                return null;
            }
        }

        return $assignments;
    }

    private function matchGroupToArea(
        int $groupIndex,
        array &$areaToGroup,
        array &$seenAreas,
        array $remaining,
        array $previousArea
    ): bool {
        $candidateAreas = array_values(array_filter(
            array_keys($remaining[$groupIndex]),
            fn ($areaIndex) => $remaining[$groupIndex][$areaIndex] > 0
        ));
        usort($candidateAreas, function ($left, $right) use ($groupIndex, $remaining, $previousArea) {
            $leftPrevious = $previousArea[$groupIndex] === $left ? 0 : 1;
            $rightPrevious = $previousArea[$groupIndex] === $right ? 0 : 1;

            return [$leftPrevious, -$remaining[$groupIndex][$left], $left]
                <=> [$rightPrevious, -$remaining[$groupIndex][$right], $right];
        });

        foreach ($candidateAreas as $areaIndex) {
            if (isset($seenAreas[$areaIndex])) {
                continue;
            }
            $seenAreas[$areaIndex] = true;

            if (! isset($areaToGroup[$areaIndex]) || $this->matchGroupToArea(
                $areaToGroup[$areaIndex],
                $areaToGroup,
                $seenAreas,
                $remaining,
                $previousArea
            )) {
                $areaToGroup[$areaIndex] = $groupIndex;

                return true;
            }
        }

        return false;
    }

    private function areaEntriesFromSlots(
        array $assignments,
        array $groups,
        array $areas,
        int $dayStart,
        int $slotMinutes
    ): array {
        $entries = [];

        foreach ($groups as $groupIndex => $group) {
            $activeArea = null;
            $segmentStart = 0;
            $segmentNumber = 0;
            $slotCount = count($assignments);

            for ($slot = 0; $slot <= $slotCount; $slot++) {
                $areaIndex = $slot < $slotCount ? ($assignments[$slot][$groupIndex] ?? null) : null;
                if ($areaIndex === $activeArea) {
                    continue;
                }

                if ($activeArea !== null) {
                    $area = $areas[$activeArea];
                    $segmentNumber++;
                    $entries[] = [
                        'group_key' => $group,
                        'type' => 'area',
                        'title' => $area['name'],
                        'bereich_id' => $area['bereich_id'],
                        'supervisor_person_id' => $area['supervisor_person_id'],
                        'start_time' => $this->formatMinutes($dayStart + ($segmentStart * $slotMinutes)),
                        'end_time' => $this->formatMinutes($dayStart + ($slot * $slotMinutes)),
                        'meta' => array_filter([
                            'round' => $segmentNumber,
                            'supervisor_name' => $area['supervisor_name'] ?: null,
                        ], fn ($value) => $value !== null),
                    ];
                }

                $activeArea = $areaIndex;
                $segmentStart = $slot;
            }
        }

        return $entries;
    }

    private function assertGeneratedEntriesDoNotConflict(array $entries): void
    {
        $areaEntries = array_values(array_filter($entries, fn ($entry) => $entry['type'] === 'area'));
        foreach ($areaEntries as $leftIndex => $left) {
            foreach ($areaEntries as $rightIndex => $right) {
                if ($rightIndex <= $leftIndex) {
                    continue;
                }
                $leftStart = $this->toMinutes($left['start_time']);
                $leftEnd = $this->toMinutes($left['end_time']);
                $rightStart = $this->toMinutes($right['start_time']);
                $rightEnd = $this->toMinutes($right['end_time']);
                if (! $this->overlaps($leftStart, $leftEnd, $rightStart, $rightEnd)) {
                    continue;
                }

                if ($left['bereich_id'] === $right['bereich_id']) {
                    throw new DomainException("Der Bereich {$left['title']} wäre gleichzeitig für mehrere Gruppen eingeplant.");
                }
                if ($left['group_key'] === $right['group_key']) {
                    throw new DomainException("Die Gruppe {$left['group_key']} wäre gleichzeitig in mehreren Bereichen eingeplant.");
                }
                if ($left['supervisor_person_id'] && $left['supervisor_person_id'] === $right['supervisor_person_id']) {
                    $name = $left['meta']['supervisor_name'] ?? 'Der ausgewählte Anleiter';
                    throw new DomainException("{$name} wäre gleichzeitig in mehreren Bereichen eingeplant.");
                }
            }
        }
    }

    private function overlaps(int $leftStart, int $leftEnd, int $rightStart, int $rightEnd): bool
    {
        return $leftStart < $rightEnd && $leftEnd > $rightStart;
    }

    private function toMinutes(string $time): int
    {
        if (! preg_match('/^(\d{2}):(\d{2})/', $time, $matches)) {
            throw new DomainException("Ungültige Uhrzeit: {$time}");
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    private function formatMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
