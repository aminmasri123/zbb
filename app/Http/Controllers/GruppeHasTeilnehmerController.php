<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Gruppe;
use App\Models\Personen;
use App\Models\Standort;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class GruppeHasTeilnehmerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
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
            'gruppe_id'    => 'required|exists:gruppes,id',
            'teilnehmer'   => 'required|array|min:1',
            'teilnehmer.*' => 'integer|exists:personens,id',
        ]);

        $ids = array_map('intval', $validated['teilnehmer']);
        $gruppe = Gruppe::findOrFail($validated['gruppe_id']);

        // IDs, die bereits existieren
        $already = $gruppe->teilnehmer()
            ->whereIn('personens.id', $ids)
            ->pluck('personens.id')
            ->all();

        // Nur neue hinzufügen
        $new = array_values(array_diff($ids, $already));

        // Füge neue hinzu
        if (count($new) > 0) {
            $gruppe->teilnehmer()->syncWithoutDetaching($new);
        }

        // Sammle Teilnehmerdaten für beide Gruppen
        $addedTeilnehmer = \App\Models\Personen::whereIn('id', $new)->get(['id', 'vorname', 'nachname']);
        $alreadyTeilnehmer = \App\Models\Personen::whereIn('id', $already)->get(['id', 'vorname', 'nachname']);

        return response()->json([
            'success' => true,
            'message' => count($new) > 0
                ? 'Teilnehmer erfolgreich hinzugefügt.'
                : 'Keine neuen Teilnehmer hinzugefügt.',
            'added'   => $addedTeilnehmer,
            'already' => $alreadyTeilnehmer,
        ]);
    }




    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = auth()->user();
        $projekt = $user->current_team_id;
        $gruppe = Gruppe::with('teilnehmer', 'teilnehmer.anwesenheiten')->findOrFail($id);

        $person = Personen::findOrFail($user->id);
        $userStandorte = $person->standorte()->pluck('standorts.id')->toArray();

        $teilnehmer = Personen::Teilnehmer()
        ->with('standorte', 'projekte')
        ->whereHas('standorte', function($query) use ($userStandorte) {
            $query->whereIn('standorts.id', $userStandorte);
        })->whereHas('projekte', function ($query) use ($projekt) {
        // prüfe auf die id-Spalte der Projekte
        $query->where('projekts.id', $projekt);
        })
        ->get();





        return Inertia::render('Gruppe/GruppeHasTeilnehmer/Index', [
            'gruppe' => $gruppe,
            'teilnehmer' => $teilnehmer,
            ],
        );
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
