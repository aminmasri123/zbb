<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Fahrtarten;
use Illuminate\Http\Request;
use App\Models\Fahrtkostensaetze;

class FahrtkostensaetzeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $fahrtkosten = Fahrtkostensaetze::with('fahrtart')->get();
        $fahrtarten = Fahrtarten::all();
        return Inertia::render('Fahrten/Fahrtkosten/Index',
            ['fahrtkosten' => $fahrtkosten,
            'fahrtarten' => $fahrtarten
            ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'fahrtart_id' => 'required|integer|exists:fahrtartens,id',
            'rechentyp'   => 'required|string|in:pro_km,pro_fahrt,pro_monat,prozent',
            'ab' => 'required|date',
            'bis' => 'required|date|after_or_equal:ab',
            'beschreibung' => 'nullable|string',
            'satz' => 'required|numeric',

        ]);

        $ab  = Carbon::parse($request->ab)->format('Y-m-d');
        $bis = Carbon::parse($request->bis)->format('Y-m-d');

        $fahrtkosten = Fahrtkostensaetze::create([
            'fahrtart_id' => $request->fahrtart_id,
            'rechentyp' => $request->rechentyp,
            'satz' => $request->satz,
            'gueltig_ab' => $ab,
            'gueltig_bis' => $bis,
            'bemerkung' => $request->bemerkung,
        ]);

        return response()->json(['fahrtkosten' => $fahrtkosten->load('fahrtart')]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        try {
            $fahrtkosten = Fahrtkostensaetze::findOrFail($id);
            $fahrtkosten->delete();

            return response()->json(['message' => 'Fahrtkosten erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Fahrtkosten nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
