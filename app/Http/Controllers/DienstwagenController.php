<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Personen;
use App\Models\Standort;
use App\Models\Dienstwagen;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DienstwagenController extends Controller
{
    public function index()
    {
        return Inertia::render('Dienstwagen/Index', [
            'vehicles'  => Dienstwagen::with('standort')->orderBy('created_at', 'desc')->get(),
            'standorte' => Standort::orderBy('name')->get()
        ]);
    }

    public function create()
    {
        return Inertia::render('Dienstwagen/Create', [
            'drivers'   => Personen::orderBy('nachname')->get(),
            'locations' => Standort::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'typ'          => 'required|string',
            'kennzeichen'   => 'required|string|unique:dienstwagens,kennzeichen',
            'marke'         => 'required|string',
            'modell'        => 'required|string',
            'baujahr'       => 'required|integer',
            'kraftstoffart' => 'required|string',
            'kilometerstand'=> 'required|integer',
            'standort_id'   => 'required|integer|exists:standorts,id',
            'status'        => 'required|string',
            'naechste_wartung'  => 'nullable|date',
            //'allowed_drivers' => 'nullable|array',
        ]);
        if (!empty($data['naechste_wartung'])){

            $data['naechste_wartung'] = Carbon::parse($data['naechste_wartung'])->format('Y-m-d');
        }

        $vehicle = Dienstwagen::create($data);

        // Fahrerberechtigungen
        /* if (!empty($data['allowed_drivers'])) {
            $vehicle->drivers()->sync($data['allowed_drivers']);
        } */

        return redirect()->route('dienstwagen.index')
            ->with('success', 'Fahrzeug erfolgreich hinzugefügt.');
    }

    public function edit($id)
    {
        $vehicle = Dienstwagen::findOrFail($id);
        return Inertia::render('Dienstwagen/Edit', [
            'vehicle'  => $vehicle->load('fahrer'),
            'drivers'  => Personen::orderBy('nachname')->get(),
            'locations'=> Standort::orderBy('name')->get(),
        ]);
    }


    public function update(Request $request, $id,)
    {
        $dienstwagen = Dienstwagen::findOrFail($request->id);

        $data = $request->validate([
            'typ'             => 'required|string',
            'kennzeichen'     => 'required|string|unique:dienstwagens,kennzeichen,' . $dienstwagen->id,
            'marke'           => 'required|string',
            'modell'          => 'required|string',
            'baujahr'         => 'required|integer',
            'kraftstoffart'   => 'required|string',
            'kilometerstand'  => 'required|integer',
            'standort_id'     => 'required|integer|exists:standorts,id',
            'status'          => 'required|string',
            'naechste_wartung'=> 'nullable|date',
           'allowed_drivers.*'=> 'integer|exists:personens,id',
            'allowed_drivers' => 'nullable|array',
        ]);

        $dienstwagen->update($data);

        // Wenn Fahrerberechtigungen aktiv sind:
        if (!empty($data['allowed_drivers'])) {
            $dienstwagen->fahrer()->sync($data['allowed_drivers']);
        } else {
            $dienstwagen->fahrer()->detach();
        }

        return redirect()->route('dienstwagen.index')
            ->with('success', 'Fahrzeugdaten erfolgreich aktualisiert.');
    }

    public function destroy($id)
    {
         try {
            $dienstwagen = Dienstwagen::findOrFail($id);

            $dienstwagen->delete(); // Lösche die Projekt

            return response()->json(['message' => 'Dienstwagen erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Dienstwagen nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
