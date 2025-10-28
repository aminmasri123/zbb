<?php

namespace App\Http\Controllers;

use Log;
use Exception;
use App\Models\User;
use App\Models\Brief;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BriefController extends Controller
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
                'name' => 'required|string|max:100',
                'titel' => 'required|string|max:255',
                'content' => 'required|string',
            ]);

            $user = auth()->user();


            // 📨 2. Brief erstellen
            $brief = Brief::create([
                'name' => $validated['name'],
                'title' => $validated['titel'],
                'content' => $validated['content'],
            ]);
            // 🔐 3. Sofort an den Ersteller selbst freigeben
            $brief->freigaben()->create([
                'shareable_to_id'   => $user->id,
                'shareable_to_type' => User::class,
                'shared_by'         => $user->id,
                'right'             => 'bearbeiten',
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
public function share(Request $request)
{
    $validated = $request->validate([
        'brief_id' => 'required|exists:briefs,id',
        'betreuer_ids' => 'required|array',
        'betreuer_ids.*' => 'exists:users,id',
    ]);

    $brief = Brief::findOrFail($validated['brief_id']);

    // Beispiel: Freigaben in Pivot-Tabelle speichern
    foreach ($validated['betreuer_ids'] as $userId) {
        $brief->freigaben()->updateOrCreate(
        [
            'shareable_to_id'   => $userId,
            'shareable_to_type' => User::class,
        ],
        [
            'shared_by' => Auth::id(),
            'right'     => 'lesen',
        ]
    );
    }

    return back()->with('success', 'Brief wurde erfolgreich freigegeben.');
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
