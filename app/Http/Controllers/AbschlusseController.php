<?php

namespace App\Http\Controllers;

use App\Models\Personen;
use Illuminate\Http\Request;
use App\Models\PersonenHasAbschluesse;

class AbschlusseController extends Controller
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
            'person_id' => 'required|exists:personens,id',
            'abschluss_id' => 'required|exists:abschluesses,id',
            'bezeichnung' => 'required|string',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);

        PersonenHasAbschluesse::firstOrCreate([
            'person_id' => $validated['person_id'], // Suchkriterium
            'abschluss_id' => $validated['abschluss_id'],  // Werte, die gesetzt werden
            'bezeichnung' => $validated['bezeichnung'],
            'start' => $validated['start'] ?? null,
            'end' => $validated['end'] ?? null,
        ]);

        return back()->with('success', 'Abschluss gespeichert.');
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
   public function destroy($id)
    {
        PersonenHasAbschluesse::where('id', $id)->delete();



        return response()->json(['message' => 'Kontakt erfolgreich entfernt.'], 200);


    }
}
