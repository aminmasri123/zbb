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
    private function holidays(int $year): array
    {
        if (isset($this->holidaysByYear[$year])) {
            return $this->holidaysByYear[$year];
        }

        $holidays = Yasumi::create('Germany/Saarland', $year, 'de_DE');
        $result = [];

        foreach ($holidays as $holiday) {
            $result[$holiday->format('Y-m-d')] = $holiday->getName();
        }

        return $this->holidaysByYear[$year] = $result;
    }
}
