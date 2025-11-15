<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DienwagenreportController extends Controller
{
    public function index()
    {
        return Inertia::render('Fleet/Reports/Index', [
            'vehicles' => Vehicle::with(['tripRecords', 'costRecords'])->get()
        ]);
    }

    public function storeTrip(Request $request)
    {
        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id'  => 'nullable|exists:drivers,id',
            'date'       => 'required|date',
            'start_km'   => 'required|integer',
            'end_km'     => 'required|integer',
            'purpose'    => 'required|string',
            'destination'=> 'required|string',
        ]);

        TripRecord::create($data);

        return back()->with('success', 'Fahrt erfasst.');
    }
}
