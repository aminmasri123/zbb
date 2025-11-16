<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Dienstwagen;
use Illuminate\Http\Request;

class DienstwagenreportsController extends Controller
{
   public function index(Request $request)
{
    // Falls ein Monat ausgewählt wurde → diesen verwenden
    $month = $request->get('monat', now()->format('Y-m'));

    // Monat Start + Ende berechnen
    $start = now()->parse($month . '-01')->startOfMonth();
    $end   = now()->parse($month . '-01')->endOfMonth();

    return Inertia::render('Dienstwagen/Reports/Index', [
        'vehicles' => Dienstwagen::with([

            // NUR DATEN DES MONATS LADEN
            'wartungen' => function($q) use ($start, $end) {
                $q->whereBetween('datum', [$start, $end]);
            },

            'kostanaufzeichnungen' => function($q) use ($start, $end) {
                $q->whereBetween('datum', [$start, $end]);
            },

            'fahrten' => function($q) use ($start, $end) {
                $q->whereBetween('date', [$start, $end]);
            },

            'standort',

        ])->get(),

        'currentMonth' => $month,
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
