<?php

namespace App\Http\Controllers;

use Throwable;
use Carbon\Carbon;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Zeitraum;
use Illuminate\Http\Request;
use App\Models\ProjektHasPersonen;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjektHasTeilnehmerController extends Controller
{
    public function __construct(private readonly ActiveProjectContext $activeProjectContext)
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
                //dd($request);

        $validated = $request->validate([
            'teilnehmer_id'       => ['required', 'exists:personens,id'],
            'massnahmebegleiter'  => ['nullable', 'exists:personens,id'],
            'betreuer'            => ['nullable', 'exists:personens,id'],
            'projekt_id'          => ['required', 'exists:projekts,id'],
            'antragsdatum'        => ['nullable', 'date'],
            'starttermin'         => ['nullable', 'date'],
            'endtermin'           => ['nullable', 'date'],
            'anfangsdatum'        => ['nullable', 'date'],
            'enddatum'            => ['nullable', 'date'],
            'standort_id'         => ['nullable', 'exists:standorts,id'],
            'model_type'          => ['required', Rule::in([ProjektHasPersonen::class])],
        ]);

        $activeProject = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($activeProject, 409, 'Bitte wählen Sie zuerst ein aktives Projekt aus.');
        abort_unless((int) $validated['projekt_id'] === (int) $activeProject->id, 403);
        $validated['projekt_id'] = $activeProject->id;
        $this->validateProjectStaff($activeProject, [
            $validated['betreuer'] ?? null,
        ]);
        $teilnehmer = Personen::query()
            ->teilnehmer()
            ->whereHas('projekte', fn ($query) => $query->where('projekts.id', $activeProject->id))
            ->findOrFail($validated['teilnehmer_id']);

        DB::beginTransaction();

        try {
            // 🔹 Prüfen: Projekt bereits zugewiesen?
            $existingPivot = ProjektHasPersonen::where('personen_id', $validated['teilnehmer_id'])
                ->where('projekt_id', $validated['projekt_id'])
                ->first();

            if ($existingPivot) {
                if (array_key_exists('standort_id', $validated)) {
                    $existingPivot->update([
                        'standort_id' => $validated['standort_id'],
                    ]);
                }

                $zeitraum = Zeitraum::create([
                    'antragsdatum' => $validated['antragsdatum'] ?? null,
                    'starttermin'  => $validated['starttermin'] ?? null,
                    'endtermin'    => $validated['endtermin'] ?? null,
                    'anfangsdatum' => $validated['anfangsdatum'] ?? null,
                    'enddatum'     => $validated['enddatum'] ?? null,
                    'model_type'   => $validated['model_type'],
                    'model_id'     => $existingPivot->id,
                ]);

                $meta = $existingPivot->meta;
                if (!$meta) {
                    $meta = $existingPivot->meta()->create([
                        'projekt_person_id'   => $existingPivot->id,
                        'projektbegleiter_id' => $validated['massnahmebegleiter'] ?? null,
                        'betreuer_id'         => $validated['betreuer'] ?? null,
                    ]);
                } else {
                    $meta->update([
                        'projektbegleiter_id' => $validated['massnahmebegleiter'] ?? $meta->projektbegleiter_id,
                        'betreuer_id'         => $validated['betreuer'] ?? $meta->betreuer_id,
                    ]);
                }

                DB::commit();
                return back()->with('success', 'Zeitraum zum bestehenden Projekt hinzugefügt!');
            }

            $standortId = $validated['standort_id'] ?? null;

            $pivot = ProjektHasPersonen::create([
                'personen_id' => $validated['teilnehmer_id'],
                'projekt_id'  => $validated['projekt_id'],
                'status'      => $activeProject->rule('participation_initial_status', 'aktiv'),
                'standort_id' => $standortId,
            ]);

            // 🟢 Meta sofort anlegen
            $pivot->meta()->create([
                'projekt_person_id'   => $pivot->id,
                'projektbegleiter_id' => $validated['massnahmebegleiter'] ?? null,
                'betreuer_id'         => $validated['betreuer'] ?? null,
            ]);

            // Zeitraum anlegen
            Zeitraum::create([
                'antragsdatum' => $validated['antragsdatum'] ?? null,
                'starttermin'  => $validated['starttermin'] ?? null,
                'endtermin'    => $validated['endtermin'] ?? null,
                'anfangsdatum' => $validated['anfangsdatum'] ?? null,
                'enddatum'     => $validated['enddatum'] ?? null,
                'model_type'   => $validated['model_type'],
                'model_id'     => $pivot->id,
            ]);

            DB::commit();
            return back()->with('success', 'Projekt erfolgreich zugewiesen!');
        }

        // ---------------------------------------------------------
        // 🔴 Fehlerbehandlung
        // ---------------------------------------------------------
        catch (Throwable $e) {
            DB::rollBack();

            Log::error("Projekt-Zuweisung FEHLER: " . $e->getMessage(), [
                'user_id' => auth()->id(),
                'daten'   => $validated,
            ]);

            return back()->with('error', 'Fehler beim Zuweisen des Projekts.');
        }
    }

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
            'id'            => ['required', 'exists:projekt_has_personens,id'],
            'projektbegleiter_id' => ['nullable', 'exists:personens,id'],
            'betreuer_id' => ['nullable', 'exists:personens,id'],
            'antragsdatum'  => ['nullable', 'date'],
            'starttermin'   => ['nullable', 'date'],
            'endtermin'     => ['nullable', 'date'],
            'anfangsdatum'  => ['nullable', 'date'],
            'enddatum'      => ['nullable', 'date'],
            'standort_id'   => ['nullable', 'exists:standorts,id'],
            'status' => ['sometimes', 'string', Rule::in(Projekt::PARTICIPATION_STATUSES)],
        ]);

        $activeProject = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($activeProject, 409, 'Bitte wählen Sie zuerst ein aktives Projekt aus.');
        $this->validateProjectStaff($activeProject, [
            $validated['betreuer_id'] ?? null,
        ]);
        $pivot = ProjektHasPersonen::query()
            ->where('projekt_id', $activeProject->id)
            ->findOrFail($validated['id']);

        DB::beginTransaction();
        try {
            // 🟩 Pivot holen
            if (!$pivot) {
                return back()->with('error', 'Projektzuweisung nicht gefunden.');
            }

            if (array_key_exists('standort_id', $validated)) {
                $pivot->update([
                    'standort_id' => $validated['standort_id'],
                ]);
            }

            if (array_key_exists('status', $validated)) {
                $pivot->update(['status' => $validated['status']]);
            }

           // Prüfen, ob Meta existiert (angenommen: Relation heißt "meta")
            $meta = $pivot->meta;
            $projektbegleiterId = $validated['projektbegleiter_id'] ?? null;
            $betreuerId = $validated['betreuer_id'] ?? null;

                if (!$meta && ($projektbegleiterId || $betreuerId)) {
                    // 🟢 1. Kein Meta vorhanden + einer der beiden Werte existiert → ERSTELLEN
                    $meta = $pivot->meta()->create([
                        'projekt_person_id'   => $pivot->id,
                        'projektbegleiter_id' => $projektbegleiterId,
                        'betreuer_id'         => $betreuerId,
                    ]);

                } elseif ($meta && ($projektbegleiterId || $betreuerId)) {
                    // 🟡 2. Meta existiert + einer der Werte gesetzt → UPDATE
                    $meta->update([
                        'projektbegleiter_id' => $projektbegleiterId ?? $meta->projektbegleiter_id,
                        'betreuer_id'         => $betreuerId ?? $meta->betreuer_id,
                    ]);

                } elseif (!$meta && !$projektbegleiterId && !$betreuerId) {
                    // 🔴 3. Kein Meta vorhanden + beide Werte NULL → NICHTS TUN
                    // (absichtlich leer)
                }


            // 🟩 Letzten Zeitraum holen — **richtige Sortierung (id statt created_at)**
            $zeitraum = $pivot->zeitraume()
                ->orderBy('id', 'desc')
                ->first();
            $hasZeitraumData = collect([
                'antragsdatum',
                'starttermin',
                'endtermin',
                'anfangsdatum',
                'enddatum',
            ])->contains(fn ($field) => !empty($validated[$field]));
            // 🟧 Wenn vorhanden → aktualisieren
            if ($zeitraum) {

                $zeitraum->update([
                    'antragsdatum' => $validated['antragsdatum'] ?? $zeitraum->antragsdatum,
                    'starttermin'  => $validated['starttermin'] ?? $zeitraum->starttermin,
                    'endtermin'    => $validated['endtermin'] ?? $zeitraum->endtermin,
                    'anfangsdatum' => $validated['anfangsdatum'] ?? $zeitraum->anfangsdatum,
                    'enddatum'     => $validated['enddatum'] ?? $zeitraum->enddatum,
                ]);
            } elseif ($hasZeitraumData) {
                // 🟨 Sonst neuen Zeitraum anlegen
            $zeitraum = Zeitraum::create([
                    'antragsdatum' => $validated['antragsdatum'] ?? null,
                    'starttermin'  => $validated['starttermin'] ?? null,
                    'endtermin'    => $validated['endtermin'] ?? null,
                    'anfangsdatum' => $validated['anfangsdatum'] ?? null,
                    'enddatum'     => $validated['enddatum'] ?? null,
                    'model_type'   => get_class($pivot),
                    'model_id'     => $pivot->id,
                ]);
            } else {
                $zeitraum = null;
            }

            DB::commit();
            $pivot->load('standort');

            return response()->json([
                'success' => true,
                'zeitraum' => $zeitraum ?? null,
                'meta'     => $meta ? $meta->load('projektbegleiter', 'betreuer') : null,
                'standort_id' => $pivot->standort_id,
                'standort' => $pivot->standort,
                'status' => $pivot->status,
            ]);
        } catch (Throwable $e) {

            DB::rollBack();

            Log::error("Fehler beim Aktualisieren der Projektzuweisung: " . $e->getMessage(), [
                'user_id' => auth()->id(),
                'daten'   => $validated,
            ]);

            return back()->with('error', 'Fehler beim Aktualisieren der Projektzuweisung.');
        }
    }

    private function validateProjectStaff(Projekt $project, array $personIds): void
    {
        $personIds = collect($personIds)->filter()->map(fn ($id) => (int) $id)->unique();
        if ($personIds->isEmpty()) {
            return;
        }

        $validIds = $project->mitarbeiter()
            ->whereIn('personens.id', $personIds)
            ->pluck('personens.id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($validIds->count() !== $personIds->count()) {
            throw ValidationException::withMessages([
                'betreuer' => 'Betreuer und Ansprechpartner müssen dem aktiven Projekt zugewiesen sein.',
            ]);
        }
    }
}
