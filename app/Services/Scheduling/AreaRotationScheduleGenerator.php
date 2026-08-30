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
        $areaOrders = $this->normaliseAreaOrders($input['area_orders'] ?? [], $groups, $areas);

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

                $slotAssignments = $this->scheduleSlots($groups, $areas, $areaOrders, $blockedSlots, $dayStart, $dayEnd, $planningStep);
                if ($slotAssignments !== null) {
                    $calculatedDuration = $durationSlots * $planningStep;
                    break;
                }
            }

            if ($slotAssignments === null) {
                throw new DomainException('Nach Abzug der Pausen und Aktivitäten bleibt nicht genug Zeit für alle Bereichsrotationen.');
            }
        } else {
            $slotAssignments = $this->scheduleSlots($groups, $areas, $areaOrders, $blockedSlots, $dayStart, $dayEnd, $planningStep);
            if ($slotAssignments === null) {
                throw new DomainException('Der Zeitraum reicht für alle Bereichsrotationen nicht aus. Bitte den Tag verlängern, Bereiche kürzen oder Gruppen reduzieren.');
            }
        }

        if ($automaticDuration) {
            $slotAssignments = $this->fillIdleSlots($slotAssignments, $blockedSlots, count($groups), 5);
            $slotAssignments = $this->rescheduleWithBalancedDurations(
                $slotAssignments,
                $groups,
                $areas,
                $areaOrders,
                $blockedSlots,
                $dayStart,
                $dayEnd,
                $planningStep
            ) ?? $this->balanceAreaDurations($slotAssignments, count($groups), count($areas));
        }
        $actualDurations = $this->actualAreaDurations($slotAssignments, $groups, $areas, $planningStep);

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
            $this->areaEntriesFromSlots($slotAssignments, $groups, $areas, $dayStart, $planningStep),
            $automaticDuration
                ? $this->bufferEntriesFromSlots($slotAssignments, $blockedSlots, $groups, $dayStart, $planningStep)
                : []
        );

        $this->assertGeneratedEntriesDoNotConflict($entries);

        usort($entries, fn ($left, $right) => [
            $left['start_time'], $left['group_key'] ?? '', $left['title'],
        ] <=> [
            $right['start_time'], $right['group_key'] ?? '', $right['title'],
        ]);
        $generatedAreaOrders = $this->areaOrdersFromEntries($entries, $groups);

        $idleMinutes = max(array_map(function ($groupIndex) use ($slotAssignments, $blockedSlots, $planningStep) {
            $idleSlots = 0;
            foreach ($blockedSlots[$groupIndex] as $slot => $blocked) {
                if (! $blocked && ! isset($slotAssignments[$slot][$groupIndex])) {
                    $idleSlots++;
                }
            }

            return $idleSlots * $planningStep;
        }, array_keys($groups)));

        return [
            'schedule_date' => $input['schedule_date'] ?? null,
            'slot_minutes' => $slotMinutes,
            'config' => [
                'start_time' => $this->formatMinutes($dayStart),
                'end_time' => $this->formatMinutes($dayEnd),
                'groups' => $groups,
                'duration_mode' => $automaticDuration ? 'automatic' : 'manual',
                'calculated_area_duration_minutes' => $calculatedDuration,
                'actual_area_duration_min_minutes' => min($actualDurations),
                'actual_area_duration_max_minutes' => max($actualDurations),
                'rotation_count' => max(count($groups), count($areas)),
                'unallocated_minutes' => $automaticDuration ? 0 : $idleMinutes,
                'buffer_minutes' => $automaticDuration ? $idleMinutes : 0,
                'areas' => array_map(fn ($area) => array_diff_key($area, ['_index' => true]), $areas),
                'area_orders' => $generatedAreaOrders,
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
                    $blocked[$groupIndex][$slot] = $event['type'];
                }
            }
        }

        return $blocked;
    }

    private function normaliseAreaOrders(array $orders, array $groups, array $areas): array
    {
        $areaIds = array_map(fn ($area) => (int) $area['bereich_id'], $areas);
        $normalised = [];

        foreach ($orders as $group => $orderedIds) {
            if (! in_array((string) $group, $groups, true) || ! is_array($orderedIds)) {
                continue;
            }
            $validIds = array_values(array_unique(array_map('intval', array_filter(
                $orderedIds,
                fn ($areaId) => in_array((int) $areaId, $areaIds, true)
            ))));
            $normalised[(string) $group] = array_values(array_merge(
                $validIds,
                array_values(array_diff($areaIds, $validIds))
            ));
        }

        return $normalised;
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
        array $areaOrders,
        array $blockedSlots,
        int $dayStart,
        int $dayEnd,
        int $slotMinutes,
        ?array $durationSlotsByGroup = null
    ): ?array {
        $slotCount = intdiv($dayEnd - $dayStart, $slotMinutes);
        $remaining = [];
        foreach ($groups as $groupIndex => $group) {
            foreach ($areas as $areaIndex => $area) {
                $remaining[$groupIndex][$areaIndex] = $durationSlotsByGroup[$groupIndex][$areaIndex]
                    ?? intdiv($area['duration_minutes'], $slotMinutes);
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
        $activeArea = array_fill(0, count($groups), null);
        $completedAreas = array_fill(0, count($groups), 0);

        for ($slot = 0; $slot < $slotCount; $slot++) {
            $currentArea = array_fill(0, count($groups), null);
            $reservedAreas = [];

            foreach ($activeArea as $groupIndex => $areaIndex) {
                if ($areaIndex === null) {
                    continue;
                }
                $reservedAreas[$areaIndex] = $groupIndex;
                if (! $blockedSlots[$groupIndex][$slot]) {
                    $currentArea[$groupIndex] = $areaIndex;
                }
            }

            $availableGroups = array_values(array_filter(
                array_keys($groups),
                fn ($groupIndex) => ! $blockedSlots[$groupIndex][$slot]
                    && $activeArea[$groupIndex] === null
                    && array_sum($remaining[$groupIndex]) > 0
            ));
            usort($availableGroups, function ($left, $right) use ($futureFree, $remaining, $slot) {
                $leftSlack = $futureFree[$left][$slot] - array_sum($remaining[$left]);
                $rightSlack = $futureFree[$right][$slot] - array_sum($remaining[$right]);

                return [$leftSlack, $left] <=> [$rightSlack, $right];
            });

            foreach ($availableGroups as $groupIndex) {
                $orderedAreaIds = $areaOrders[$groups[$groupIndex]] ?? null;
                if ($orderedAreaIds !== null) {
                    $nextAreaId = collect($orderedAreaIds)->first(fn ($areaId) => collect($areas)->contains(
                        fn ($area, $areaIndex) => $area['bereich_id'] === $areaId
                            && $remaining[$groupIndex][$areaIndex] > 0
                    ));
                    $candidateAreas = array_values(array_filter(
                        array_keys($areas),
                        fn ($areaIndex) => $areas[$areaIndex]['bereich_id'] === $nextAreaId
                            && ! isset($reservedAreas[$areaIndex])
                    ));
                } else {
                    $candidateAreas = array_values(array_filter(
                        array_keys($areas),
                        fn ($areaIndex) => $remaining[$groupIndex][$areaIndex] > 0
                            && ! isset($reservedAreas[$areaIndex])
                    ));
                    $preferredArea = ($groupIndex + $completedAreas[$groupIndex]) % count($areas);
                    usort($candidateAreas, fn ($left, $right) => [
                        ($left - $preferredArea + count($areas)) % count($areas),
                        $left,
                    ] <=> [
                        ($right - $preferredArea + count($areas)) % count($areas),
                        $right,
                    ]);
                }

                foreach ($candidateAreas as $areaIndex) {
                    if (! $this->canCompleteBeforeNonBreakEvent(
                        $groupIndex,
                        $slot,
                        $remaining[$groupIndex][$areaIndex],
                        $blockedSlots
                    )) {
                        continue;
                    }
                    $activeArea[$groupIndex] = $areaIndex;
                    $reservedAreas[$areaIndex] = $groupIndex;
                    $currentArea[$groupIndex] = $areaIndex;
                    break;
                }
            }

            foreach ($currentArea as $groupIndex => $areaIndex) {
                if ($areaIndex === null) {
                    continue;
                }
                $assignments[$slot][$groupIndex] = $areaIndex;
                $remaining[$groupIndex][$areaIndex]--;
                if ($remaining[$groupIndex][$areaIndex] === 0) {
                    $activeArea[$groupIndex] = null;
                    $completedAreas[$groupIndex]++;
                }
            }
        }

        foreach ($remaining as $groupRemaining) {
            if (array_sum($groupRemaining) > 0) {
                return null;
            }
        }

        return $assignments;
    }

    private function canCompleteBeforeNonBreakEvent(
        int $groupIndex,
        int $startSlot,
        int $requiredSlots,
        array $blockedSlots
    ): bool {
        $availableSlots = 0;
        for ($slot = $startSlot; $slot < count($blockedSlots[$groupIndex]); $slot++) {
            $blockType = $blockedSlots[$groupIndex][$slot];
            if ($blockType && $blockType !== 'break') {
                return false;
            }
            if (! $blockType) {
                $availableSlots++;
            }
            if ($availableSlots >= $requiredSlots) {
                return true;
            }
        }

        return false;
    }

    private function fillIdleSlots(
        array $assignments,
        array $blockedSlots,
        int $groupCount,
        int $maximumDurationDifference = 5
    ): array
    {
        $slotCount = count($assignments);
        $durations = [];
        foreach ($assignments as $slotAssignments) {
            foreach ($slotAssignments as $groupIndex => $areaIndex) {
                $durations[$groupIndex][$areaIndex] = ($durations[$groupIndex][$areaIndex] ?? 0) + 1;
            }
        }
        $baseDuration = min(array_merge(...array_map('array_values', $durations)));
        $maximumDuration = $baseDuration + $maximumDurationDifference;

        for ($pass = 0; $pass < 2; $pass++) {
            for ($groupIndex = 0; $groupIndex < $groupCount; $groupIndex++) {
                for ($slot = 1; $slot < $slotCount; $slot++) {
                    if ($blockedSlots[$groupIndex][$slot] || isset($assignments[$slot][$groupIndex])) {
                        continue;
                    }
                    $previousArea = $assignments[$slot - 1][$groupIndex] ?? null;
                    if ($previousArea !== null
                        && ($durations[$groupIndex][$previousArea] ?? 0) < $maximumDuration
                        && $this->areaIsFreeAtSlot($assignments, $slot, $previousArea, $groupIndex)) {
                        $assignments[$slot][$groupIndex] = $previousArea;
                        $durations[$groupIndex][$previousArea]++;
                    }
                }
            }

            for ($groupIndex = 0; $groupIndex < $groupCount; $groupIndex++) {
                for ($slot = $slotCount - 2; $slot >= 0; $slot--) {
                    if ($blockedSlots[$groupIndex][$slot] || isset($assignments[$slot][$groupIndex])) {
                        continue;
                    }
                    $nextArea = $assignments[$slot + 1][$groupIndex] ?? null;
                    if ($nextArea !== null
                        && ($durations[$groupIndex][$nextArea] ?? 0) < $maximumDuration
                        && $this->areaIsFreeAtSlot($assignments, $slot, $nextArea, $groupIndex)) {
                        $assignments[$slot][$groupIndex] = $nextArea;
                        $durations[$groupIndex][$nextArea]++;
                    }
                }
            }
        }

        return $assignments;
    }

    private function areaIsFreeAtSlot(array $assignments, int $slot, int $areaIndex, int $exceptGroup): bool
    {
        foreach ($assignments[$slot] as $groupIndex => $assignedArea) {
            if ($groupIndex !== $exceptGroup && $assignedArea === $areaIndex) {
                return false;
            }
        }

        return true;
    }

    /**
     * Keep the number of already usable slots, but distribute them evenly over
     * every area before scheduling the rotations again. Different offsets are
     * tried so that the one-minute remainders do not create area conflicts.
     */
    private function rescheduleWithBalancedDurations(
        array $filledAssignments,
        array $groups,
        array $areas,
        array $areaOrders,
        array $blockedSlots,
        int $dayStart,
        int $dayEnd,
        int $slotMinutes
    ): ?array {
        $areaCount = count($areas);
        $totals = array_fill(0, count($groups), 0);
        foreach ($filledAssignments as $slotAssignments) {
            foreach ($slotAssignments as $groupIndex => $areaIndex) {
                $totals[$groupIndex]++;
            }
        }

        $areaIndexById = array_flip(array_map(fn ($area) => (int) $area['bereich_id'], $areas));
        for ($attempt = 0; $attempt < $areaCount; $attempt++) {
            $durationSlots = [];
            foreach ($groups as $groupIndex => $group) {
                $base = intdiv($totals[$groupIndex], $areaCount);
                $remainder = $totals[$groupIndex] % $areaCount;
                $durationSlots[$groupIndex] = array_fill(0, $areaCount, $base);
                $orderedIndexes = isset($areaOrders[$group])
                    ? array_values(array_map(fn ($areaId) => $areaIndexById[$areaId], $areaOrders[$group]))
                    : array_keys($areas);

                for ($extra = 0; $extra < $remainder; $extra++) {
                    $position = ($groupIndex + $attempt + $extra) % $areaCount;
                    $durationSlots[$groupIndex][$orderedIndexes[$position]]++;
                }
            }

            $assignments = $this->scheduleSlots(
                $groups,
                $areas,
                $areaOrders,
                $blockedSlots,
                $dayStart,
                $dayEnd,
                $slotMinutes,
                $durationSlots
            );
            if ($assignments !== null) {
                return $assignments;
            }
        }

        return null;
    }

    /**
     * Move boundaries between two consecutive areas until their total durations
     * differ by at most one slot wherever the area availability permits it.
     */
    private function balanceAreaDurations(array $assignments, int $groupCount, int $areaCount): array
    {
        $durations = array_fill(0, $groupCount, array_fill(0, $areaCount, 0));
        foreach ($assignments as $slotAssignments) {
            foreach ($slotAssignments as $groupIndex => $areaIndex) {
                $durations[$groupIndex][$areaIndex]++;
            }
        }

        $slotCount = count($assignments);
        $maximumMoves = max(1, $slotCount * $groupCount * $areaCount);

        for ($move = 0; $move < $maximumMoves; $move++) {
            $best = null;

            for ($groupIndex = 0; $groupIndex < $groupCount; $groupIndex++) {
                for ($slot = 1; $slot < $slotCount; $slot++) {
                    $leftArea = $assignments[$slot - 1][$groupIndex] ?? null;
                    $rightArea = $assignments[$slot][$groupIndex] ?? null;
                    if ($leftArea === null || $rightArea === null || $leftArea === $rightArea) {
                        continue;
                    }

                    $rightToLeftDifference = $durations[$groupIndex][$rightArea]
                        - $durations[$groupIndex][$leftArea];
                    if ($rightToLeftDifference > 1
                        && $this->areaIsFreeAtSlot($assignments, $slot, $leftArea, $groupIndex)
                        && ($best === null || $rightToLeftDifference > $best['difference'])) {
                        $best = [
                            'slot' => $slot,
                            'group' => $groupIndex,
                            'from' => $rightArea,
                            'to' => $leftArea,
                            'difference' => $rightToLeftDifference,
                        ];
                    }

                    $leftToRightDifference = $durations[$groupIndex][$leftArea]
                        - $durations[$groupIndex][$rightArea];
                    if ($leftToRightDifference > 1
                        && $this->areaIsFreeAtSlot($assignments, $slot - 1, $rightArea, $groupIndex)
                        && ($best === null || $leftToRightDifference > $best['difference'])) {
                        $best = [
                            'slot' => $slot - 1,
                            'group' => $groupIndex,
                            'from' => $leftArea,
                            'to' => $rightArea,
                            'difference' => $leftToRightDifference,
                        ];
                    }
                }
            }

            if ($best === null) {
                break;
            }

            $assignments[$best['slot']][$best['group']] = $best['to'];
            $durations[$best['group']][$best['from']]--;
            $durations[$best['group']][$best['to']]++;
        }

        return $assignments;
    }

    private function actualAreaDurations(array $assignments, array $groups, array $areas, int $slotMinutes): array
    {
        $durations = [];
        foreach (array_keys($groups) as $groupIndex) {
            foreach (array_keys($areas) as $areaIndex) {
                $slots = count(array_filter(
                    $assignments,
                    fn ($slotAssignments) => ($slotAssignments[$groupIndex] ?? null) === $areaIndex
                ));
                $durations[] = $slots * $slotMinutes;
            }
        }

        return $durations;
    }

    private function areaOrdersFromEntries(array $entries, array $groups): array
    {
        $orders = [];
        foreach ($groups as $group) {
            $orders[$group] = [];
            foreach ($entries as $entry) {
                if ($entry['type'] !== 'area' || $entry['group_key'] !== $group) {
                    continue;
                }
                $areaId = (int) $entry['bereich_id'];
                if (! in_array($areaId, $orders[$group], true)) {
                    $orders[$group][] = $areaId;
                }
            }
        }

        return $orders;
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

    private function bufferEntriesFromSlots(
        array $assignments,
        array $blockedSlots,
        array $groups,
        int $dayStart,
        int $slotMinutes
    ): array {
        $entries = [];
        $slotCount = count($assignments);

        foreach ($groups as $groupIndex => $group) {
            $segmentStart = null;
            for ($slot = 0; $slot <= $slotCount; $slot++) {
                $isBuffer = $slot < $slotCount
                    && ! $blockedSlots[$groupIndex][$slot]
                    && ! isset($assignments[$slot][$groupIndex]);
                if ($isBuffer && $segmentStart === null) {
                    $segmentStart = $slot;
                    continue;
                }
                if ($isBuffer || $segmentStart === null) {
                    continue;
                }

                $entries[] = [
                    'group_key' => $group,
                    'type' => 'buffer',
                    'title' => 'Wechsel-/Pufferzeit',
                    'bereich_id' => null,
                    'supervisor_person_id' => null,
                    'start_time' => $this->formatMinutes($dayStart + ($segmentStart * $slotMinutes)),
                    'end_time' => $this->formatMinutes($dayStart + ($slot * $slotMinutes)),
                    'meta' => ['automatically_created' => true],
                ];
                $segmentStart = null;
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
