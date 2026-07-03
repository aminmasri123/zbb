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
                //dd($request);

        $validated = $request->validate([
            'teilnehmer_id'       => ['required', 'exists:personens,id'],
            'massnahmebegleiter'  => ['nullable', 'exists:personens,id'],
            'betreuer'            => ['nullable', 'exists:personens,id'],
            'projekt_id'          => ['required', 'exists:projekts,id'],
            'antragsdatum'        => ['nullable', 'date'],
            'starttermin'         => ['nullable', 'date'],
            'endtermin'           => ['nullable', 'date'],
            'anfangsdatum'        => ['nullable', 'date'],
            'enddatum'            => ['nullable', 'date'],
            'standort_id'         => ['nullable', 'exists:standorts,id'],
            'model_type'          => ['required'],
        ]);


        DB::beginTransaction();

        try {
            $teilnehmer = Personen::findOrFail($validated['teilnehmer_id']);

            // 🔹 Prüfen: Projekt bereits zugewiesen?
            $existingPivot = ProjektHasPersonen::where('personen_id', $validated['teilnehmer_id'])
                ->where('projekt_id', $validated['projekt_id'])
                ->first();

            // ---------------------------------------------------------
            // 🔵 FALL 1: Bestehende Projektzuweisung → Zeitraum anhängen
            // ---------------------------------------------------------
            /* if ($existingPivot) {
                Zeitraum::create([
                    'antragsdatum' => $validated['antragsdatum'] ?? null,
                    'starttermin'  => $validated['starttermin'] ?? null,
                    'endtermin'    => $validated['endtermin'] ?? null,
                    'anfangsdatum' => $validated['anfangsdatum'] ?? null,
                    'enddatum'     => $validated['enddatum'] ?? null,
                    'model_type'   => $validated['model_type'],
                    'model_id'     => $existingPivot->id,
                ]);

                // 🟢 Meta prüfen oder anlegen/aktualisieren
                $meta = $existingPivot->meta;
                if (!$meta) {
                    $existingPivot->meta()->create([
                        'projekt_person_id'   => $existingPivot->id,
                        'projektbegleiter_id' => $validated['massnahmebegleiter'] ?? null,
                        'betreuer_id'         => $validated['betreuer'] ?? null,
                    ]);
                } else {
                    $meta->update([
                        'projektbegleiter_id' => $validated['massnahmebegleiter'] ?? $meta->projektbegleiter_id,
                        'betreuer_id'         => $validated['betreuer'] ?? $meta->betreuer_id,
                    ]);
                }

                DB::commit();
                return back()->with('success', 'Zeitraum zum bestehenden Projekt hinzugefügt!');
            } */

            // ---------------------------------------------------------
            // 🔵 FALL 2: Neues Projekt → Pivot + Meta + Zeitraum anlegen
            // ---------------------------------------------------------
            $projekt = Projekt::find($validated['projekt_id']);
            $standortId = $validated['standort_id'] ?? $projekt?->abteilung?->standort_id ?? 1;

            $pivot = ProjektHasPersonen::create([
                'personen_id' => $validated['teilnehmer_id'],
                'projekt_id'  => $validated['projekt_id'],
                'status'      => 'aktiv',
                'standort_id' => $standortId,
            ]);

            // 🟢 Meta sofort anlegen
            $pivot->meta()->create([
                'projekt_person_id'   => $pivot->id,
                'projektbegleiter_id' => $validated['massnahmebegleiter'] ?? null,
                'betreuer_id'         => $validated['betreuer'] ?? null,
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
        }

        // ---------------------------------------------------------
        // 🔴 Fehlerbehandlung
        // ---------------------------------------------------------
        catch (Throwable $e) {
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
            'projektbegleiter_id' => ['nullable', 'exists:personens,id'],
            'betreuer_id' => ['nullable', 'exists:personens,id'],
            'antragsdatum'  => ['nullable', 'date'],
            'starttermin'   => ['nullable', 'date'],
            'endtermin'     => ['nullable', 'date'],
            'anfangsdatum'  => ['nullable', 'date'],
            'enddatum'      => ['nullable', 'date'],
            'standort_id'   => ['nullable', 'exists:standorts,id'],
        ]);

        DB::beginTransaction();
        try {
            // 🟩 Pivot holen
            $pivot = ProjektHasPersonen::findOrFail($validated['id']);

            if (!$pivot) {
                return back()->with('error', 'Projektzuweisung nicht gefunden.');
            }

            if (array_key_exists('standort_id', $validated)) {
                $pivot->update([
                    'standort_id' => $validated['standort_id'],
                ]);
            }

           // Prüfen, ob Meta existiert (angenommen: Relation heißt "meta")
            $meta = $pivot->meta;

                if (!$meta && ($validated['projektbegleiter_id'] ?? null || $validated['betreuer_id'] ?? null)) {
                    // 🟢 1. Kein Meta vorhanden + einer der beiden Werte existiert → ERSTELLEN
                    $meta = $pivot->meta()->create([
                        'projekt_person_id'   => $pivot->id,
                        'projektbegleiter_id' => $validated['projektbegleiter_id'] ?? null,
                        'betreuer_id'         => $validated['betreuer_id'] ?? null,
                    ]);

                } elseif ($meta && ($validated['projektbegleiter_id'] ?? null || $validated['betreuer_id'] ?? null)) {
                    // 🟡 2. Meta existiert + einer der Werte gesetzt → UPDATE
                    $meta->update([
                        'projektbegleiter_id' => $validated['projektbegleiter_id'] ?? $meta->projektbegleiter_id,
                        'betreuer_id'         => $validated['betreuer_id'] ?? $meta->betreuer_id,
                    ]);

                } elseif (!$meta && empty($validated['projektbegleiter_id']) && empty($validated['betreuer_id'])) {
                    // 🔴 3. Kein Meta vorhanden + beide Werte NULL → NICHTS TUN
                    // (absichtlich leer)
                }


            // 🟩 Letzten Zeitraum holen — **richtige Sortierung (id statt created_at)**
            $zeitraum = $pivot->zeitraume()
                ->orderBy('id', 'desc')
                ->first();
            $hasZeitraumData = collect([
                'antragsdatum',
                'starttermin',
                'endtermin',
                'anfangsdatum',
                'enddatum',
            ])->contains(fn ($field) => !empty($validated[$field]));
            // 🟧 Wenn vorhanden → aktualisieren
            if ($zeitraum) {

                $zeitraum->update([
                    'antragsdatum' => $validated['antragsdatum'] ?? $zeitraum->antragsdatum,
                    'starttermin'  => $validated['starttermin'] ?? $zeitraum->starttermin,
                    'endtermin'    => $validated['endtermin'] ?? $zeitraum->endtermin,
                    'anfangsdatum' => $validated['anfangsdatum'] ?? $zeitraum->anfangsdatum,
                    'enddatum'     => $validated['enddatum'] ?? $zeitraum->enddatum,
                ]);
            } elseif ($hasZeitraumData) {
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
            } else {
                $zeitraum = null;
            }

            DB::commit();
            $pivot->load('standort');

            return response()->json([
                'success' => true,
                'zeitraum' => $zeitraum ?? null,
                'meta'     => $meta ? $meta->load('projektbegleiter', 'betreuer') : null,
                'standort_id' => $pivot->standort_id,
                'standort' => $pivot->standort,
            ]);
        } catch (Throwable $e) {

            DB::rollBack();

            Log::error("Fehler beim Aktualisieren der Projektzuweisung: " . $e->getMessage(), [
                'user_id' => auth()->id(),
                'daten'   => $validated,
            ]);

            return back()->with('error', 'Fehler beim Aktualisieren der Projektzuweisung.');
        }
    }
}
