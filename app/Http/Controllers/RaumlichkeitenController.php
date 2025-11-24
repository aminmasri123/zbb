<?php

namespace App\Http\Controllers;

use Exception;
use Inertia\Inertia;
use App\Models\Raeume;
use App\Models\Standort;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RaumlichkeitenController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $standorte = Standort::with('raeume', 'adresse')->orderBy('name')->get();
        return Inertia::render('Raum/Index', ['standorte' => $standorte]);
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
        // Validierung
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'standort_id'   => 'required|exists:standorts,id',
            'typ'           => 'required|in:Büro,Elektroraum,Unterrichtsraum,Seminarraum,Besprechungsraum,Labor,Werkstatt,Lager,Küche,Aufenthaltsraum,Sanitärraum,Empfang,Serverraum,Archiv,Aula,Bibliothek,Arbeitsplatz,Copyroom,Technikraum,Hauswirtschaftsraum,Holzbereich,Metallbereich',
            'kapazitaet'    => 'nullable|integer|min:0',
            'beschreibung'  => 'nullable|string|max:1000',
        ]);

        try {

            // Raum speichern
            $raum = Raeume::create([
                'name'          => $validated['name'],
                'standort_id'   => $validated['standort_id'],
                'typ'           => $validated['typ'],
                'kapazitaet'    => $validated['kapazitaet'] ?? null,
                'beschreibung'  => $validated['beschreibung'] ?? null,
            ]);

            // Standort inkl. aller Räume zurückgeben
            $standort = Standort::with('raeume')->find($validated['standort_id']);

            return response()->json([
    'message' => 'Raum erfolgreich erstellt.',
    'raum'    => $raum->load('standort')
], 201);


        } catch (\Exception $e) {

            return response()->json([
                'error'   => 'Beim Erstellen des Raumes ist ein Fehler aufgetreten.',
                'details' => $e->getMessage(),
            ], 500);
        }
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
    public function update(Request $request, $id)
{

dd($request);
    // Validierung
    $validated = $request->validate([
        'name'          => 'required|string|max:100',
        'standort_id'   => 'required|exists:standorts,id',
        'typ'           => 'required|in:Büro,Elektroraum,Unterrichtsraum,Seminarraum,Besprechungsraum,Labor,Werkstatt,Lager,Küche,Aufenthaltsraum,Sanitärraum,Empfang,Serverraum,Archiv,Aula,Bibliothek,Arbeitsplatz,Copyroom,Technikraum,Hauswirtschaftsraum,Holzbereich,Metallbereich',
        'kapazitaet'    => 'nullable|integer|min:0',
        'beschreibung'  => 'nullable|string|max:1000',
    ]);

    try {
        // Raum finden
        $raum = Raeume::findOrFail($id);

        // Raum aktualisieren
        $raum->update([
            'name'          => $validated['name'],
            'standort_id'   => $validated['standort_id'],
            'typ'           => $validated['typ'],
            'kapazitaet'    => $validated['kapazitaet'] ?? null,
            'beschreibung'  => $validated['beschreibung'] ?? null,
        ]);

        // aktualisiertes Modell + Standort zurückgeben
        return response()->json([
            'message' => 'Raum erfolgreich aktualisiert.',
            'raum'    => $raum->load('standort'),
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'error'   => 'Beim Aktualisieren des Raumes ist ein Fehler aufgetreten.',
            'details' => $e->getMessage(),
        ], 500);
    }
}


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $raum = Raeume::findOrFail($id);
            $raum->delete();

            return response()->json(['message' => 'der Raum: ' . $raum->name . ' wurde  erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Die Daten konnte nicht gefunden werden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
