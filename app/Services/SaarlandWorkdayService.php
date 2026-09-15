<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Yasumi\Yasumi;

class SaarlandWorkdayService
{
    /** @var array<int, array<string, string>> */
    private array $holidaysByYear = [];

    public function details(CarbonInterface|string $value): array
    {
        $date = $value instanceof CarbonInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value);
        $dateKey = $date->toDateString();
        $holidayName = $this->holidays($date->year)[$dateKey] ?? null;

        if ($holidayName) {
            return [
                'date' => $dateKey,
                'is_workday' => false,
                'type' => 'holiday',
                'name' => $holidayName,
                'label' => "Feiertag: {$holidayName}",
            ];
        }

        if ($date->isWeekend()) {
            $name = $date->isSaturday() ? 'Samstag' : 'Sonntag';

            return [
                'date' => $dateKey,
                'is_workday' => false,
                'type' => 'weekend',
                'name' => $name,
                'label' => "Wochenende: {$name}",
            ];
        }

        return [
            'date' => $dateKey,
            'is_workday' => true,
            'type' => 'workday',
            'name' => null,
            'label' => 'Regulaerer Arbeitstag',
        ];
    }

    /** Explicit exceptions are opt-in; missing options always exclude non-working days. */
    public function isAttendanceDay(CarbonInterface|string $value, array $options = [], array $confirmedDates = []): bool
    {
        $date = $value instanceof CarbonInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value);
        if (in_array($date->toDateString(), $confirmedDates, true)) {
            return true;
        }
        if ($date->isSaturday() && !filter_var($options['includeSaturday'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }
        if ($date->isSunday() && !filter_var($options['includeSunday'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return !isset($this->holidays($date->year)[$date->toDateString()])
            || filter_var($options['includeHolidays'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function isWorkday(CarbonInterface|string $value): bool
    {
        return $this->details($value)['is_workday'];
    }

    public function endDateForDuration(CarbonInterface|string $start, int $days): CarbonImmutable
    {
        $cursor = $start instanceof CarbonInterface
            ? CarbonImmutable::instance($start)
            : CarbonImmutable::parse($start);
        $remaining = max(1, $days) - 1;

        while ($remaining > 0) {
            $cursor = $cursor->addDay();
            if ($this->isWorkday($cursor)) {
                $remaining--;
            }
        }

        return $cursor;
    }

    public function nonWorkingDays(CarbonInterface|string $start, CarbonInterface|string $end): array
    {
        $cursor = $start instanceof CarbonInterface
            ? CarbonImmutable::instance($start)
            : CarbonImmutable::parse($start);
        $last = $end instanceof CarbonInterface
            ? CarbonImmutable::instance($end)
            : CarbonImmutable::parse($end);
        $days = [];

        while ($cursor->lte($last)) {
            $details = $this->details($cursor);
            if (! $details['is_workday']) {
                $days[] = $details;
            }
            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /** @return array<string, string> */
    public function holidays(int $year): array
    {
        if (isset($this->holidaysByYear[$year])) {
            return $this->holidaysByYear[$year];
        }

        $holidays = Yasumi::create('Germany/Saarland', $year, 'de_DE');
        $result = [];

        foreach ($holidays as $holiday) {
            // § 2 SFG: Yasumi also returns observances such as New Year's Eve.
            // Its Saarland provider currently classifies Assumption Day as "other".
            // https://www.kirchenrecht-rheinland.de/document/2954
            if ($holiday->getType() !== \Yasumi\Holiday::TYPE_OFFICIAL && $holiday->getKey() !== 'assumptionOfMary') {
                continue;
            }
            $result[$holiday->format('Y-m-d')] = $holiday->getName();
        }

        return $this->holidaysByYear[$year] = $result;
    }
}
