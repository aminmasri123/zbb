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
            'anwesenheitsstatuten_id' => 'required|integer|exists:anwesenheitsstatutens,id',
            'tag' => 'required|date|exists:tages,datum',
            'startzeit'   => 'required|date_format:H:i',
            'endzeit'     => 'required|date_format:H:i|after:startzeit',
            'tatstartTime'=> 'nullable|date_format:H:i',
            'tatendTime'  => 'nullable|date_format:H:i|after:tatstartTime',
            'personen_id' => 'required|integer|exists:personens,id',
            'bemerkung' => 'nullable|string|max:255',
            'bereich_id' => 'nullable|integer|exists:bereiches,id',
        ]);

        DB::beginTransaction();

        try {

                $tagId = Tage::where('datum', $validated['tag'])->value('id');

                if(!$tagId || $tagId == Null) {
                    throw new Exception('Ungültiger Tag.');
                    return redirect()->back()->with('error', 'Ungültiger Tag');
                }

                $tatzeitenId = null;
                $zeitenId = Zeiten::firstOrCreate(
                    [
                        'startzeit' => $validated['startzeit'],
                        'endzeit' => $validated['endzeit']
                    ]
                )->id;

                if (!empty($validated['tatstartTime']) && !empty($validated['tatendTime'])) {
                    $tatzeitenId = Zeiten::firstOrCreate(
                        [
                            'startzeit' => $validated['tatstartTime'],
                            'endzeit'   => $validated['tatendTime']
                        ]
                    )->id;
                }

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
        } catch (Exception $e) {
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
            'gruppe_id'   => 'nullable|integer|exists:gruppes,id',
            'tag'         => 'required|date',

            'startzeit'   => 'nullable|date_format:H:i',
            'endzeit'     => 'nullable|date_format:H:i|after:startzeit',

            'tatstartTime'=> 'nullable|date_format:H:i',
            'tatendTime'  => 'nullable|date_format:H:i|after:tatstartTime',

            'anwesenheitsstatuten_id' => 'required|integer|exists:anwesenheitsstatutens,id',
            'bemerkung'    => 'nullable|string|max:255',
        ]);

        // 🔹 Tag-ID holen
        $tagId = Tage::where('datum', $validated['tag'])->value('id');
        if (!$tagId) {
            return response()->json(['error' => 'Ungültiges Datum!'], 422);
        }

        // 🔹 Bestehenden Datensatz abrufen
        $anwesenheit = GruppeHasPersonen::where('personen_id', $validated['personen_id'])
            ->where('tage_id', $tagId)
            ->when($validated['gruppe_id'], fn($q) => $q->where('gruppe_id', $validated['gruppe_id']))
            ->first();

        /**
         * 🔹 Zeit IDs NUR setzen, wenn Zeiten wirklich im Request vorhanden sind
         * und nur dann neue Zeitblöcke erstellen.
         */
        $zeitgeplantId = $anwesenheit->zeitgeplant_id ?? null;
        if (!empty($validated['startzeit']) && !empty($validated['endzeit'])) {
            // Nur neue Zeit anlegen, falls sich etwas geändert hat
            if (
                !$anwesenheit ||
                $anwesenheit->zeitgeplant?->startzeit !== $validated['startzeit'] ||
                $anwesenheit->zeitgeplant?->endzeit   !== $validated['endzeit']
            ) {
                $zeitgeplantId = Zeiten::firstOrCreate([
                    'startzeit' => $validated['startzeit'],
                    'endzeit'   => $validated['endzeit'],
                ])->id;
            }
        }

        // 🔹 Tatsächliche Zeit
        $zeittatsaechlichId = $anwesenheit->zeittatsaechlich_id ?? null;
        if (!empty($validated['tatstartTime']) && !empty($validated['tatendTime'])) {
            if (
                !$anwesenheit ||
                $anwesenheit->zeittatsaechlich?->startzeit !== $validated['tatstartTime'] ||
                $anwesenheit->zeittatsaechlich?->endzeit   !== $validated['tatendTime']
            ) {
                $zeittatsaechlichId = Zeiten::firstOrCreate([
                    'startzeit' => $validated['tatstartTime'],
                    'endzeit'   => $validated['tatendTime'],
                ])->id;
            }
        }

        // 🔹 UPDATE
        if ($anwesenheit) {

            $anwesenheit->update([
                'anwesenheitsstatuten_id' => $validated['anwesenheitsstatuten_id'],
                'zeitgeplant_id'          => $zeitgeplantId,
                'zeittatsaechlich_id'     => $zeittatsaechlichId,
                'bemerkung'               => $validated['bemerkung'] ?? null,
                'user_id'                 => Auth::id(),
            ]);

            return redirect()->back()->with('success', 'Anwesenheit aktualisiert.');
        }

        // 🔹 SONST → NEU ANLEGEN
        GruppeHasPersonen::create([
            'personen_id'              => $validated['personen_id'],
            'tage_id'                  => $tagId,
            'gruppe_id'                => $validated['gruppe_id'] ?? null,
            'zeitgeplant_id'           => $zeitgeplantId,
            'zeittatsaechlich_id'      => $zeittatsaechlichId,
            'anwesenheitsstatuten_id'  => $validated['anwesenheitsstatuten_id'],
            'bemerkung'                => $validated['bemerkung'] ?? null,
            'user_id'                  => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Anwesenheit hinzugefügt.');
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
