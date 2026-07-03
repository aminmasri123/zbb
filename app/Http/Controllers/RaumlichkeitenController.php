<?php

namespace App\Http\Controllers;

use Exception;
use Inertia\Inertia;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\Personen;
use App\Models\RaumMeldung;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RaumlichkeitenController extends Controller
{
    public function index()
    {
        $this->authorizeRoomPermission('index');

        $standorte = Standort::with([
            'adresse',
            'raeume' => fn ($query) => $query
                ->with(['parent', 'children', 'standardPerson', 'meldungen.gemeldetVonPerson'])
                ->orderBy('name'),
        ])->orderBy('name')->get();

        $personal = Personen::mitarbeiter()
            ->aktiv()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get(['id', 'vorname', 'nachname']);

        return Inertia::render('Raum/Index', [
            'standorte' => $standorte,
            'personal' => $personal,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeRoomPermission('store');

        $validated = $request->validate($this->raumRules());
        $validated = $this->normalizeRaumBelegung($validated);
        $this->ensureParentRoom($validated);

        $raum = Raeume::create($validated);

        return response()->json([
            'message' => 'Raum erfolgreich erstellt.',
            'raum' => $raum->load(['standort', 'parent', 'children', 'standardPerson', 'meldungen.gemeldetVonPerson']),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeRoomPermission('update');

        $raum = Raeume::findOrFail($id);
        $validated = $request->validate($this->raumRules($raum->id));
        $validated = $this->normalizeRaumBelegung($validated);
        $this->ensureParentRoom($validated, $raum->id);

        $raum->update($validated);

        return response()->json([
            'message' => 'Raum erfolgreich aktualisiert.',
            'raum' => $raum->load(['standort', 'parent', 'children', 'standardPerson', 'meldungen.gemeldetVonPerson']),
        ], 200);
    }

    public function destroy(string $id)
    {
        $this->authorizeRoomPermission('destroy');

        try {
            $raum = Raeume::findOrFail($id);
            $raum->delete();

            return response()->json(['message' => 'Der Raum ' . $raum->name . ' wurde erfolgreich geloescht.'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Die Daten konnten nicht gefunden werden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }

    public function storeMeldung(Request $request, Raeume $raum)
    {
        $user = auth()->user();
        abort_unless(
            $this->canAny($user, ['raeumlichkeiten.meldung.store', 'gruppe.index', 'raeumlichkeiten.index', 'räumlichkeiten.index']),
            403
        );

        $validated = $request->validate([
            'titel' => 'required|string|max:150',
            'kategorie' => ['required', Rule::in($this->meldungKategorien())],
            'prioritaet' => ['required', Rule::in(['niedrig', 'normal', 'hoch', 'kritisch'])],
            'beschreibung' => 'nullable|string|max:2000',
            'projekt_id' => 'nullable|integer|exists:projekts,id',
            'gruppe_id' => 'nullable|integer|exists:gruppes,id',
        ]);

        $meldung = RaumMeldung::create([
            ...$validated,
            'raum_id' => $raum->id,
            'gemeldet_von_user_id' => $user?->id,
            'gemeldet_von_personen_id' => $user?->person_id,
            'status' => 'offen',
        ]);

        return response()->json([
            'message' => 'Meldung wurde erfasst.',
            'meldung' => $meldung->load(['gemeldetVonPerson', 'raum']),
        ], 201);
    }

    public function updateMeldung(Request $request, RaumMeldung $meldung)
    {
        $this->authorizeRoomPermission('meldung.update');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['offen', 'in_bearbeitung', 'erledigt'])],
            'prioritaet' => ['nullable', Rule::in(['niedrig', 'normal', 'hoch', 'kritisch'])],
            'beschreibung' => 'nullable|string|max:2000',
        ]);

        $meldung->update([
            ...$validated,
            'erledigt_am' => $validated['status'] === 'erledigt' ? now() : null,
        ]);

        return response()->json([
            'message' => 'Meldung wurde aktualisiert.',
            'meldung' => $meldung->load(['gemeldetVonPerson', 'raum']),
        ]);
    }

    private function raumRules(?int $raumId = null): array
    {
        return [
            'name' => 'required|string|max:100',
            'standort_id' => 'required|exists:standorts,id',
            'parent_id' => 'nullable|integer|exists:raeumes,id',
            'typ' => ['required', Rule::in($this->raumtypen())],
            'belegungsart' => ['required', Rule::in(['frei', 'standard', 'teilweise', 'blockiert'])],
            'standard_personen_id' => 'nullable|integer|exists:personens,id',
            'aktiv' => 'sometimes|boolean',
            'kapazitaet' => 'nullable|integer|min:0',
            'beschreibung' => 'nullable|string|max:1000',
        ];
    }

    private function ensureParentRoom(array $validated, ?int $raumId = null): void
    {
        if (empty($validated['parent_id'])) {
            return;
        }

        if ($raumId && (int) $validated['parent_id'] === $raumId) {
            abort(422, 'Ein Raum kann nicht sein eigener Elternraum sein.');
        }

        $parent = Raeume::findOrFail($validated['parent_id']);

        if ((int) $parent->standort_id !== (int) $validated['standort_id']) {
            abort(422, 'Unterraeume muessen am selben Standort liegen wie der Elternraum.');
        }
    }

    private function normalizeRaumBelegung(array $validated): array
    {
        $belegungsart = $validated['belegungsart'] ?? 'frei';

        if (!in_array($belegungsart, ['standard', 'teilweise'], true)) {
            $validated['standard_personen_id'] = null;
            return $validated;
        }

        if ($belegungsart === 'standard' && empty($validated['standard_personen_id'])) {
            throw ValidationException::withMessages([
                'standard_personen_id' => 'Bitte waehlen Sie eine Standard-Person.',
            ]);
        }

        return $validated;
    }

    private function authorizeRoomPermission(string $action): void
    {
        $user = auth()->user();
        abort_unless($this->canAny($user, [
            'raeumlichkeiten.' . $action,
            'räumlichkeiten.' . $action,
        ]), 403);
    }

    private function canAny($user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user?->can($permission)) {
                return true;
            }
        }

        return false;
    }

    private function raumtypen(): array
    {
        return [
            'Büro',
            'Elektroraum',
            'Unterrichtsraum',
            'Seminarraum',
            'Besprechungsraum',
            'Labor',
            'Werkstatt',
            'Lager',
            'Küche',
            'Aufenthaltsraum',
            'Sanitärraum',
            'Empfang',
            'Serverraum',
            'Archiv',
            'Aula',
            'Bibliothek',
            'Arbeitsplatz',
            'Copyroom',
            'Technikraum',
            'Hauswirtschaftsraum',
            'Holzbereich',
            'Metallbereich',
        ];
    }

    private function meldungKategorien(): array
    {
        return [
            'laptop',
            'fenster',
            'heizung',
            'moebel',
            'strom',
            'netzwerk',
            'sicherheit',
            'reinigung',
            'sonstiges',
        ];
    }
}
