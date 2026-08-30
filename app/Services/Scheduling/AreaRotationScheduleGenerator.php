<?php

namespace App\Services\Scheduling;

use DomainException;

class AreaRotationScheduleGenerator
{
    /**
     * Generate a daily rotation. Shared events block every group, while an area
     * can occur at most once in a rotation window.
     */
    public function generate(array $input): array
    {
        $slotMinutes = (int) ($input['slot_minutes'] ?? 15);
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
        if (! in_array($slotMinutes, [5, 10, 15, 30], true)) {
            throw new DomainException('Das Zeitraster muss 5, 10, 15 oder 30 Minuten betragen.');
        }
        if ($groups === []) {
            throw new DomainException('Bitte mindestens eine Gruppe angeben.');
        }
        if ($areas === []) {
            throw new DomainException('Bitte mindestens einen Bereich auswählen.');
        }

        $events = $this->normaliseEvents($input['events'] ?? [], $dayStart, $dayEnd, $slotMinutes);
        usort($events, fn ($left, $right) => $left['start'] <=> $right['start']);
        $this->assertEventsDoNotOverlap($events);

        $roundCount = max(count($groups), count($areas));
        $automaticDuration = count(array_filter(
            $areas,
            fn ($area) => ! isset($area['duration_minutes']) || (int) $area['duration_minutes'] <= 0
        )) > 0;
        $calculatedDuration = $automaticDuration
            ? $this->automaticRoundDuration($dayStart, $dayEnd, $events, $roundCount, $slotMinutes)
            : null;

        foreach ($areas as $index => &$area) {
            $area['bereich_id'] = (int) $area['bereich_id'];
            $area['name'] = trim((string) ($area['name'] ?? ('Bereich '.$area['bereich_id'])));
            $area['duration_minutes'] = $calculatedDuration ?? (int) $area['duration_minutes'];
            $area['supervisor_person_id'] = ($area['supervisor_person_id'] ?? null) !== null
                && ($area['supervisor_person_id'] ?? '') !== ''
                ? (int) $area['supervisor_person_id'] : null;
            $area['supervisor_name'] = trim((string) ($area['supervisor_name'] ?? ''));

            if ($area['duration_minutes'] < $slotMinutes || $area['duration_minutes'] % $slotMinutes !== 0) {
                throw new DomainException("Die Dauer für {$area['name']} muss ein Vielfaches des Zeitrasters sein.");
            }
            if ($area['duration_minutes'] > ($dayEnd - $dayStart)) {
                throw new DomainException("Die Dauer für {$area['name']} ist länger als der Planungstag.");
            }
            $area['_index'] = $index;
        }
        unset($area);

        if (count(array_unique(array_column($areas, 'bereich_id'))) !== count($areas)) {
            throw new DomainException('Ein Bereich wurde mehrfach ausgewählt.');
        }

        $roundDuration = max(array_column($areas, 'duration_minutes'));
        $availableMinutes = ($dayEnd - $dayStart) - array_sum(array_map(
            fn ($event) => $event['end'] - $event['start'],
            $events
        ));
        $cursor = $dayStart;
        $entries = array_map(fn ($event) => [
            'group_key' => null,
            'type' => $event['type'],
            'title' => $event['title'],
            'bereich_id' => null,
            'supervisor_person_id' => null,
            'start_time' => $this->formatMinutes($event['start']),
            'end_time' => $this->formatMinutes($event['end']),
            'meta' => ['group_labels' => $groups],
        ], $events);

        for ($round = 0; $round < $roundCount; $round++) {
            $roundStart = $this->nextAvailableWindow($cursor, $roundDuration, $events, $dayEnd);
            $roundEnd = $roundStart + $roundDuration;

            foreach ($groups as $groupIndex => $group) {
                $areaIndex = ($groupIndex + $round) % $roundCount;
                if ($areaIndex >= count($areas)) {
                    continue;
                }

                $area = $areas[$areaIndex];
                $entries[] = [
                    'group_key' => $group,
                    'type' => 'area',
                    'title' => $area['name'],
                    'bereich_id' => $area['bereich_id'],
                    'supervisor_person_id' => $area['supervisor_person_id'],
                    'start_time' => $this->formatMinutes($roundStart),
                    'end_time' => $this->formatMinutes($roundStart + $area['duration_minutes']),
                    'meta' => array_filter([
                        'round' => $round + 1,
                        'supervisor_name' => $area['supervisor_name'] ?: null,
                    ], fn ($value) => $value !== null),
                ];
            }

            $cursor = $roundEnd;
        }

        $this->assertGeneratedEntriesDoNotConflict($entries);

        usort($entries, fn ($left, $right) => [
            $left['start_time'], $left['group_key'] ?? '', $left['title'],
        ] <=> [
            $right['start_time'], $right['group_key'] ?? '', $right['title'],
        ]);

        return [
            'schedule_date' => $input['schedule_date'] ?? null,
            'slot_minutes' => $slotMinutes,
            'config' => [
                'start_time' => $this->formatMinutes($dayStart),
                'end_time' => $this->formatMinutes($dayEnd),
                'groups' => $groups,
                'duration_mode' => $automaticDuration ? 'automatic' : 'manual',
                'calculated_area_duration_minutes' => $automaticDuration ? $roundDuration : null,
                'rotation_count' => $roundCount,
                'unallocated_minutes' => max(0, $availableMinutes - ($roundCount * $roundDuration)),
                'areas' => array_map(fn ($area) => array_diff_key($area, ['_index' => true]), $areas),
                'events' => array_map(fn ($event) => [
                    'title' => $event['title'],
                    'type' => $event['type'],
                    'start_time' => $this->formatMinutes($event['start']),
                    'end_time' => $this->formatMinutes($event['end']),
                ], $events),
            ],
            'entries' => $entries,
        ];
    }

