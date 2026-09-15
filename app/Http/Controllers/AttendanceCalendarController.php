<?php

namespace App\Http\Controllers;

use App\Services\SaarlandWorkdayService;
use Illuminate\Http\Request;

class AttendanceCalendarController extends Controller
{
    public function __invoke(Request $request, SaarlandWorkdayService $workdays)
    {
        $data = $request->validate(['year' => ['required', 'integer', 'min:1900', 'max:2199']]);
        return response()->json(['year' => (int) $data['year'], 'region' => 'Saarland',
            'holidays' => (object) $workdays->holidays((int) $data['year'])]);
    }
}
