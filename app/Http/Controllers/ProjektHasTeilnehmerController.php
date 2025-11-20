<?php

namespace App\Http\Controllers;

use Throwable;
use Carbon\Carbon;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Zeitraum;
use Illuminate\Http\Request;
use App\Models\ProjektHasPersonen;
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
            'teilnehmer_id' => ['required', 'exists:personens,id'],
            'projekt_id'    => ['required', 'exists:projekts,id'],
            'antragsdatum'  => ['nullable', 'date'],
            'starttermin'   => ['nullable', 'date'],
            'endtermin'     => ['nullable', 'date'],
            'anfangsdatum'  => ['nullable', 'date'],
            'enddatum'      => ['nullable', 'date'],
            'model_type'    => ['required'],
        ]);

        DB::beginTransaction();

        try {
            $teilnehmer = Personen::findOrFail($validated['teilnehmer_id']);

            // Prüfen: Schon zugewiesen?
            $existingPivot = ProjektHasPersonen::where('personen_id', $validated['teilnehmer_id'])
                ->where('projekt_id', $validated['projekt_id'])
                ->first();

            // ---------------------------------------------------------
            // 🔵 Fall 1: Projektzuordnung existiert schon → Zeitraum anhängen
            // ---------------------------------------------------------
            if ($existingPivot) {

                Zeitraum::create([
                    'antragsdatum' => $validated['antragsdatum'] ?? null,
                    'starttermin'  => $validated['starttermin'] ?? null,
                    'endtermin'    => $validated['endtermin'] ?? null,
                    'anfangsdatum' => $validated['anfangsdatum'] ?? null,
                    'enddatum'     => $validated['enddatum'] ?? null,
                    'model_type'   => $validated['model_type'],
                    'model_id'     => $existingPivot->id,
                ]);

                DB::commit();

                return back()->with('success', 'Zeitraum zum bestehenden Projekt hinzugefügt!');
            }

            // ---------------------------------------------------------
            // 🔵 Fall 2: Neues Projekt → Pivot anlegen
            // ---------------------------------------------------------

            // Standort automatisch aus Projekt holen
            $projekt = Projekt::find($validated['projekt_id']);
            $standortId = $projekt?->abteilung?->standort_id ?? 1;

            $pivot = ProjektHasPersonen::create([
                'personen_id' => $validated['teilnehmer_id'],
                'projekt_id'  => $validated['projekt_id'],
                'status'      => 'aktiv',
                'standort_id' => $standortId, // sauberer statt "1"
            ]);

            // Zeitraum anlegen
            Zeitraum::create([
                'antragsdatum' => $validated['antragsdatum'] ?? null,
                'starttermin'  => $validated['starttermin'] ?? null,
                'endtermin'    => $validated['endtermin'] ?? null,
                'anfangsdatum' => $validated['anfangsdatum'] ?? null,
                'enddatum'     => $validated['enddatum'] ?? null,
                'model_type'   => $validated['model_type'],
                'model_id'     => $pivot->id,
            ]);

            DB::commit();

            return back()->with('success', 'Projekt erfolgreich zugewiesen!');

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error("Projekt-Zuweisung FEHLER: " . $e->getMessage(), [
                'user_id' => auth()->id(),
                'daten'   => $validated,
            ]);

            return back()->with('error', 'Fehler beim Zuweisen des Projekts.');
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
    public function update(Request $request)
    {


        $validated = $request->validate([
            'id'            => ['required', 'exists:projekt_has_personens,id'],
            'antragsdatum'  => ['nullable', 'date'],
            'starttermin'   => ['nullable', 'date'],
            'endtermin'     => ['nullable', 'date'],
            'anfangsdatum'  => ['nullable', 'date'],
            'enddatum'      => ['nullable', 'date'],
        ]);
        DB::beginTransaction();
        try {
            // 🟩 Pivot holen
            $pivot = ProjektHasPersonen::findOrFail($validated['id']);
            if (!$pivot) {
                return back()->with('error', 'Projektzuweisung nicht gefunden.');
            }



            // 🟩 Letzten Zeitraum holen — **richtige Sortierung (id statt created_at)**
            $zeitraum = $pivot->zeitraume()
                ->orderBy('id', 'desc')
                ->first();

            // 🟧 Wenn vorhanden → aktualisieren
            if ($zeitraum) {

                $zeitraum->update([
                    'antragsdatum' => $validated['antragsdatum'] ?? $zeitraum->antragsdatum,
                    'starttermin'  => $validated['starttermin'] ?? $zeitraum->starttermin,
                    'endtermin'    => $validated['endtermin'] ?? $zeitraum->endtermin,
                    'anfangsdatum' => $validated['anfangsdatum'] ?? $zeitraum->anfangsdatum,
                    'enddatum'     => $validated['enddatum'] ?? $zeitraum->enddatum,
                ]);
            }else {
                // 🟨 Sonst neuen Zeitraum anlegen
            $zeitraum = Zeitraum::create([
                    'antragsdatum' => $validated['antragsdatum'] ?? null,
                    'starttermin'  => $validated['starttermin'] ?? null,
                    'endtermin'    => $validated['endtermin'] ?? null,
                    'anfangsdatum' => $validated['anfangsdatum'] ?? null,
                    'enddatum'     => $validated['enddatum'] ?? null,
                    'model_type'   => get_class($pivot),
                    'model_id'     => $pivot->id,
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'zeitraum' => $zeitraum
            ]);


        return redirect()->back()
            ->with('success', 'Projektzuweisung erfolgreich aktualisiert!')
            ->with('zeitraum', $zeitraum);   // ← wichtig!

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error("Fehler beim Aktualisieren der Projektzuweisung: " . $e->getMessage(), [
                'user_id' => auth()->id(),
                'daten'   => $validated,
            ]);

            return back()->with('error', 'Fehler beim Aktualisieren der Projektzuweisung.');
        }
    }
}
