<?php

namespace App\Http\Controllers;

use App\Models\Gruppe;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class GruppeHasTeilnehmerController extends Controller
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'gruppe_id'    => 'required|exists:gruppes,id',
            'teilnehmer'   => 'required|array|min:1',
            'teilnehmer.*' => 'integer|exists:personens,id',
        ]);

        // IDs normalisieren (String -> int), damit array_diff korrekt arbeitet
        $ids = array_map('intval', $validated['teilnehmer']);

        $gruppe = Gruppe::findOrFail((int) $validated['gruppe_id']);

        // IDs, die bereits in der Pivot sind (als ARRAY, nicht Collection)
        $already = $gruppe->teilnehmer()
            ->whereIn('personens.id', $ids)
            ->pluck('personens.id')
            ->all(); // => Array

        // Nur die wirklich neuen IDs
        $new = array_values(array_diff($ids, $already)); // => Array

        // 🔒 Wenn es wirklich keine neuen gibt: sofort zurück
        if (count($new) === 0) {
            return back()->with('info', 'Alle ausgewählten Teilnehmer sind bereits in dieser Gruppe.');
        }

        // Nur die neuen hinzufügen
        $gruppe->teilnehmer()->syncWithoutDetaching($new);

        // Wenn es Mischfälle gibt (einige waren schon drin)
        if (count($already) > 0) {
            return back()->with('warning', 'Einige Teilnehmer waren bereits in der Gruppe. Die übrigen wurden hinzugefügt.');
        }

        // Alles neu hinzugefügt
        return back()->with('success', 'Teilnehmer erfolgreich zur Gruppe hinzugefügt.');
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
