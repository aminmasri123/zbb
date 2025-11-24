<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Gruppe;
use App\Models\Raeume;
use App\Models\Bereich;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Abteilung;
use Illuminate\Http\Request;
use App\Models\ProjektHasBereiche;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\BereichHasTeilnehmer;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GruppeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
     public function index(Request $request)
    {
        $user = Auth()->user();
            if(!$user->current_team_id){
                return redirect()->back()->with('error', 'Bitte wählen Sie ein Projekt aus.');
            }
        //alle gruppen mit bereich laden
            $gruppen = Gruppe::with('bereich', 'betreuer')->where('projekt_id', $user->current_team_id)->get();
            $gruppen = Gruppe::with('bereich', 'betreuer')->where('projekt_id', $user->current_team_id)->where('personen_id', $user->id)->get();

            $projekt = Projekt::with('bereiche', 'mitarbeiter', 'raeume')->findOrFail($user->current_team_id);
            $betreuer = $projekt->mitarbeiter;
            if($user->can('projekt.mitarbeiter.view.all')){
                $betreuer = $projekt->mitarbeiter->values();
            } else {
                //$betreuer = $projekt->mitarbeiter->where('id', $user->id)->first();
                $betreuer = collect([$user->person]);
            }


            return Inertia::render('Gruppe/Index', [
            'gruppen' => $gruppen->toArray(),
            'projekt' => $projekt->toArray(),
            'betreuer' => $betreuer,
            ],
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'startDate'   => 'required|date',
            'endDate'     => 'nullable|date|after_or_equal:startDate',
            'startZeit' => 'required|date_format:H:i',
            'endZeit'   => 'required|date_format:H:i|after:startZeit',
            'bereich'     => 'required|integer|exists:bereiches,id',
            'betreuer'    => 'required|integer|exists:personens,id',
            'raum_id'    => 'required|integer|exists:raeumes,id',
        ]);
        $user = Auth()->user();

        $gruppe = Gruppe::create([
            'personen_id'   => $validated['betreuer'],
            'bereich_id'    => $validated['bereich'],
            'projekt_id'    => $user->current_team_id,
            'raum_id'       => $validated['raum_id'],
            'anfangsdatum'  => $validated['startDate'],
            'enddatum'      => $validated['endDate'] ?? null,
            'startzeit'     => $validated['startZeit'],
            'endzeit'       => $validated['endZeit'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gruppe erfolgreich erstellt.',
            'gruppe'  => $gruppe->load(['bereich', 'betreuer'])
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

     public function update(Request $request, $id)
    {
        try {
            // 🔹 Validierung
            $validated = $request->validate([
                'bereich' => 'required|integer|exists:bereiches,id',
                'betreuer' => 'required|integer|exists:personens,id',
                'anfangsdatum' => 'nullable|date',
                'enddatum' => 'nullable|date|after_or_equal:anfangsdatum',
            ]);

            // 🔹 Gruppe finden
            $gruppe = Gruppe::findOrFail($id);

            // 🔹 Daten speichern (z. B. falls Spalten gleich heißen)
            $gruppe->bereich_id = $validated['bereich'];
            $gruppe->personen_id = $validated['betreuer'];
            $gruppe->anfangsdatum = $validated['anfangsdatum'];
            $gruppe->enddatum = $validated['enddatum'];
            $gruppe->save();

            // 🔹 (Optional) Daten für Vue zurückgeben
            return response()->json([
                'success' => true,
                'message' => 'Gruppe erfolgreich aktualisiert.',
                'projekt' => $gruppe->load(['bereich', 'betreuer'])
            ], 200);

        } catch (ValidationException $e) {
            // ⛔ Validierungsfehler
            return response()->json([
                'success' => false,
                'message' => 'Validierungsfehler',
                'errors' => $e->errors(),
            ], 422);

        } catch (ModelNotFoundException $e) {
            // ⛔ Datensatz nicht gefunden
            return response()->json([
                'success' => false,
                'message' => 'Gruppe nicht gefunden.',
            ], 404);

        } catch (\Exception $e) {
            // ⛔ Allgemeiner Fehler (z. B. SQL)
            Log::error('Fehler beim Aktualisieren der Gruppe: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ein unerwarteter Fehler ist aufgetreten.',
            ], 500);
        }
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $gruppe = Gruppe::findOrFail($id);
            $gruppe->delete();

            return response()->json(['message' => 'Gruppe erfolgreich gelöscht!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Gruppe nicht gefunden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
