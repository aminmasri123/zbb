<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Models\PersonenHasNotizen;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NotizController extends Controller
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
        try {
            // 🧩 1. Validierung
            $validated = $request->validate([
                'person_id' => 'required|exists:personens,id',
                'notiztyp' => 'required|exists:notizvariantens,id',
                'notizkategorie' => 'required|exists:notizvariantens,id',
                'prioritaet' => 'required|exists:notizvariantens,id',
                'titel' => 'required|string|max:255',
                'inhalt' => 'required|string',
            ]);

            $user = auth()->user();


            // 📨 2. Brief erstellen
            $brief = PersonenHasNotizen::create([
                'user_id' => $user->id,
                'person_id' => $validated['person_id'],
                'notiztyp_id' => $validated['notiztyp'],
                'prioritaet_id' => $validated['prioritaet'],
                'kategorie_id' => $validated['notizkategorie'],
                'titel' => $validated['titel'],
                'notizinhalt' => $validated['inhalt'],
            ]);



            // ✅ Erfolgreiche Rückgabe
            return redirect()->back()->with('success', 'Vorlage erfolgreich hinzugefügt!');


        } catch (ValidationException $e) {
            // ❗ Falls Validierung fehlschlägt
            return back()
                ->withErrors($e->validator)
                ->withInput();

        } catch (Exception $e) {
            // ❌ Allgemeine Fehler (z. B. DB-Probleme)
            Log::error('Fehler beim Erstellen eines Briefes: ' . $e->getMessage());

            return back()
                ->with('error', 'Beim Erstellen des Briefs ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.')
                ->withInput();
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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $notiz = PersonenHasNotizen::findOrFail($id);
            $notiz->delete();

            return response()->json(['message' => 'die Notiz: ' . $notiz->titel . ' wurde  erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Die Daten konnte nicht gefunden werden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
