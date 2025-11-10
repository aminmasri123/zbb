<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Fahrten;
use App\Models\Abrechnungen;
use Illuminate\Http\Request;
use App\Models\Fahrtkostensaetze;
use Illuminate\Support\Facades\Auth;

class FahrtkostenAbrechnenController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    $validated = $request->validate([
        'teilnehmer_id' => 'required|integer|exists:personens,id',
        'fahrtarten_id' => 'required|integer|exists:fahrtartens,id',
        'tag' => 'required|date',
        'start' => 'nullable|string',
        'ziel' => 'nullable|string',
        'entfernung'=> 'nullable|numeric',
    ]);

    $kostenSatz = Fahrtkostensaetze::where('fahrtart_id', $validated['fahrtarten_id'])
        ->whereDate('gueltig_ab', '<=', $validated['tag'])
        ->whereDate('gueltig_bis', '>=', $validated['tag'])
        ->first();

    if (!$kostenSatz) {
        return response()->json([
            'message' => 'Kein gültiger Kostensatz für dieses Datum gefunden.'
        ], 404);
    }

    // 🧮 Kostenberechnung
    switch ($kostenSatz->rechentyp) {
        case 'pro_monat':
            $gesamtkosten = $kostenSatz->satz;
            break;
        case 'pro_km':
            $gesamtkosten = $kostenSatz->satz * $validated['entfernung'];
            break;
        case 'pro_fahrt':
            $gesamtkosten = $kostenSatz->satz;
            break;
        case 'prozent':
            $gesamtkosten = $kostenSatz->satz; // TODO: später Prozentsatz einbauen
            break;
        default:
            $gesamtkosten = 0;
    }

    $person = Auth::user()->person;

    $fahrtabrechnung = Fahrten::create([
        'person_id' => $validated['teilnehmer_id'],
        'personal_id' => $person->id,
        'fahrtart_id' => $validated['fahrtarten_id'],
        'datum' => $validated['tag'],
        'start' => $validated['start'] ?? null,
        'ziel' => $validated['ziel'] ?? null,
        'entfernung_km' => $validated['entfernung'] ?? 0,
        'status'=> 'offen',
        'kosten_berechnet' => $gesamtkosten,
    ]);

    return response()->json([
        'message' => 'Fahrtabrechnung erfolgreich hinzugefügt!',
        'fahrtabrechnung' => $fahrtabrechnung->load(['fahrtarten', 'personal']),
    ]);
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $fahrtabrechnung = Fahrten::findOrFail($id);
            $fahrtabrechnung->delete();

            return response()->json(['message' => 'Fahrtabrechnung erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Fahrtabrechnung nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
