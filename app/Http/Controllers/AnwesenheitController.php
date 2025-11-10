<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Tage;
use App\Models\Zeiten;
use Illuminate\Http\Request;
use App\Models\GruppeHasPersonen;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\PersonenHasAnwesenheiten;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
            'startzeit'   => 'required|date_format:H:i',
            'endzeit'     => 'required|date_format:H:i|after:startzeit',
            'tatstartTime'=> 'nullable|date_format:H:i',
            'tatendTime'  => 'nullable|date_format:H:i|after:tatstartzeit',
            'personen_id' => 'required|integer|exists:personens,id',
            'bemerkungen' => 'nullable|string|max:255',
            'gruppe_id' => 'nullable|integer|exists:gruppes,id',

        ]);
        DB::beginTransaction();

        try {

                $tagId = Tage::where('datum', $validated['tag'])->value('id');
                if(!$tagId) {
                    throw new Exception('Ungültiger Tag.');
                }

                $zeitenId = Zeiten::firstOrCreate(
                    [
                        'startzeit' => $validated['startzeit'],
                        'endzeit' => $validated['endzeit']
                    ]
                )->id;

                $tatzeitenId = Zeiten::firstOrCreate(
                    [
                        'startzeit' => $validated['tatstartTime'],
                        'endzeit' => $validated['tatendTime']
                    ]
                )->id;

                GruppeHasPersonen::updateOrCreate(
                [
                    'personen_id' => $validated['personen_id'],
                    'tage_id' => $tagId,
                    'zeitgeplant_id' => $zeitenId,
                    'zeittatsaechlich_id' =>  $tatzeitenId,
                ],
                [

                    'anwesenheitsstatuten_id' => $validated['anwesenheitsstatuten_id'],
                    'user_id' => Auth::id(),
                    'bemerkung' => $validated['bemerkungen'] ?? null,
                    'gruppe_id' => $validated['gruppe_id'] ?? null,
                ]
                );


            DB::commit();


                return redirect()->back()->with('success', 'Anwesenheiten gespeichert.');
        } catch (\Exception $e) {
            DB::rollBack();
        return redirect()->back()->with('error', 'Fehler beim Speichern: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'personen_id' => 'required|integer|exists:personens,id',
            'gruppe_id' => 'required|integer|exists:gruppes,id',
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
        $anwesenheit = GruppeHasPersonen::where('personen_id', $validated['personen_id'])
            ->where('gruppe_id', $validated['gruppe_id'])
            ->where('tage_id', $tagId)
            ->first();

        if ($anwesenheit) {
            // ✅ UPDATE
            $anwesenheit->update([
                'anwesenheitsstatuten_id' => $validated['anwesenheitsstatuten_id'],
                'zeitgeplant_id' => $zeitId, // falls du das Spaltenfeld nutzt
                'zeittatsaechlich_id' => $zeitId, // falls du separate Spalten hast
                'bemerkung' => $validated['bemerkung'] ?? null,
                'user_id' => Auth::id(),
            ]);


            return response()->json(['success' => true, 'message' => 'Anwesenheit aktualisiert (UPDATE).']);
        }

        // ✅ INSERT
        GruppeHasPersonen::create([
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
    public function destroy($id)
    {

        try {

            GruppeHasPersonen::findOrFail($id)->delete();
            return response()->json(['message' => 'Kontakt erfolgreich entfernt.'], 200);


        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Die Anwesenheit konnte nicht gefunden werden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }

    }
}
