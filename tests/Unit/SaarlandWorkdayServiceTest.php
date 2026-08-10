<?php

namespace Tests\Unit;

use App\Services\SaarlandWorkdayService;
use PHPUnit\Framework\TestCase;

class SaarlandWorkdayServiceTest extends TestCase
{
    public function test_two_day_group_starting_on_friday_ends_on_monday(): void
    {
        $service = new SaarlandWorkdayService();

        $this->assertSame(
            '2026-09-14',
            $service->endDateForDuration('2026-09-11', 2)->toDateString(),
        );
    }

    public function test_public_holiday_is_skipped_by_automatic_duration(): void
    {
        $service = new SaarlandWorkdayService();

        $this->assertSame(
            '2026-05-15',
            $service->endDateForDuration('2026-05-13', 2)->toDateString(),
        );
    }

    public function test_weekends_and_saarland_holidays_are_reported_as_non_working_days(): void
    {
        $service = new SaarlandWorkdayService();
        $days = collect($service->nonWorkingDays('2026-08-14', '2026-08-17'))->keyBy('date');

        $this->assertSame('holiday', $days->get('2026-08-15')['type']);
        $this->assertSame('weekend', $days->get('2026-08-16')['type']);
        $this->assertFalse($days->has('2026-08-17'));
    }
}
