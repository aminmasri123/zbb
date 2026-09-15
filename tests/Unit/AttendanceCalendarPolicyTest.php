<?php

namespace Tests\Unit;

use App\Http\Controllers\ProjektBopController;
use App\Services\SaarlandWorkdayService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class AttendanceCalendarPolicyTest extends TestCase
{
    public function test_weekends_and_holidays_are_opt_in_independently(): void
    {
        $calendar = new SaarlandWorkdayService;
        $this->assertTrue($calendar->isAttendanceDay('2026-09-18'));
        foreach (['2026-09-19', '2026-09-20', '2026-05-14', '2026-08-15'] as $date) {
            $this->assertFalse($calendar->isAttendanceDay($date), $date);
        }
        $this->assertTrue($calendar->isAttendanceDay('2026-09-19', ['includeSaturday' => true]));
        $this->assertFalse($calendar->isAttendanceDay('2026-09-20', ['includeSaturday' => true]));
        $this->assertFalse($calendar->isAttendanceDay('2026-08-15', ['includeSaturday' => true]));
        $this->assertFalse($calendar->isAttendanceDay('2026-08-15', ['includeHolidays' => true]));
        $this->assertTrue($calendar->isAttendanceDay('2026-08-15', ['includeSaturday' => true, 'includeHolidays' => true]));
        $this->assertTrue($calendar->isAttendanceDay('2026-05-14', [], ['2026-05-14']));
        $this->assertFalse($calendar->isAttendanceDay('2026-09-19', ['includeSaturday' => 'false']));
    }

    public function test_old_bibb_candidates_are_filtered_before_the_ten_day_limit(): void
    {
        $days = collect(CarbonPeriod::create('2026-09-14', '2026-09-27'))
            ->map(fn ($date) => ['date' => $date->toDateString(), 'selected' => true])->all();
        [$dates, $feedback] = $this->bibb(['days' => $days, 'feedbackDate' => '2026-09-27']);
        $this->assertSame(['14.09.2026', '15.09.2026', '16.09.2026', '17.09.2026', '18.09.2026',
            '21.09.2026', '22.09.2026', '23.09.2026', '24.09.2026', '25.09.2026'], $dates);
        $this->assertSame('', $feedback);
    }

    public function test_only_statutory_saarland_holidays_are_excluded_across_years(): void
    {
        $calendar = new SaarlandWorkdayService;
        $this->assertCount(12, $calendar->holidays(2026));
        $this->assertTrue($calendar->isWorkday('2026-12-31'));
        $this->assertTrue($calendar->isWorkday('2026-12-24'));
        $this->assertFalse($calendar->isWorkday('2027-01-01'));
        $this->assertSame('holiday', $calendar->details('2026-08-15')['type']);
    }

    public function test_bibb_holidays_legacy_dates_and_deselected_days_follow_the_same_policy(): void
    {
        [$dates] = $this->bibb(['termin1' => '2026-05-14', 'termin2' => '2026-05-15', 'termin3' => '2026-05-16']);
        $this->assertSame(['15.05.2026'], $dates);
        [$dates] = $this->bibb(['days' => [['date' => '2026-05-14'], ['date' => '2026-05-15', 'selected' => false]], 'termin1' => '2026-05-18']);
        $this->assertSame([], $dates, 'Filtered days must not fall back to stale legacy dates.');
        [$dates, $feedback] = $this->bibb(['days' => [['date' => '2026-05-14']], 'includeHolidays' => true, 'feedbackDate' => '2026-05-14']);
        $this->assertSame(['14.05.2026'], $dates);
        $this->assertSame('14.05.2026', $feedback);
    }

    private function bibb(array $input): array
    {
        return (new ReflectionMethod(ProjektBopController::class, 'bibbDateListFromRequest'))
            ->invoke(app(ProjektBopController::class), new Request($input));
    }
}
