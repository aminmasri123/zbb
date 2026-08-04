<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Gruppe;
use App\Models\Personen;
use App\Models\ProjektHasPersonen;
use App\Models\Raeume;
use App\Models\RaumHasPersonen;
use App\Models\Projekt;
use App\Services\RaumBelegungService;
use App\Services\Projects\ActiveProjectContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GruppeController extends Controller
{
    public function __construct(private readonly ActiveProjectContext $activeProjectContext)
    {
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $this->authorizeAny($user, ['gruppe.index']);

        $activeProject = $this->activeProjectContext->currentAvailableFor($user);

        if (!$activeProject) {
            return redirect()->back()->with('error', 'Bitte waehlen Sie ein Projekt aus.');
        }

        $projekt = $this->projektMitVerfuegbarenRaeumen($activeProject->id);
        $canSeeAllGroups = $this->canSeeAllGroups($user);

        $gruppen = Gruppe::query()
            ->with(['bereich', 'betreuer.user', 'partners', 'raum.parent', 'raum.standort', 'standort'])
            ->withCount([
                'teilnehmer as teilnehmer_count' => fn ($query) => $query->select(DB::raw('count(distinct personens.id)')),
            ])
            ->where('projekt_id', $activeProject->id)
            ->when(!$canSeeAllGroups, fn ($query) => $query->where('personen_id', $this->userPersonId($user)))
            ->latest('created_at')
            ->latest('id')
            ->get();

        return Inertia::render('Gruppe/Index', [
            'gruppen' => $gruppen,
            'projekt' => $projekt,
            'betreuer' => $this->betreuerOptions($projekt, $user, $canSeeAllGroups),
            'canSeeAllGroups' => $canSeeAllGroups,
        ]);
    }

    public function store(Request $request, RaumBelegungService $belegungService)
    {
        $user = auth()->user();
        $this->authorizeAny($user, ['gruppe.store']);
        $activeProject = $this->activeProjectContext->currentAvailableFor($user);
        abort_unless($activeProject, 409, 'Bitte wählen Sie zuerst ein aktives Projekt aus.');

        $request->merge([
            'ort_typ' => $request->input('ort_typ', 'raum'),
        ]);

        $validated = $request->validate([
            'startDate' => 'required|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'startZeit' => 'required|date_format:H:i',
            'endZeit' => 'required|date_format:H:i|after:startZeit',
            'bereich' => 'required|integer|exists:bereiches,id',
            'betreuer' => 'required|integer|exists:personens,id',
            'partner_ids' => ['nullable', 'array'],
            'partner_ids.*' => [
                'nullable',
                'integer',
                Rule::exists('projekt_has_partners', 'partner_id')
                    ->where(fn ($query) => $query->where('projekt_id', $activeProject->id)),
            ],
            'ort_typ' => ['required', Rule::in(['raum', 'extern'])],
            'raum_id' => 'nullable|required_if:ort_typ,raum|integer|exists:raeumes,id',
            'standort_id' => 'nullable|required_if:ort_typ,extern|integer|exists:standorts,id',
            'externer_ort' => 'nullable|required_if:ort_typ,extern|string|max:255',
            'bemerkung' => 'nullable|string|max:1000',
        ]);

        $projekt = $this->projektMitVerfuegbarenRaeumen($activeProject->id);
        $this->validateProjektZuordnung($projekt, (int) $validated['bereich'], $validated['raum_id'] ?? null);
        $standortId = $this->resolveStandortId($projekt, $validated);
        $this->validateBetreuer($user, $projekt, (int) $validated['betreuer']);
        $this->validateRaumBelegung($belegungService, $validated);

        $gruppe = Gruppe::create([
            'personen_id' => $validated['betreuer'],
            'bereich_id' => $validated['bereich'],
            'projekt_id' => $activeProject->id,
            'standort_id' => $standortId,
            'ort_typ' => $validated['ort_typ'],
            'raum_id' => $validated['ort_typ'] === 'raum' ? $validated['raum_id'] : null,
            'externer_ort' => $validated['ort_typ'] === 'extern' ? $validated['externer_ort'] : null,
            'anfangsdatum' => $validated['startDate'],
            'enddatum' => $validated['endDate'] ?? null,
            'startzeit' => $validated['startZeit'],
            'endzeit' => $validated['endZeit'],
            'bemerkung' => $validated['bemerkung'] ?? null,
        ]);
        $gruppe->partners()->sync($validated['partner_ids'] ?? []);

        return response()->json([
            'success' => true,
            'message' => 'Gruppe erfolgreich erstellt.',
            'gruppe' => $gruppe->load(['bereich', 'betreuer.user', 'partners', 'raum.parent', 'raum.standort', 'standort'])->loadCount([
                'teilnehmer as teilnehmer_count' => fn ($query) => $query->select(DB::raw('count(distinct personens.id)')),
            ]),
        ], 201);
    }

    public function update(Request $request, $id, RaumBelegungService $belegungService)
    {
        $user = auth()->user();
        $gruppe = Gruppe::findOrFail($id);
        $activeProject = $this->activeProjectContext->currentAvailableFor($user);
        abort_unless($activeProject && (int) $gruppe->projekt_id === $activeProject->id, 403);
        abort_unless($this->canManageGroup($user, $gruppe, 'gruppe.update'), 403);

        try {
            $request->merge([
                'ort_typ' => $request->input('ort_typ', $gruppe->ort_typ ?? 'raum'),
            ]);

            $validated = $request->validate([
                'bereich' => 'required|integer|exists:bereiches,id',
                'betreuer' => 'required|integer|exists:personens,id',
                'partner_ids' => ['nullable', 'array'],
                'partner_ids.*' => [
                    'nullable',
                    'integer',
                    Rule::exists('projekt_has_partners', 'partner_id')
                        ->where(fn ($query) => $query->where('projekt_id', $gruppe->projekt_id)),
                ],
                'ort_typ' => ['required', Rule::in(['raum', 'extern'])],
                'raum_id' => 'nullable|required_if:ort_typ,raum|integer|exists:raeumes,id',
                'standort_id' => 'nullable|required_if:ort_typ,extern|integer|exists:standorts,id',
                'externer_ort' => 'nullable|required_if:ort_typ,extern|string|max:255',
                'anfangsdatum' => 'required|date',
                'enddatum' => 'nullable|date|after_or_equal:anfangsdatum',
                'startzeit' => 'required|date_format:H:i',
                'endzeit' => 'required|date_format:H:i|after:startzeit',
                'bemerkung' => 'nullable|string|max:1000',
            ]);

            $projekt = $this->projektMitVerfuegbarenRaeumen((int) $gruppe->projekt_id);
            $this->validateProjektZuordnung($projekt, (int) $validated['bereich'], $validated['raum_id'] ?? null);
            $standortId = $this->resolveStandortId($projekt, $validated);
            $this->validateBetreuer($user, $projekt, (int) $validated['betreuer']);
            $this->validateRaumBelegung($belegungService, $validated, $gruppe->id);

            $gruppe->update([
                'bereich_id' => $validated['bereich'],
                'personen_id' => $validated['betreuer'],
                'standort_id' => $standortId,
                'ort_typ' => $validated['ort_typ'],
                'raum_id' => $validated['ort_typ'] === 'raum' ? $validated['raum_id'] : null,
                'externer_ort' => $validated['ort_typ'] === 'extern' ? $validated['externer_ort'] : null,
                'anfangsdatum' => $validated['anfangsdatum'],
                'enddatum' => $validated['enddatum'],
                'startzeit' => $validated['startzeit'],
                'endzeit' => $validated['endzeit'],
                'bemerkung' => $validated['bemerkung'] ?? null,
            ]);
            $gruppe->partners()->sync($validated['partner_ids'] ?? []);

            return response()->json([
                'success' => true,
                'message' => 'Gruppe erfolgreich aktualisiert.',
                'projekt' => $gruppe->load(['bereich', 'betreuer.user', 'partners', 'raum.parent', 'raum.standort', 'standort'])->loadCount([
                    'teilnehmer as teilnehmer_count' => fn ($query) => $query->select(DB::raw('count(distinct personens.id)')),
                ]),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validierungsfehler',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Fehler beim Aktualisieren der Gruppe: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ein unerwarteter Fehler ist aufgetreten.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $activeProject = $this->activeProjectContext->currentAvailableFor($user);
        abort_unless($activeProject, 409, 'Bitte wählen Sie zuerst ein aktives Projekt aus.');

        try {
            $gruppe = Gruppe::findOrFail($id);
            abort_unless((int) $gruppe->projekt_id === $activeProject->id, 403);
            abort_unless($this->canManageGroup($user, $gruppe, 'gruppe.destroy'), 403);

            $gruppe->delete();

            return response()->json(['message' => 'Gruppe erfolgreich geloescht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Gruppe nicht gefunden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
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

    private function authorizeAny($user, array $permissions): void
    {
        abort_unless($this->canAny($user, $permissions), 403);
    }

    private function canSeeAllGroups($user): bool
    {
        return $this->canAny($user, ['gruppe.view.all', 'projekt.mitarbeiter.view.all']);
    }

    private function canManageGroup($user, Gruppe $gruppe, string $permission): bool
    {
        if (!$user?->can($permission)) {
            return false;
        }

        if ($this->canSeeAllGroups($user)) {
            return true;
        }

        return (int) $gruppe->personen_id === (int) $this->userPersonId($user);
    }

    private function userPersonId($user): ?int
    {
        return $user?->person_id ?? $user?->person?->id;
    }

    private function projektMitVerfuegbarenRaeumen(int $projektId): Projekt
    {
        $projekt = Projekt::with([
            'bereiche',
            'mitarbeiter',
            'raeume.parent',
            'raeume.standardPerson',
            'raeume.standort',
            'standorte',
            'partners',
        ])->findOrFail($projektId);

        $raeume = $projekt->raeume->filter(fn ($raum) => $raum->aktiv !== false)->values();

        if ($raeume->isEmpty()) {
            $standortIds = $projekt->standorte->pluck('id')->filter()->unique()->values();

            $raeume = Raeume::query()
                ->with(['parent', 'standardPerson', 'standort'])
                ->where('aktiv', true)
                ->when($standortIds->isNotEmpty(), fn ($query) => $query->whereIn('standort_id', $standortIds))
                ->orderBy('name')
                ->get();
        }

        $projekt->setRelation('raeume', $raeume);
        $projekt->setRelation('mitarbeiter', $this->uniquePersonen($projekt->mitarbeiter));
        $projekt->setRelation('standorte', $this->uniqueStandorte($projekt->standorte));

        return $projekt;
    }

    private function uniquePersonen($personen)
    {
        return $personen
            ->unique('id')
            ->sortBy(fn ($person) => strtolower(($person->nachname ?? '') . ' ' . ($person->vorname ?? '')))
            ->values();
    }

    private function uniqueStandorte($standorte)
    {
        return $standorte
            ->unique('id')
            ->sortBy(fn ($standort) => strtolower($standort->name ?? ''))
            ->values();
    }

    private function betreuerOptions(Projekt $projekt, $user, bool $includeVertretungen)
    {
        $projectMemberIds = $projekt->mitarbeiter
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $personen = $includeVertretungen
            ? Personen::query()
                ->mitarbeiter()
                ->aktiv()
                ->with('user:id,person_id,profile_photo_path')
                ->orderBy('nachname')
                ->orderBy('vorname')
                ->get(['id', 'vorname', 'nachname', 'typ', 'aktiv'])
            : $this->uniquePersonen(collect([$user->person])->filter());

        $personIds = $personen
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $bereichZuweisungen = ProjektHasPersonen::query()
            ->where('projekt_id', $projekt->id)
            ->whereIn('personen_id', $personIds)
            ->with(['bereichZuweisungen.bereich:id,name', 'raumZuweisungen.raum.standort:id,name'])
            ->get()
            ->groupBy(fn (ProjektHasPersonen $assignment) => (int) $assignment->personen_id);

        return $this->uniquePersonen($personen)
            ->map(function (Personen $person) use ($projectMemberIds, $bereichZuweisungen) {
                $assignments = $bereichZuweisungen->get((int) $person->id, collect());
                $bereiche = $assignments
                    ->flatMap(fn (ProjektHasPersonen $assignment) => $assignment->bereichZuweisungen)
                    ->filter(fn ($zuweisung) => $zuweisung->bereich)
                    ->unique('bereich_id')
                    ->map(fn ($zuweisung) => [
                        'id' => (int) $zuweisung->bereich_id,
                        'name' => $zuweisung->bereich->name,
                    ])
                    ->values();

                $defaultBereich = $assignments
                    ->flatMap(fn (ProjektHasPersonen $assignment) => $assignment->bereichZuweisungen)
                    ->first(fn ($zuweisung) => (bool) $zuweisung->is_default);
                $raumZuweisungen = $assignments
                    ->flatMap(fn (ProjektHasPersonen $assignment) => $assignment->raumZuweisungen)
                    ->filter(fn ($zuweisung) => $zuweisung->raum);
                $bueroRaeume = $this->raumOptionsForAssignmentType($raumZuweisungen, RaumHasPersonen::TYPE_BUERO);
                $arbeitsbereichRaeume = $this->raumOptionsForAssignmentType($raumZuweisungen, RaumHasPersonen::TYPE_ARBEITSBEREICH);
                $defaultBueroRaum = $raumZuweisungen
                    ->where('assignment_type', RaumHasPersonen::TYPE_BUERO)
                    ->first(fn ($zuweisung) => (bool) $zuweisung->is_default);
                $defaultArbeitsbereichRaum = $raumZuweisungen
                    ->where('assignment_type', RaumHasPersonen::TYPE_ARBEITSBEREICH)
                    ->first(fn ($zuweisung) => (bool) $zuweisung->is_default);

                return [
                    'id' => (int) $person->id,
                    'vorname' => $person->vorname,
                    'nachname' => $person->nachname,
                    'is_project_member' => $projectMemberIds->contains((int) $person->id),
                    'bereiche' => $bereiche,
                    'default_bereich_id' => $defaultBereich ? (int) $defaultBereich->bereich_id : null,
                    'raeume' => [
                        RaumHasPersonen::TYPE_BUERO => $bueroRaeume,
                        RaumHasPersonen::TYPE_ARBEITSBEREICH => $arbeitsbereichRaeume,
                    ],
                    'default_buero_raum_id' => $defaultBueroRaum ? (int) $defaultBueroRaum->raum_id : null,
                    'default_arbeitsbereich_raum_id' => $defaultArbeitsbereichRaum ? (int) $defaultArbeitsbereichRaum->raum_id : null,
                ];
            })
            ->values();
    }

    private function raumOptionsForAssignmentType($raumZuweisungen, string $assignmentType)
    {
        return $raumZuweisungen
            ->where('assignment_type', $assignmentType)
            ->unique('raum_id')
            ->map(function ($zuweisung) {
                $raum = $zuweisung->raum;

                return [
                    'id' => (int) $raum->id,
                    'name' => $raum->name,
                    'typ' => $raum->typ,
                    'standort_id' => $raum->standort_id ? (int) $raum->standort_id : null,
                    'standort' => $raum->standort ? [
                        'id' => (int) $raum->standort->id,
                        'name' => $raum->standort->name,
                    ] : null,
                ];
            })
            ->values();
    }

    private function validateProjektZuordnung(Projekt $projekt, int $bereichId, ?int $raumId): void
    {
        if (!$projekt->bereiche->contains('id', $bereichId)) {
            throw ValidationException::withMessages([
                'bereich' => 'Der Bereich gehoert nicht zum ausgewaehlten Projekt.',
            ]);
        }

        if ($raumId && !$projekt->raeume->contains('id', (int) $raumId)) {
            throw ValidationException::withMessages([
                'raum_id' => 'Der Raum ist fuer dieses Projekt nicht verfuegbar.',
            ]);
        }
    }

    private function resolveStandortId(Projekt $projekt, array $validated): int
    {
        if (($validated['ort_typ'] ?? 'raum') === 'raum') {
            $raum = $projekt->raeume->firstWhere('id', (int) ($validated['raum_id'] ?? 0));

            if (! $raum?->standort_id) {
                throw ValidationException::withMessages([
                    'raum_id' => 'Der Raum hat keinen gueltigen Standort.',
                ]);
            }

            return (int) $raum->standort_id;
        }

        $standortId = (int) ($validated['standort_id'] ?? 0);

        if (! $projekt->standorte->contains('id', $standortId)) {
            throw ValidationException::withMessages([
                'standort_id' => 'Der Standort gehoert nicht zum ausgewaehlten Projekt.',
            ]);
        }

        return $standortId;
    }

    private function validateRaumBelegung(RaumBelegungService $belegungService, array $validated, ?int $ignoreGruppeId = null): void
    {
        if (($validated['ort_typ'] ?? 'raum') !== 'raum' || empty($validated['raum_id'])) {
            return;
        }

        $startDate = $validated['startDate'] ?? $validated['anfangsdatum'];
        $endDate = $validated['endDate'] ?? $validated['enddatum'] ?? $startDate;
        $startTime = $validated['startZeit'] ?? $validated['startzeit'];
        $endTime = $validated['endZeit'] ?? $validated['endzeit'];

        $belegungService->assertAvailable(
            (int) $validated['raum_id'],
            Carbon::parse($startDate . ' ' . $startTime),
            Carbon::parse($endDate . ' ' . $endTime),
            null,
            $ignoreGruppeId
        );
    }

    private function validateBetreuer($user, Projekt $projekt, int $betreuerId): void
    {
        $isProjectMember = $projekt->mitarbeiter->contains('id', $betreuerId);

        if ($isProjectMember) {
            if (!$this->canAny($user, ['projekt.mitarbeiter.view.all', 'gruppe.view.all']) && $betreuerId !== (int) $this->userPersonId($user)) {
                throw ValidationException::withMessages([
                    'betreuer' => 'Sie duerfen nur eigene Gruppen anlegen oder bearbeiten.',
                ]);
            }

            return;
        }

        if ($this->canSeeAllGroups($user) && Personen::query()->mitarbeiter()->aktiv()->whereKey($betreuerId)->exists()) {
            return;
        }

        if (!$isProjectMember) {
            throw ValidationException::withMessages([
                'betreuer' => 'Der Betreuer gehoert nicht zum ausgewaehlten Projekt oder ist nicht als Vertretung verfuegbar.',
            ]);
        }
    }
}
