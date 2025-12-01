<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\PersonenHasBildungsmassnahmen;

class PersonenHasBildungsmassnahmenController extends Controller
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

        // 🔹 1. Validierung
        $validator = Validator::make($request->all(), [
            'teilnehmer_id' => 'required|integer|exists:personens,id',
            'typ'           => 'required|string|max:255',
            'traeger'       => 'nullable|string|max:255',
            'start'         => 'required|date',
            'end'           => 'required|date|after_or_equal:start',
            'bemerkung'     => 'nullable|string',
            'status'        => 'required|in:geplant,laufend,abgeschlossen,abgebrochen',
        ]);


        // ❌ Rückgabe bei Fehler – für axios (JSON!)
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Bitte alle Felder korrekt ausfüllen!',
                'errors'  => $validator->errors(),
            ], 422);
        }
        $validated = $validator->validated();


        // 🔹 2. ISO-Daten in normales Datum umwandeln (Vue Calendar liefert ISO 8601)
        $validated['start'] = Carbon::parse($validated['start'])->toDateString();
        $validated['end']   = Carbon::parse($validated['end'])->toDateString();

        // 🔹 3. Maßnahme in DB speichern
        $massnahme = PersonenHasBildungsmassnahmen::create([
            'person_id' => $validated['teilnehmer_id'],
            'typ'           => $validated['typ'],
            'traeger'       => $validated['traeger'],
            'start'         => $validated['start'],
            'end'          => $validated['end'],
            'bemerkung'     => $validated['bemerkung'],
            'status'        => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Bildungsmaßnahme erfolgreich erstellt.',
            'data' => $massnahme
        ], 201);
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
        //
    }
}