    private function automaticRoundDuration(
        int $dayStart,
        int $dayEnd,
        array $events,
        int $roundCount,
        int $slotMinutes
    ): int {
        $blockedMinutes = array_sum(array_map(fn ($event) => $event['end'] - $event['start'], $events));
        $availableMinutes = ($dayEnd - $dayStart) - $blockedMinutes;
        $largestCandidate = intdiv(intdiv($availableMinutes, $roundCount), $slotMinutes) * $slotMinutes;

        for ($duration = $largestCandidate; $duration >= $slotMinutes; $duration -= $slotMinutes) {
            $cursor = $dayStart;
            try {
                for ($round = 0; $round < $roundCount; $round++) {
                    $cursor = $this->nextAvailableWindow($cursor, $duration, $events, $dayEnd) + $duration;
                }

                return $duration;
            } catch (DomainException) {
                // Try the next smaller duration when fixed activities fragment the day.
            }
        }

        throw new DomainException('Nach Abzug der Pausen und gemeinsamen Aktivitäten bleibt nicht genug Zeit für alle Bereichsrotationen.');
    }

    private function normaliseEvents(array $events, int $dayStart, int $dayEnd, int $slotMinutes): array
    {
        return array_values(array_map(function (array $event) use ($dayStart, $dayEnd, $slotMinutes) {
            $start = $this->toMinutes((string) $event['start_time']);
            $end = $this->toMinutes((string) $event['end_time']);
            $title = trim((string) ($event['title'] ?? ''));
            $type = (string) ($event['type'] ?? 'shared');

            if ($title === '') {
                throw new DomainException('Jede gemeinsame Aktivität benötigt einen Namen.');
            }
            if (! in_array($type, ['shared', 'break', 'extra'], true)) {
                throw new DomainException("Die Aktivitätsart für {$title} ist ungültig.");
            }
            if ($start < $dayStart || $end > $dayEnd || $end <= $start) {
                throw new DomainException("Die Zeit für {$title} liegt außerhalb des Planungstages.");
            }
            if (($start - $dayStart) % $slotMinutes !== 0 || ($end - $dayStart) % $slotMinutes !== 0) {
                throw new DomainException("Die Zeit für {$title} muss zum gewählten Zeitraster passen.");
            }

            return ['title' => $title, 'type' => $type, 'start' => $start, 'end' => $end];
        }, $events));
    }

    private function assertEventsDoNotOverlap(array $events): void
    {
        foreach ($events as $leftIndex => $left) {
            foreach ($events as $rightIndex => $right) {
                if ($rightIndex <= $leftIndex) {
                    continue;
                }
                if ($this->overlaps($left['start'], $left['end'], $right['start'], $right['end'])) {
                    throw new DomainException("Die gemeinsamen Aktivitäten {$left['title']} und {$right['title']} überschneiden sich.");
                }
            }
        }
    }

    private function nextAvailableWindow(int $cursor, int $duration, array $events, int $dayEnd): int
    {
        while ($cursor + $duration <= $dayEnd) {
            $blockingEvent = null;
            foreach ($events as $event) {
                if ($this->overlaps($cursor, $cursor + $duration, $event['start'], $event['end'])) {
                    $blockingEvent = $event;
                    break;
                }
            }
            if (! $blockingEvent) {
                return $cursor;
            }
            $cursor = $blockingEvent['end'];
        }

        throw new DomainException('Der Zeitraum reicht für alle Bereichsrotationen nicht aus. Bitte den Tag verlängern, Bereiche kürzen oder Gruppen reduzieren.');
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
