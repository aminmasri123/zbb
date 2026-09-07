<?php

namespace App\Http\Controllers;

use App\Models\Gruppe;
use App\Services\Bop\GroupAttendanceSignatures;
use Illuminate\Http\Request;

class GroupAttendanceSignatureController extends Controller
{
    public function index(Request $request, Gruppe $gruppe, GroupAttendanceSignatures $service)
    {
        return response()->json(['lists' => $service->lists($gruppe, $request->user())]);
    }

    public function show(Request $request, Gruppe $gruppe, GroupAttendanceSignatures $service)
    {
        $input = $request->validate(['type' => 'required|in:pa,bibb', 'draft_id' => 'required|integer', 'date' => 'required|date_format:Y-m-d']);
        return response()->json(['rows' => $service->show($gruppe, $request->user(), $input['type'], $input['draft_id'], $input['date'])]);
    }

    public function store(Request $request, Gruppe $gruppe, GroupAttendanceSignatures $service)
    {
        $input = $request->validate([
            'type' => 'required|in:pa,bibb', 'draft_id' => 'required|integer', 'date' => 'required|date_format:Y-m-d',
            'key' => 'required|string|max:255', 'signature' => 'required|string|max:1000000',
        ]);
        $service->store($gruppe, $request, $input);
        return response()->json(['success' => true]);
    }
}
