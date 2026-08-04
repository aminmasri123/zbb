<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\User;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\RaumHasPersonen;
use Illuminate\Http\Request;
use App\Models\ProjektHasPersonen;
use App\Services\Projects\StaffProjectAssignmentSynchronizer;
use Illuminate\Support\Facades\DB;

class ProjektHasPersonenController extends Controller
{
    public function __construct(private readonly StaffProjectAssignmentSynchronizer $projectAssignments)
    {
    }

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
            'zuweisungen.*.bereich_ids' => ['array'],
            'zuweisungen.*.bereich_ids.*' => ['integer', 'exists:bereiches,id'],
            'zuweisungen.*.default_bereich_id' => ['nullable', 'integer', 'exists:bereiches,id'],
            'zuweisungen.*.buero_raum_ids' => ['array'],
            'zuweisungen.*.buero_raum_ids.*' => ['integer', 'exists:raeumes,id'],
            'zuweisungen.*.default_buero_raum_id' => ['nullable', 'integer', 'exists:raeumes,id'],
            'zuweisungen.*.arbeitsbereich_raum_ids' => ['array'],
            'zuweisungen.*.arbeitsbereich_raum_ids.*' => ['integer', 'exists:raeumes,id'],
            'zuweisungen.*.default_arbeitsbereich_raum_id' => ['nullable', 'integer', 'exists:raeumes,id'],
        ]);

        DB::beginTransaction();

        try {

            $person = Personen::findOrFail($validated['user_id']);

            foreach ($validated['zuweisungen'] as $zw) {

                foreach ($zw['standort_id'] as $standortId) {

                    $assignment = ProjektHasPersonen::updateOrCreate(
                        [
                            'personen_id' => $person->id,
                            'projekt_id'  => $zw['projekt_id'],
                            'standort_id' => $standortId,
                        ],
                        [
                            'status' => 'aktiv',
                        ]
                    );

                    $this->projectAssignments->syncBereicheForAssignment(
                        $assignment,
                        $zw['bereich_ids'] ?? [],
                        isset($zw['default_bereich_id']) ? (int) $zw['default_bereich_id'] : null,
                    );

                    $this->projectAssignments->syncRaeumeForAssignment(
                        $assignment,
                        RaumHasPersonen::TYPE_BUERO,
                        $zw['buero_raum_ids'] ?? [],
                        isset($zw['default_buero_raum_id']) ? (int) $zw['default_buero_raum_id'] : null,
                    );

                    $this->projectAssignments->syncRaeumeForAssignment(
                        $assignment,
                        RaumHasPersonen::TYPE_ARBEITSBEREICH,
                        $zw['arbeitsbereich_raum_ids'] ?? [],
                        isset($zw['default_arbeitsbereich_raum_id']) ? (int) $zw['default_arbeitsbereich_raum_id'] : null,
                    );
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
    public function destroy(Request $request, string $id)
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:personens,id'],
            'projekt_id' => ['nullable', 'integer', 'exists:projekts,id'],
        ]);

        if (! empty($validated['user_id'])) {
            $projektId = $validated['projekt_id'] ?? $id;

            $deleted = ProjektHasPersonen::where('personen_id', $validated['user_id'])
                ->where('projekt_id', $projektId)
                ->delete();

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'message' => 'Projekt wurde vom Mitarbeiter entfernt.',
            ]);
        }

        $zuweisung = ProjektHasPersonen::findOrFail($id);
        $zuweisung->delete();

        return response()->json([
            'success' => true,
            'deleted' => 1,
            'message' => 'Projektzuweisung wurde entfernt.',
        ]);
    }
}
