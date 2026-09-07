<?php

namespace Tests\Feature;

use App\Http\Middleware\IncreaseBibbAttendanceMemory;
use Illuminate\Http\Request;
use Tests\TestCase;

class BibbAttendanceMemoryTest extends TestCase
{
    private string $originalLimit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLimit = (string) ini_get('memory_limit');
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->originalLimit);
        parent::tearDown();
    }

    public function test_all_bibb_endpoints_get_ten_times_the_previous_limit_before_processing(): void
    {
        foreach (['', '/preview', '/draft', '/archive-folder', '/pdf-folder'] as $suffix) {
            ini_set('memory_limit', '128M');
            (new IncreaseBibbAttendanceMemory)->handle(
                Request::create('/export-anwesenheitsliste-pobo'.$suffix, 'POST'),
                function () {
                    $this->assertSame('1280M', ini_get('memory_limit'));
                    return response('ok');
                }
            );
        }
    }

    public function test_higher_and_unlimited_settings_are_preserved(): void
    {
        foreach (['2G', '-1'] as $limit) {
            ini_set('memory_limit', $limit);
            (new IncreaseBibbAttendanceMemory)->handle(
                Request::create('/export-anwesenheitsliste-pobo/draft', 'PUT'),
                fn () => response('ok')
            );
            $this->assertSame($limit, ini_get('memory_limit'));
        }
    }

    public function test_other_requests_keep_their_memory_limit(): void
    {
        ini_set('memory_limit', '128M');
        (new IncreaseBibbAttendanceMemory)->handle(Request::create('/dashboard'), fn () => response('ok'));
        $this->assertSame('128M', ini_get('memory_limit'));
    }
}
