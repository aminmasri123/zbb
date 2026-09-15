<?php

namespace Tests\Feature;

use App\Http\Controllers\EinteilungBereicheController;
use App\Http\Controllers\EinteilungParameterController;
use App\Models\Tage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AttendanceCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_is_available_to_authenticated_users_without_project_specific_permissions(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('attendance.calendar', ['year' => 2026]))
            ->assertOk()->assertJsonPath('region', 'Saarland')
            ->assertJsonPath('holidays.2026-05-14', 'Christi Himmelfahrt');
        $this->getJson(route('attendance.calendar', ['year' => 5000]))->assertUnprocessable();
    }

    public function test_both_group_generation_paths_skip_weekends_and_holidays(): void
    {
        foreach ([EinteilungParameterController::class, EinteilungBereicheController::class] as $controller) {
            $ids = (new ReflectionMethod($controller, 'tageIds'))->invoke(app($controller), Carbon::parse('2026-05-13'), Carbon::parse('2026-05-18'));
            $this->assertSame(['2026-05-13', '2026-05-15', '2026-05-18'], Tage::whereIn('id', $ids)->orderBy('datum')->pluck('datum')->all());
        }
    }
}
