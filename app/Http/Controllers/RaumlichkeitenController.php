<?php

namespace App\Http\Controllers;

use Exception;
use Inertia\Inertia;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\Personen;
use App\Models\RaumBuchung;
use App\Models\RaumMeldung;
use App\Notifications\ConfiguredEventNotification;
use App\Services\NotificationRecipientService;
use App\Services\RaumBelegungService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
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
                ->with([
                    'parent',
                    'children',
                    'standardPerson',
                    'verantwortlichePerson',
                    'meldungen.gemeldetVonPerson',
                    'meldungen.zugewiesenAnPerson',
                    'meldungen.behobenVonPerson',
                    'buchungen' => fn ($query) => $query
                        ->with(['gebuchtVonPerson', 'projekt', 'gruppe.bereich'])
                        ->where('end_at', '>=', now()->subDays(14))
                        ->orderBy('start_at'),
                    'gruppen' => fn ($query) => $query
                        ->with(['bereich', 'betreuer', 'projekt'])
                        ->where('ort_typ', 'raum')
                        ->where(function ($query) {
                            $query->whereNull('enddatum')
                                ->orWhereDate('enddatum', '>=', now()->subDays(14)->toDateString());
                        })
                        ->orderBy('anfangsdatum')
                        ->orderBy('startzeit'),
                ])
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
        $validated = $this->normalizeRaumVerwaltung($validated);
        $this->ensureParentRoom($validated);

        $raum = Raeume::create($validated);

        Notification::send(
            app(NotificationRecipientService::class)->forEvent('raeumlichkeiten.created', [
                'actor' => $request->user(),
                'creator_user' => $request->user(),
            ]),
            new ConfiguredEventNotification([
                'event_key' => 'raeumlichkeiten.created',
                'message' => 'Neuer Raum "' . $raum->name . '" wurde erstellt.',
                'link' => route('raeumlichkeiten.index'),
                'id' => $raum->id,
                'typ' => 'Räumlichkeiten',
            ])
        );

        return response()->json([
            'message' => 'Raum erfolgreich erstellt.',
            'raum' => $this->loadRaumDetails($raum),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeRoomPermission('update');

        $raum = Raeume::findOrFail($id);
        $validated = $request->validate($this->raumRules($raum->id));
        $validated = $this->normalizeRaumBelegung($validated);
        $validated = $this->normalizeRaumVerwaltung($validated, $raum);
        $this->ensureParentRoom($validated, $raum->id);

        $raum->update($validated);

        return response()->json([
            'message' => 'Raum erfolgreich aktualisiert.',
            'raum' => $this->loadRaumDetails($raum),
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

        Notification::send(
            app(NotificationRecipientService::class)->forEvent('raeumlichkeiten.meldung.created', [
                'actor' => $user,
                'creator_user' => $user,
                'project_id' => $validated['projekt_id'] ?? null,
            ]),
            new ConfiguredEventNotification([
                'event_key' => 'raeumlichkeiten.meldung.created',
                'message' => 'Neue Raummeldung "' . $meldung->titel . '" wurde erfasst.',
                'link' => route('raeumlichkeiten.index'),
                'id' => $meldung->id,
                'typ' => 'Raummeldung',
            ])
        );

        return response()->json([
            'message' => 'Meldung wurde erfasst.',
            'meldung' => $meldung->load(['gemeldetVonPerson', 'zugewiesenAnPerson', 'behobenVonPerson', 'raum.standort']),
        ], 201);
    }

    public function updateMeldung(Request $request, RaumMeldung $meldung)
    {
        $this->authorizeRoomPermission('meldung.update');

        $validated = $request->validate([
            'titel' => 'sometimes|required|string|max:150',
            'status' => ['required', Rule::in($this->meldungStatus())],
            'kategorie' => ['nullable', Rule::in($this->meldungKategorien())],
            'prioritaet' => ['nullable', Rule::in(['niedrig', 'normal', 'hoch', 'kritisch'])],
            'zugewiesen_an_personen_id' => 'nullable|integer|exists:personens,id',
            'behoben_von_personen_id' => 'nullable|integer|exists:personens,id',
            'faellig_am' => 'nullable|date',
            'beschreibung' => 'nullable|string|max:2000',
            'massnahme' => 'nullable|string|max:2000',
            'kosten' => 'nullable|numeric|min:0|max:99999999.99',
            'interne_notiz' => 'nullable|string|max:2000',
        ]);

        $closed = in_array($validated['status'], ['behoben', 'erledigt'], true);
        $data = $validated;

        if ($closed) {
            $data['erledigt_am'] = $meldung->erledigt_am ?? now();
            $data['behoben_am'] = $meldung->behoben_am ?? now();
            $data['behoben_von_personen_id'] = $data['behoben_von_personen_id']
                ?? $meldung->behoben_von_personen_id
                ?? $request->user()?->person_id;
        } else {
            $data['erledigt_am'] = null;
            $data['behoben_am'] = null;
            $data['behoben_von_personen_id'] = $data['behoben_von_personen_id'] ?? null;
        }

        $meldung->update($data);

        return response()->json([
            'message' => 'Meldung wurde aktualisiert.',
            'meldung' => $meldung->load(['gemeldetVonPerson', 'zugewiesenAnPerson', 'behobenVonPerson', 'raum.standort']),
        ]);
    }

    public function storeBuchung(Request $request, RaumBelegungService $belegungService)
    {
        $this->authorizeAnyRoomPermission(['buchung.store', 'update']);

        $validated = $request->validate($this->buchungRules());
        $raum = Raeume::findOrFail($validated['raum_id']);
        $this->ensureRaumBuchbar($raum, $validated['typ']);

        $start = Carbon::parse($validated['start_at']);
        $end = Carbon::parse($validated['end_at']);

        if (in_array($validated['status'], ['reserviert', 'bestaetigt'], true)) {
            $belegungService->assertAvailable($raum->id, $start, $end);
        }

        $buchung = RaumBuchung::create([
            ...$validated,
            'start_at' => $start,
            'end_at' => $end,
            'projekt_id' => $validated['projekt_id'] ?? $request->user()?->current_team_id,
            'gebucht_von_user_id' => $request->user()?->id,
            'gebucht_von_personen_id' => $request->user()?->person_id,
        ]);

        return response()->json([
            'message' => 'Raumbuchung wurde erstellt.',
            'buchung' => $this->loadBuchungDetails($buchung),
        ], 201);
    }

    public function updateBuchung(Request $request, RaumBuchung $buchung, RaumBelegungService $belegungService)
    {
        $this->authorizeAnyRoomPermission(['buchung.update', 'update']);

        $validated = $request->validate($this->buchungRules());
        $raum = Raeume::findOrFail($validated['raum_id']);
        $this->ensureRaumBuchbar($raum, $validated['typ']);

        $start = Carbon::parse($validated['start_at']);
        $end = Carbon::parse($validated['end_at']);

        if (in_array($validated['status'], ['reserviert', 'bestaetigt'], true)) {
            $belegungService->assertAvailable($raum->id, $start, $end, $buchung->id);
        }

        $buchung->update([
            ...$validated,
            'start_at' => $start,
            'end_at' => $end,
        ]);

        return response()->json([
            'message' => 'Raumbuchung wurde aktualisiert.',
            'buchung' => $this->loadBuchungDetails($buchung),
        ]);
    }

    public function destroyBuchung(RaumBuchung $buchung)
    {
        $this->authorizeAnyRoomPermission(['buchung.destroy', 'buchung.update', 'update']);

        $buchung->update(['status' => 'storniert']);

        return response()->json([
            'message' => 'Raumbuchung wurde storniert.',
            'buchung' => $this->loadBuchungDetails($buchung),
        ]);
    }

    private function raumRules(?int $raumId = null): array
    {
        return [
            'name' => 'required|string|max:100',
            'raumnummer' => 'nullable|string|max:60',
            'etage' => 'nullable|string|max:60',
            'standort_id' => 'required|exists:standorts,id',
            'parent_id' => 'nullable|integer|exists:raeumes,id',
            'typ' => ['required', Rule::in($this->raumtypen())],
            'belegungsart' => ['required', Rule::in(['frei', 'standard', 'teilweise', 'blockiert'])],
            'status' => ['nullable', Rule::in($this->raumStatus())],
            'standard_personen_id' => 'nullable|integer|exists:personens,id',
            'verantwortliche_personen_id' => 'nullable|integer|exists:personens,id',
            'aktiv' => 'sometimes|boolean',
            'buchbar' => 'sometimes|boolean',
            'kapazitaet' => 'nullable|integer|min:0',
            'flaeche_qm' => 'nullable|numeric|min:0|max:999999.99',
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

    private function normalizeRaumVerwaltung(array $validated, ?Raeume $raum = null): array
    {
        $validated['status'] = $validated['status'] ?? $raum?->status ?? 'verfuegbar';
        $validated['aktiv'] = $validated['aktiv'] ?? $raum?->aktiv ?? true;
        $validated['buchbar'] = $validated['buchbar'] ?? $raum?->buchbar ?? true;

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

    private function authorizeAnyRoomPermission(array $actions): void
    {
        $user = auth()->user();
        $permissions = [];

        foreach ($actions as $action) {
            $permissions[] = 'raeumlichkeiten.' . $action;
            $permissions[] = 'räumlichkeiten.' . $action;
        }

        abort_unless($this->canAny($user, $permissions), 403);
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

    private function raumStatus(): array
    {
        return [
            'verfuegbar',
            'eingeschraenkt',
            'wartung',
            'gesperrt',
        ];
    }

    private function meldungStatus(): array
    {
        return [
            'offen',
            'in_bearbeitung',
            'wartet_auf_extern',
            'behoben',
            'erledigt',
        ];
    }

    private function buchungRules(): array
    {
        return [
            'raum_id' => 'required|integer|exists:raeumes,id',
            'projekt_id' => 'nullable|integer|exists:projekts,id',
            'gruppe_id' => 'nullable|integer|exists:gruppes,id',
            'titel' => 'required|string|max:150',
            'typ' => ['required', Rule::in(['buchung', 'wartung', 'sperre'])],
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'teilnehmerzahl' => 'nullable|integer|min:0',
            'status' => ['required', Rule::in(['reserviert', 'bestaetigt', 'storniert'])],
            'zweck' => 'nullable|string|max:1000',
            'bemerkung' => 'nullable|string|max:1000',
        ];
    }

    private function ensureRaumBuchbar(Raeume $raum, string $typ): void
    {
        if (in_array($typ, ['wartung', 'sperre'], true)) {
            return;
        }

        if ($raum->aktiv === false || $raum->buchbar === false || in_array($raum->status, ['wartung', 'gesperrt'], true)) {
            throw ValidationException::withMessages([
                'raum_id' => 'Dieser Raum ist aktuell nicht buchbar.',
            ]);
        }
    }

    private function loadRaumDetails(Raeume $raum): Raeume
    {
        return $raum->load([
            'standort',
            'parent',
            'children',
            'standardPerson',
            'verantwortlichePerson',
            'meldungen.gemeldetVonPerson',
            'meldungen.zugewiesenAnPerson',
            'meldungen.behobenVonPerson',
            'buchungen.gebuchtVonPerson',
            'buchungen.projekt',
            'buchungen.gruppe.bereich',
            'gruppen.bereich',
            'gruppen.betreuer',
            'gruppen.projekt',
        ]);
    }

    private function loadBuchungDetails(RaumBuchung $buchung): RaumBuchung
    {
        return $buchung->load([
            'raum.standort',
            'gebuchtVonPerson',
            'projekt',
            'gruppe.bereich',
        ]);
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
