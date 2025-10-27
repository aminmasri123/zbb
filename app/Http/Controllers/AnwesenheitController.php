<?php

namespace App\Http\Controllers;

use App\Models\Tage;
use App\Models\Zeiten;
use Illuminate\Http\Request;
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
    public function store(Request $request)
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
            'startzeit' => 'required|date_format:H:i:s',
            'endzeit' => 'required|date_format:H:i:s|after:startzeit',
            'anwesenheitsstatuten_id' => 'required|integer|exists:anwesenheitsstatutens,id',
            'bemerkung' => 'nullable|string|max:255',
        ]);

        $tagId = Tage::where('datum', $validated['tag'])->value('id');
        if(!$tagId) {
            return response()->json([
                'success' => false,
                'message' => 'Ungültiger Tag.',
            ], 400);
        }

        $eintrag = PersonenHasAnwesenheiten::updateOrCreate(
            [
                'personen_id' => $validated['personen_id'],
                'tage_id' => $tagId,
                'zeiten_id' => Zeiten::firstOrCreate(
                    [
                        'startzeit' => $validated['startzeit'],
                        'endzeit' => $validated['endzeit']
                    ]
                )->id,
            ],
            [
                'anwesenheitsstatuten_id' => $validated['anwesenheitsstatuten_id'],
                'user_id' => Auth::id(),
                'bemerkung' => $validated['bemerkung'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Anwesenheit erfolgreich gespeichert.',
            'eintrag' => $eintrag,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
