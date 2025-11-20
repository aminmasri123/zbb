<?php

namespace App\Http\Controllers;

use Exception;
use Throwable;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Dienstwagen;
use Illuminate\Http\Request;
use App\Models\ProjektHasPersonen;
use Illuminate\Support\Facades\Log;
use App\Models\Dienstwagenfahrtenbuch;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DienstwagenfahrtenbuchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
{
    $user = auth()->user();
    $person = $user->person;

    if (!$person) {
        abort(403, "Keine Person verknüpft.");
    }

    // Projekte + Pivot-Daten des Users
    $projektIds   = $person->projekte->pluck('id');
    $standortIds  = $person->projekte->pluck('pivotModel.standort_id');
    $abteilungId  = $person->projekte()->distinct()->pluck('abteilung_id') ?? null;
    /** -----------------------------------------------
     * ROLLE 1: DIENSTWAGENKOORDINATOR → Vollzugriff
     * -----------------------------------------------*/
    if ($user->can('dienstwagen.fahrtenbuch.view.all')) {

        $fahrten = Dienstwagenfahrtenbuch::with(['dienstwagen', 'fahrer'])
            ->orderBy('date', 'desc')
            ->get();

        $fahrer = Personen::mitarbeiter()
            ->active()
            ->with(['dienstwagen', 'dienstwagenfahrten'])
            ->orderBy('nachname')->orderBy('vorname')
            ->get();

        $dienstwagen = Dienstwagen::all();

        return Inertia::render('Dienstwagen/Fahrtenbuch/Index', [
            'entries'  => $fahrten,
            'drivers'  => $fahrer,
            'vehicles' => $dienstwagen,
        ]);
    }
    /** -----------------------------------------------
     * ROLLE 2: ABTEILUNGSLEITER + ASSISTENZ
     * Darf ALLE sehen, die zur gleichen Abteilung gehören
     * -----------------------------------------------*/
    if ($user->can('dienstwagen.fahrtenbuch.view.abteilung')) {

        // Alle Projekte der Abteilung holen
        $abteilungsProjektIds = Projekt::whereIn('abteilung_id', $abteilungId)->pluck('id');
        // Fahrer: Mitarbeiter müssen einem Projekt der Abteilung angehören (aktiv)
        $fahrer = Personen::mitarbeiter()
            ->whereHas('projekte', function ($q) use ($abteilungsProjektIds, $standortIds) {
                $q->whereIn('projekt_id', $abteilungsProjektIds)
                ->whereIn('standort_id', $standortIds)
                ->where('status', 'aktiv');
            })
            ->with(['dienstwagen', 'dienstwagenfahrten'])
            ->active()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();

        // Fahrten: Nur Fahrten von Mitarbeitern, die in Projekten der Abteilung aktiv sind
        $fahrten = Dienstwagenfahrtenbuch::with(['dienstwagen', 'fahrer'])
                ->whereHas('fahrer.projekte', function ($q) use ($abteilungsProjektIds, $standortIds) {
                    $q->whereIn('projekt_id', $abteilungsProjektIds)
                    ->whereIn('standort_id', $standortIds)
                    ->where('status', 'aktiv');
                })
                ->orderBy('date', 'desc')
                ->get();

            // Fahrzeuge (Dienstwagen) nach Standort des Users
            $dienstwagen = Dienstwagen::whereIn('standort_id', $standortIds)->get();

            return Inertia::render('Dienstwagen/Fahrtenbuch/Index', [
                'entries'  => $fahrten,
                'drivers'  => $fahrer,
                'vehicles' => $dienstwagen,
            ]);
    }

    /** -----------------------------------------------
     * ROLLE 3: PROJEKTLEITER
     * Darf ALLE sehen, die im selben Projekt + Standort + aktiv sind
     * -----------------------------------------------*/
    if ($user->can('dienstwagen.fahrtenbuch.view.projekt')) {
        $fahrer = Personen::mitarbeiter()
            ->whereHas('projekte', function ($q) use ($projektIds, $standortIds) {
                $q->whereIn('projekt_id', $projektIds)
                  ->whereIn('standort_id', $standortIds)
                  ->where('status', 'aktiv');
            })
            ->with(['dienstwagen', 'dienstwagenfahrten'])
            ->orderBy('nachname')->orderBy('vorname')
            ->get();

        $fahrten = Dienstwagenfahrtenbuch::with(['dienstwagen', 'fahrer'])
            ->whereHas('fahrer.projekte', function ($q) use ($projektIds, $standortIds) {
                $q->whereIn('projekt_id', $projektIds)
                  ->whereIn('standort_id', $standortIds)
                  ->where('status', 'aktiv');
            })
            ->orderBy('date', 'desc')
            ->get();

        $dienstwagen = Dienstwagen::whereIn('standort_id', $standortIds)->get();

        return Inertia::render('Dienstwagen/Fahrtenbuch/Index', [
            'entries'  => $fahrten,
            'drivers'  => $fahrer,
            'vehicles' => $dienstwagen,
        ]);
    }
    /** -----------------------------------------------
     * ROLLE 4: ANLEITER / SOZIALPÄD / NORMALER MITARBEITER
     * → Sieht nur eigene Fahrten + eigene Fahrzeuge
     * -----------------------------------------------*/
    $fahrten = Dienstwagenfahrtenbuch::with(['dienstwagen', 'fahrer'])
        ->where('person_id', $person->id)
        ->orderBy('date', 'desc')
        ->get();

    $fahrer = Personen::where('id', $person->id)
        ->with(['dienstwagen', 'dienstwagenfahrten'])
        ->get();

    $dienstwagen = $person->dienstwagen;

    return Inertia::render('Dienstwagen/Fahrtenbuch/Index', [
        'entries'  => $fahrten,
        'drivers'  => $fahrer,
        'vehicles' => $dienstwagen,
    ]);
}


    public function store(Request $request)
    {
        try {
            // 1. Validierung
            $data = $request->validate([
                'dienstwagen_id' => 'required|exists:dienstwagens,id',
                'person_id'      => 'nullable|exists:personens,id',
                'date'           => 'required|date',
                'start_km'       => 'required|integer|min:0',
                'end_km'         => 'required|integer|gte:start_km',
                'zweck'          => 'required|string|max:255',
                'ziel'           => 'required|string|max:255',
            ]);
            // 2. Datum parsen

            try {
                $data['date'] = date('Y-m-d', strtotime($data['date']));
            } catch (\Exception $e) {
                Log::error('Fehler beim Datum-Parsing (store): ' . $e->getMessage());
                return back()->withErrors(['date' => 'Ungültiges Datum.'])->withInput();
            }

            // -----------------------------------------
            // 3. KM-LOGIK: Prüfen ob Start-KM korrekt ist
            // -----------------------------------------
            $lastTrip = Dienstwagenfahrtenbuch::where('dienstwagen_id', $data['dienstwagen_id'])
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($lastTrip) {
                if ((int)$lastTrip->end_km !== (int)$data['start_km']) {
                    return back()
                        ->withErrors([
                            'start_km' => "Der Start-Kilometerstand muss dem letzten End-KM entsprechen: {$lastTrip->end_km} km."
                        ])
                        ->withInput();
                }
            }
            // Wenn es keine Fahrt gibt → keine Prüfung nötig

            // 4. Speichern
            Dienstwagenfahrtenbuch::create($data);

            return redirect()
                ->route('dienstwagen.fahrtenbuch.index')
                ->with('success', 'Fahrt wurde erfolgreich gespeichert.');

        } catch (\Throwable $e) {

            Log::error('Fehler beim Speichern einer Fahrt: ' . $e->getMessage());

            return back()
                ->withErrors(['general' => 'Beim Speichern ist ein Fehler aufgetreten.'])
                ->withInput();
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

    public function update(Request $request, $id)
    {
        try {
            $fahrt = Dienstwagenfahrtenbuch::findOrFail($id);
            // 1. Validierung
            $data = $request->validate([
                'dienstwagen_id' => 'required|exists:dienstwagens,id',
                'person_id'      => 'nullable|exists:personens,id',
                'date'           => 'required|date',
                'start_km'       => 'required|integer|min:0',
                'end_km'         => 'required|integer|gte:start_km',
                'zweck'          => 'required|string|max:255',
                'ziel'           => 'required|string|max:255',
            ]);

            // 2. Datum sauber parsen
            try {
                $data['date'] = Carbon::parse($data['date'])->format('Y-m-d');
            } catch (Exception $e) {
                Log::error('Fehler beim Datum-Parsing (update): ' . $e->getMessage());
                return back()->withErrors(['date' => 'Ungültiges Datum.'])->withInput();
            }

            // -----------------------------------------
            // 3. Prüfung: stimmt der Start-KM mit vorheriger Fahrt überein?
            // -----------------------------------------
            $previousTrip = Dienstwagenfahrtenbuch::where('dienstwagen_id', $data['dienstwagen_id'])
                ->where('id', '!=', $id) // eigene Fahrt ignorieren
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($previousTrip) {
                if ((int)$previousTrip->end_km !== (int)$data['start_km']) {
                    return back()
                        ->withErrors([
                            'start_km' => "Der Start-Kilometerstand muss dem letzten End-KM entsprechen: {$previousTrip->end_km} km."
                        ])
                        ->withInput();
                }
            }

            // 4. Fahrt aktualisieren
            $fahrt->update($data);

            return redirect()
                ->route('dienstwagen.fahrtenbuch.index')
                ->with('success', 'Fahrt wurde erfolgreich aktualisiert.');

        } catch (\Throwable $e) {

            Log::error('Fehler beim Aktualisieren einer Fahrt: ' . $e->getMessage());

            return back()
                ->withErrors(['general' => 'Beim Aktualisieren ist ein Fehler aufgetreten.'])
                ->withInput();
        }
    }


    public function destroy($id)
    {
        try {
            $fahrt = Dienstwagenfahrtenbuch::findOrFail($id);

            $fahrt->delete(); // Lösche die Projekt

            return response()->json(['message' => 'Fahrt erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Fahrt nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
