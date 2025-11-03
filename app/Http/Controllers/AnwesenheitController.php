<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Tage;
use App\Models\Zeiten;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\PersonenHasAnwesenheiten;

class AnwesenheitController extends Controller
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
    /* public function store(Request $request)
    {

        dd($request->all());
        $validated = $request->validate([
            'anwesenheiten' => 'required|array',
            'anwesenheiten.*.personen_id' => 'required|integer|exists:personen,id',
            'anwesenheiten.*.tage_id' => 'required|integer|exists:tage,id',
            'anwesenheiten.*.zeiten_id' => 'required|integer|exists:zeiten,id',
            'anwesenheiten.*.anwesenheitsstatuten_id' => 'required|integer|exists:anwesenheitsstatuten,id',
            'anwesenheiten.*.bemerkung' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            foreach ($validated['anwesenheiten'] as $eintrag) {
                PersonenHasAnwesenheit::updateOrCreate(
                    [
                        'personen_id' => $eintrag['personen_id'],
                        'tage_id' => $eintrag['tage_id'],
                        'zeiten_id' => $eintrag['zeiten_id'],
                    ],
                    [
                        'anwesenheitsstatuten_id' => $eintrag['anwesenheitsstatuten_id'],
                        'user_id' => Auth::id(),
                        'bemerkung' => $eintrag['bemerkung'] ?? null,
                    ]
                );
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Anwesenheiten gespeichert.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    } */
    public function store(Request $request)
    {

        $validated = $request->validate([

            'anwesenheitsstatuten_id' => 'required|integer',

            'tag' => 'required|date',

            'startzeit' => 'required',
            'endzeit' => 'required',
            'personen_id' => 'required|integer|exists:personens,id',
            'bemerkungen' => 'nullable|string|max:255',
        ]);


        DB::beginTransaction();

        try {
                $tagId = Tage::where('datum', $validated['tag'])->value('id');
                if(!$tagId) {
                    throw new Exception('Ungültiger Tag.');
                }
                $zeitenId = Zeiten::firstOrCreate(
                    [
                        'startzeit' => $validated['startzeit'] ,
                        'endzeit' => $validated['endzeit']
                    ]
                )->id;

                PersonenHasAnwesenheiten::updateOrCreate(
                    [
                        'personen_id' => $validated['personen_id'],
                        'tage_id' => $tagId,
                        'zeiten_id' => $zeitenId,
                    ],
                    [
                        'anwesenheitsstatuten_id' => $validated['anwesenheitsstatuten_id'],
                        'user_id' => Auth::id(),
                        'bemerkung' => $validated['bemerkungen'] ?? null,
                    ]
                );


            DB::commit();

                return redirect()->back()->with('success', 'Anwesenheiten gespeichert.');
        } catch (\Exception $e) {
            DB::rollBack();
        return redirect()->back()->with('error', 'Fehler beim Speichern: ' . $e->getMessage());
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
    public function update(Request $request)
{
    $validated = $request->validate([
        'personen_id' => 'required|integer|exists:personens,id',
        'tag' => 'required|date',
        'startzeit' => 'required',
        'endzeit' => 'required',
        'anwesenheitsstatuten_id' => 'required|integer|exists:anwesenheitsstatutens,id',
        'bemerkung' => 'nullable|string|max:255',
    ]);

    // 🔹 Tag-ID holen oder Fehler
    $tagId = Tage::where('datum', $validated['tag'])->value('id');

    if (!$tagId) {
        return response()->json(['error' => 'Ungültiger Tag!'], 422);
    }

    // 🔹 Zeit-ID holen oder anlegen
    $zeitId = Zeiten::firstOrCreate([
        'startzeit' => $validated['startzeit'],
        'endzeit'   => $validated['endzeit'],
    ])->id;

    // 🔹 Prüfen, ob Eintrag existiert
    $anwesenheit = PersonenHasAnwesenheiten::where('personen_id', $validated['personen_id'])
        ->where('tage_id', $tagId)
        ->first();

    if ($anwesenheit) {
        // ✅ UPDATE
        $anwesenheit->update([
            'anwesenheitsstatuten_id' => $validated['anwesenheitsstatuten_id'],
            'zeiten_id' => $zeitId,
            'bemerkung' => $validated['bemerkung'] ?? null,
            'user_id' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Anwesenheit aktualisiert (UPDATE).']);
    }

    // ✅ INSERT
    PersonenHasAnwesenheiten::create([
        'personen_id' => $validated['personen_id'],
        'tage_id' => $tagId,
        'zeiten_id' => $zeitId,
        'anwesenheitsstatuten_id' => $validated['anwesenheitsstatuten_id'],
        'user_id' => Auth::id(),
        'bemerkung' => $validated['bemerkung'] ?? null,
    ]);

    return response()->json(['success' => true, 'message' => 'Anwesenheit neu eingetragen (INSERT).']);
}


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        {
        try {
            $anwesenheit = PersonenHasAnwesenheiten::findOrFail($id); // Suche die Abteilung
            $anwesenheit->delete(); // Lösche die Abteilung

            return response()->json(['message' => 'die Anwesenheit wurde  erfolgreich gelöscht!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Die Anwesenheit konnte nicht gefunden werden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
    }
}
