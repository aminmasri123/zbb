<?php

namespace Tests\Unit;

use App\Http\Controllers\BopGruppeExportController;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ReflectionMethod;
use Tests\TestCase;

class BopGroupAttendanceExportTest extends TestCase
{
    public function test_attendance_export_skips_weekend_between_friday_and_tuesday(): void
    {
        $controller = app(BopGruppeExportController::class);
        $days = $this->invokePrivate($controller, 'attendanceDays', [
            Carbon::parse('2026-09-04'),
            Carbon::parse('2026-09-08'),
        ]);

        $this->assertSame(
            ['2026-09-04', '2026-09-07', '2026-09-08'],
            array_map(fn (Carbon $day) => $day->toDateString(), $days),
        );

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $this->invokePrivate($controller, 'writeAttendanceDayHeaders', [$sheet, $days]);

        $this->assertSame('Fr', $sheet->getCell('G5')->getValue());
        $this->assertSame('04.09.2026', $sheet->getCell('H5')->getValue());
        $this->assertSame('Mo', $sheet->getCell('K5')->getValue());
        $this->assertSame('07.09.2026', $sheet->getCell('L5')->getValue());
        $this->assertSame('Di', $sheet->getCell('O5')->getValue());
        $this->assertSame('08.09.2026', $sheet->getCell('P5')->getValue());
    }

    public function test_explicitly_confirmed_weekend_workday_remains_in_export(): void
    {
        $days = $this->invokePrivate(
            app(BopGruppeExportController::class),
            'attendanceDays',
            [
                Carbon::parse('2026-09-04'),
                Carbon::parse('2026-09-07'),
                ['2026-09-05'],
            ],
        );

        $this->assertSame(
            ['2026-09-04', '2026-09-05', '2026-09-07'],
            array_map(fn (Carbon $day) => $day->toDateString(), $days),
        );
    }

    private function invokePrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
