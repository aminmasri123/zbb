<?php

namespace App\Http\Controllers;

use App\Models\Zeitraum;
use App\Models\Teilnehmer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class ProjektHasTeilnehmerController extends Controller
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
            'teilnehmer_id' => ['required', 'exists:teilnehmers,id'],
            'projekt_id' => ['required', 'exists:projekts,id'],
            'antragsdatum' => ['nullable', 'date'],
            'starttermin' => ['nullable', 'date'],
            'endtermin' => ['nullable', 'date'],
            'anfangsdatum' => ['nullable', 'date'],
            'enddatum' => ['nullable', 'date'],
        ]);

        DB::beginTransaction();

        try {
            $teilnehmer = Teilnehmer::findOrFail($validated['teilnehmer_id']);

            // Prüfen, ob bereits zugewiesen
            $existingPivot = DB::table('projekt_has_teilnehmers')
                ->where('teilnehmer_id', $validated['teilnehmer_id'])
                ->where('projekt_id', $validated['projekt_id'])
                ->first();

            if ($existingPivot) {
                // Falls bereits zugewiesen → nur Zeitraum hinzufügen
                Zeitraum::create([
                    'antragsdatum' => $validated['antragsdatum'] ?? null,
                    'starttermin' => $validated['starttermin'] ?? null,
                    'endtermin' => $validated['endtermin'] ?? null,
                    'anfangsdatum' => $validated['anfangsdatum'] ?? null,
                    'enddatum' => $validated['enddatum'] ?? null,
                    'model_type' => 'App\Models\ProjektHasTeilnehmer',
                    'model_id' => $existingPivot->id,
                ]);

                DB::commit();
                return back()->with('success', 'Zeitraum zum bestehenden Projekt hinzugefügt!');
            }

            // Pivot-Eintrag erstellen
            $teilnehmer->projekte()->attach($validated['projekt_id']);

            // Pivot-ID ermitteln
            $pivotId = DB::table('projekt_has_teilnehmers')
                ->where('teilnehmer_id', $validated['teilnehmer_id'])
                ->where('projekt_id', $validated['projekt_id'])
                ->latest('id')
                ->value('id');

            // Zeitraum anlegen
            Zeitraum::create([
                'antragsdatum' => $validated['antragsdatum'] ?? null,
                'starttermin' => $validated['starttermin'] ?? null,
                'endtermin' => $validated['endtermin'] ?? null,
                'anfangsdatum' => $validated['anfangsdatum'] ?? null,
                'enddatum' => $validated['enddatum'] ?? null,
                'model_type' => 'App\Models\ProjektHasTeilnehmer',
                'model_id' => $pivotId,
            ]);

            DB::commit();

            return back()->with('success', 'Projekt erfolgreich zugewiesen!');
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Fehler beim Zuweisen eines Projekts: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'data' => $validated,
            ]);

            return back()->with('error', 'Beim Zuweisen des Projekts ist ein Fehler aufgetreten.');
        }
    }


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
