<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Gruppe;
use App\Models\Anwesenheitsstatuten;
use App\Models\Tage;
use App\Models\Personen;
use App\Models\ProjektHasPersonen;
use App\Models\Raeume;
use App\Models\RaumHasPersonen;
use App\Models\Projekt;
use App\Services\RaumBelegungService;
use App\Services\SaarlandWorkdayService;
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
    public function __construct(
        private readonly ActiveProjectContext $activeProjectContext,
        private readonly SaarlandWorkdayService $workdays,
    ) {
    }

    public function workdayPreview(Request $request)
    {
        $this->authorizeAny($request->user(), ['gruppe.store']);

        $validated = $request->validate([
            'groupType' => ['required', Rule::in(['1-day', '2-day', '3-day', 'unlimited'])],
            'startDate' => ['required', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
        ]);

        $start = Carbon::parse($validated['startDate'])->startOfDay();
        $end = match ($validated['groupType']) {
            '1-day' => $start->copy(),
            '2-day' => $this->workdays->endDateForDuration($start, 2),
            '3-day' => $this->workdays->endDateForDuration($start, 3),
            default => isset($validated['endDate'])
                ? Carbon::parse($validated['endDate'])->startOfDay()
                : $start->copy(),
        };

        if ($start->diffInDays($end) > 3660) {
            throw ValidationException::withMessages([
                'endDate' => 'Der Gruppenzeitraum darf maximal zehn Jahre umfassen.',
            ]);
        }

        return response()->json([
            'endDate' => $end->toDateString(),
            'nonWorkingDays' => $this->workdays->nonWorkingDays($start, $end),
        ]);
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
        $anwesendHeuteStatusId = $this->anwesendStatusId('anwesend');

        $gruppen = Gruppe::query()
            ->with(['bereich', 'betreuer.user', 'partners', 'raum.parent', 'raum.standort', 'standort'])
            ->withCount([
                'teilnehmer as teilnehmer_count' => fn ($query) => $query->select(DB::raw('count(distinct personens.id)')),
                'teilnehmer as anwesend_heute' => $this->anwesendHeuteCount(
                    Carbon::today(),
                    $anwesendHeuteStatusId
                ),
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
        $anwesendHeuteStatusId = $this->anwesendStatusId('anwesend');
        abort_unless($activeProject, 409, 'Bitte wählen Sie zuerst ein aktives Projekt aus.');

        $request->merge([
            'ort_typ' => $request->input('ort_typ', 'raum'),
        ]);

        $validated = $request->validate([
            'groupType' => ['required', Rule::in(['1-day', '2-day', '3-day', 'unlimited'])],
            'startDate' => 'required|date',
            'endDate' => ['required_if:groupType,unlimited', 'nullable', 'date', 'after_or_equal:startDate'],
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
            'non_working_dates' => ['nullable', 'array'],
            'non_working_dates.*' => ['date', 'distinct'],
            'allow_room_overlap' => ['sometimes', 'boolean'],
        ]);

        if ($validated['groupType'] !== 'unlimited') {
            $duration = match ($validated['groupType']) {
                '2-day' => 2,
                '3-day' => 3,
                default => 1,
            };
            $validated['endDate'] = $this->workdays
                ->endDateForDuration($validated['startDate'], $duration)
                ->toDateString();
        }

        $nonWorkingDates = $this->validatedNonWorkingDates(
            $validated['non_working_dates'] ?? [],
            $validated['startDate'],
            $validated['endDate'] ?? $validated['startDate'],
        );

        $projekt = $this->projektMitVerfuegbarenRaeumen($activeProject->id);
        $this->validateProjektZuordnung($projekt, (int) $validated['bereich'], $validated['raum_id'] ?? null);
        $standortId = $this->resolveStandortId($projekt, $validated);
        $this->validateBetreuer($user, $projekt, (int) $validated['betreuer']);
        $roomConflicts = $this->validateRaumBelegung($belegungService, $validated);

        if ($roomConflicts !== [] && ! ($validated['allow_room_overlap'] ?? false)) {
            return $this->roomConflictResponse($roomConflicts);
        }

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
            'non_working_dates' => $nonWorkingDates,
        ]);
        $gruppe->partners()->sync($validated['partner_ids'] ?? []);

        if ($roomConflicts !== [] && ($validated['allow_room_overlap'] ?? false)) {
            $this->logConfirmedRoomOverlap($request, $gruppe, $roomConflicts, 'created');
        }

        return response()->json([
            'success' => true,
            'message' => 'Gruppe erfolgreich erstellt.',
            'gruppe' => $gruppe->load(['bereich', 'betreuer.user', 'partners', 'raum.parent', 'raum.standort', 'standort'])->loadCount([
                'teilnehmer as teilnehmer_count' => fn ($query) => $query->select(DB::raw('count(distinct personens.id)')),
                'teilnehmer as anwesend_heute' => $this->anwesendHeuteCount(
                    Carbon::today(),
                    $anwesendHeuteStatusId
                ),
            ]),
        ], 201);
    }

    public function updateNonWorkingDate(Request $request, Gruppe $gruppe)
    {
        $activeProject = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($activeProject && (int) $gruppe->projekt_id === (int) $activeProject->id, 403);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'worked' => ['required', 'boolean'],
        ]);
        $date = Carbon::parse($validated['date'])->startOfDay();
        $start = Carbon::parse($gruppe->anfangsdatum)->startOfDay();
        $end = Carbon::parse($gruppe->enddatum ?: $gruppe->anfangsdatum)->startOfDay();

        if ($date->lt($start) || $date->gt($end)) {
            throw ValidationException::withMessages([
                'date' => 'Das Datum liegt ausserhalb des Gruppenzeitraums.',
            ]);
        }

        if ($this->workdays->isWorkday($date)) {
            throw ValidationException::withMessages([
                'date' => 'Fuer einen regulaeren Arbeitstag ist keine Freigabe erforderlich.',
            ]);
        }

        $dates = collect($gruppe->non_working_dates ?? [])
            ->map(fn ($value) => Carbon::parse($value)->toDateString())
            ->unique()
            ->values();

        $dates = $validated['worked']
            ? $dates->push($date->toDateString())->unique()->sort()->values()
            : $dates->reject(fn ($value) => $value === $date->toDateString())->values();

        $gruppe->update(['non_working_dates' => $dates->all()]);

        if ($validated['worked']) {
            $details = $this->workdays->details($date);
            Tage::updateOrCreate([
                'datum' => $date->toDateString(),
            ], [
                'wochentag' => $date->locale('de')->dayName,
                'feiertag_typ' => $details['type'] === 'holiday' ? 'gesetzlicher_feiertag' : 'kein_feiertag',
                'feiertag_name' => $details['type'] === 'holiday' ? $details['name'] : null,
            ]);
        }

        return response()->json([
            'message' => $validated['worked']
                ? 'Der Einsatz an diesem freien Tag wurde bestaetigt.'
                : 'Die Freigabe fuer den freien Tag wurde entfernt.',
            'non_working_dates' => $gruppe->fresh()->non_working_dates ?? [],
        ]);
    }

    public function update(Request $request, $id, RaumBelegungService $belegungService)
    {
        $user = auth()->user();
        $gruppe = Gruppe::findOrFail($id);
        $activeProject = $this->activeProjectContext->currentAvailableFor($user);
        abort_unless($activeProject && (int) $gruppe->projekt_id === $activeProject->id, 403);
        abort_unless($this->canManageGroup($user, $gruppe, 'gruppe.update'), 403);
        $anwesendHeuteStatusId = $this->anwesendStatusId('anwesend');

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
                'allow_room_overlap' => ['sometimes', 'boolean'],
            ]);

            $projekt = $this->projektMitVerfuegbarenRaeumen((int) $gruppe->projekt_id);
            $this->validateProjektZuordnung($projekt, (int) $validated['bereich'], $validated['raum_id'] ?? null);
            $standortId = $this->resolveStandortId($projekt, $validated);
            $this->validateBetreuer($user, $projekt, (int) $validated['betreuer']);
            $roomConflicts = $this->validateRaumBelegung($belegungService, $validated, $gruppe->id);

            if ($roomConflicts !== [] && ! ($validated['allow_room_overlap'] ?? false)) {
                return $this->roomConflictResponse($roomConflicts);
            }

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

            if ($roomConflicts !== [] && ($validated['allow_room_overlap'] ?? false)) {
                $this->logConfirmedRoomOverlap($request, $gruppe, $roomConflicts, 'updated');
            }

            return response()->json([
                'success' => true,
                'message' => 'Gruppe erfolgreich aktualisiert.',
                'projekt' => $gruppe->load(['bereich', 'betreuer.user', 'partners', 'raum.parent', 'raum.standort', 'standort'])->loadCount([
                    'teilnehmer as teilnehmer_count' => fn ($query) => $query->select(DB::raw('count(distinct personens.id)')),
                    'teilnehmer as anwesend_heute' => $this->anwesendHeuteCount(
                        Carbon::today(),
                        $anwesendHeuteStatusId
                    ),
                ]),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $this->firstValidationMessage($e->errors()),
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

    private function anwesendStatusId(string $status): ?int
    {
        return Anwesenheitsstatuten::query()
            ->where('status', $status)
            ->value('id');
    }

    private function anwesendHeuteCount(Carbon $today, ?int $anwesendStatusId): callable
    {
        return fn ($query) => $query
            ->select(DB::raw('count(distinct personens.id)'))
            ->when(
                $anwesendStatusId !== null,
                fn ($q) => $q->where('gruppe_has_personens.anwesenheitsstatuten_id', $anwesendStatusId)
            )
            ->when(
                $anwesendStatusId === null,
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->whereIn(
                'gruppe_has_personens.tage_id',
                fn ($q) => $q->from('tages')
                    ->select('id')
                    ->whereDate('datum', $today)
            );
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

    private function validateRaumBelegung(RaumBelegungService $belegungService, array $validated, ?int $ignoreGruppeId = null): array
    {
        if (($validated['ort_typ'] ?? 'raum') !== 'raum' || empty($validated['raum_id'])) {
            return [];
        }

        $startDate = $validated['startDate'] ?? $validated['anfangsdatum'];
        $endDate = $validated['endDate'] ?? $validated['enddatum'] ?? $startDate;
        $startTime = $validated['startZeit'] ?? $validated['startzeit'];
        $endTime = $validated['endZeit'] ?? $validated['endzeit'];

        return $belegungService->conflictsForGroup(
            (int) $validated['raum_id'],
            Carbon::parse($startDate . ' ' . $startTime),
            Carbon::parse($endDate . ' ' . $endTime),
            $ignoreGruppeId
        );
    }

    private function roomConflictResponse(array $conflicts)
    {
        $first = $conflicts[0];
        $roomName = $first['room']['name'];
        $period = $first['overlap']['label'];
        $occupiedBy = $first['occupied_by']['label'];
        $message = sprintf(
            'Der Raum "%s" ist am %s bereits durch "%s" belegt.',
            $roomName,
            $period,
            $occupiedBy,
        );

        return response()->json([
            'success' => false,
            'code' => 'room_conflict',
            'message' => $message,
            'question' => 'Waren beide Gruppen tatsächlich gleichzeitig in diesem Raum?',
            'conflicts' => $conflicts,
            'errors' => [
                'raum_id' => [$message],
            ],
        ], 409);
    }

    private function firstValidationMessage(array $errors): string
    {
        foreach ($errors as $messages) {
            if (is_array($messages) && isset($messages[0])) {
                return (string) $messages[0];
            }
        }

        return 'Bitte prüfen Sie die markierten Eingaben.';
    }

    private function logConfirmedRoomOverlap(Request $request, Gruppe $gruppe, array $conflicts, string $action): void
    {
        Log::notice('Doppelbelegung eines Raums wurde ausdruecklich bestaetigt.', [
            'action' => $action,
            'user_id' => $request->user()?->id,
            'gruppe_id' => $gruppe->id,
            'raum_id' => $gruppe->raum_id,
            'conflicts' => collect($conflicts)->map(fn (array $conflict) => [
                'type' => $conflict['type'],
                'occupied_id' => $conflict['occupied_by']['id'],
                'overlap' => $conflict['overlap']['label'],
            ])->values()->all(),
        ]);
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

    private function validatedNonWorkingDates(array $dates, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $validated = [];

        foreach (array_unique($dates) as $value) {
            $date = Carbon::parse($value)->startOfDay();

            if ($date->lt($start) || $date->gt($end)) {
                throw ValidationException::withMessages([
                    'non_working_dates' => 'Eine bestaetigte Ausnahme liegt ausserhalb des Gruppenzeitraums.',
                ]);
            }

            if ($this->workdays->isWorkday($date)) {
                throw ValidationException::withMessages([
                    'non_working_dates' => 'Nur Wochenenden und Feiertage duerfen als Sonderarbeitstage bestaetigt werden.',
                ]);
            }

            $validated[] = $date->toDateString();
        }

        sort($validated);

        return $validated;
    }
}

