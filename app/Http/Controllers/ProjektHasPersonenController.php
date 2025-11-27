<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\User;
use App\Models\Projekt;
use App\Models\Personen;
use Illuminate\Http\Request;
use App\Models\ProjektHasPersonen;
use Illuminate\Support\Facades\DB;

class ProjektHasPersonenController extends Controller
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
        $validated = $request->validate([
            'user_id' => ['required', 'exists:personens,id'],
            'zuweisungen' => ['required', 'array'],

            'zuweisungen.*.projekt_id' => ['required', 'exists:projekts,id'],
            'zuweisungen.*.standort_id' => ['required', 'array'],
            'zuweisungen.*.standort_id.*' => ['exists:standorts,id'],
        ]);

        DB::beginTransaction();

        try {

            $person = Personen::findOrFail($validated['user_id']);

            foreach ($validated['zuweisungen'] as $zw) {

                foreach ($zw['standort_id'] as $standortId) {

                    ProjektHasPersonen::create([
                        'personen_id' => $person->id,
                        'projekt_id'  => $zw['projekt_id'],
                        'standort_id' => $standortId,
                        'status'      => 'aktiv',
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Projekte erfolgreich zugewiesen.',
            ]);

        } catch (Throwable $e) {

            DB::rollBack();
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Speichern.',
                'error'   => $e->getMessage(),
            ], 500);
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
    public function destroy(string $id)
    {
        //
    }
}
