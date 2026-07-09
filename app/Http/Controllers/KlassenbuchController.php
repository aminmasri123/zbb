<?php

namespace App\Http\Controllers;

use App\Models\Gruppe;
use App\Models\Klassenbuch;
use App\Models\KlassenbuchEintrag;
use App\Models\KlassenbuchKommentar;
use App\Models\KlassenbuchTyp;
use App\Models\KlassenbuchWoche;
use App\Models\Projekt;
use App\Notifications\KlassenbuchWocheZurPruefungNotification;
use App\Services\NotificationRecipientService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class KlassenbuchController extends Controller
{
    public function __construct(private readonly NotificationRecipientService $notificationRecipients)
    {
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user->current_team_id) {
            return redirect()->route('dashboard')->with('error', 'Bitte wählen Sie zuerst ein Projekt aus.');
        }

        $this->ensureCurrentProjectUsesKlassenbuch($user);

        $gruppen = Gruppe::query()
            ->with(['bereich', 'betreuer', 'raum', 'projekt.abteilung', 'klassenbuecher.typ'])
            ->withCount([
                'teilnehmer as teilnehmer_count' => fn ($query) => $query->select(DB::raw('count(distinct personens.id)')),
            ])
            ->where('projekt_id', $user->current_team_id)
            ->when(!$this->canSeeAllGroups($user), fn ($query) => $query->where('personen_id', $this->userPersonId($user)))
            ->orderBy('anfangsdatum')
            ->orderBy('startzeit')
            ->get();

        $selectedGruppeId = $request->integer('gruppe_id');
        if (! $selectedGruppeId || ! $gruppen->contains('id', $selectedGruppeId)) {
            $selectedGruppeId = $gruppen->first()?->id;
        }

        $klassenbuecher = Klassenbuch::query()
            ->with(['typ', 'gruppe.bereich', 'gruppe.betreuer', 'gruppe.projekt.abteilung'])
            ->withCount(['wochen', 'offeneWochen', 'pruefungWochen', 'korrekturWochen', 'gesperrteWochen'])
            ->whereHas('gruppe', function ($query) use ($user) {
                $query->where('projekt_id', $user->current_team_id)
                    ->when(!$this->canSeeAllGroups($user), fn ($q) => $q->where('personen_id', $this->userPersonId($user)));
            })
            ->latest()
            ->get();

        $pruefungen = KlassenbuchWoche::query()
            ->with(['klassenbuch.typ', 'klassenbuch.gruppe.bereich', 'klassenbuch.gruppe.betreuer'])
            ->where('status', 'eingereicht')
            ->whereHas('klassenbuch.gruppe', fn ($query) => $query->where('projekt_id', $user->current_team_id))
            ->orderBy('submitted_at')
            ->take(20)
            ->get();

        return Inertia::render('Klassenbuch/Index', [
            'gruppen' => $gruppen,
            'klassenbuecher' => $klassenbuecher,
            'pruefungen' => $pruefungen,
            'typen' => KlassenbuchTyp::where('aktiv', true)->orderBy('name')->get(),
            'selectedGruppeId' => $selectedGruppeId,
            'canReview' => $this->userCanReviewInCurrentProject($user),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'gruppe_id' => ['required', 'integer', 'exists:gruppes,id'],
            'klassenbuch_typ_id' => ['required', 'integer', 'exists:klassenbuch_typen,id'],
            'titel' => ['nullable', 'string', 'max:255'],
            'schuljahr' => ['nullable', 'string', 'max:30'],
            'lehrjahr' => ['nullable', 'integer', 'min:1', 'max:6'],
        ]);

        $gruppe = Gruppe::with(['bereich', 'projekt'])->findOrFail($validated['gruppe_id']);
        $this->authorizeGruppe($gruppe);

        $typ = KlassenbuchTyp::findOrFail($validated['klassenbuch_typ_id']);
        $titel = $validated['titel'] ?: $this->defaultTitel($gruppe, $typ, $validated['schuljahr'] ?? null);

        $klassenbuch = Klassenbuch::firstOrCreate(
            [
                'gruppe_id' => $gruppe->id,
                'klassenbuch_typ_id' => $typ->id,
                'schuljahr' => $validated['schuljahr'] ?? null,
            ],
            [
                'created_by_user_id' => auth()->id(),
                'titel' => $titel,
                'lehrjahr' => $validated['lehrjahr'] ?? null,
                'status' => 'aktiv',
            ]
        );

        $this->ensureWochen($klassenbuch->load('gruppe'));

        return redirect()
            ->route('klassenbuch.show', $klassenbuch)
            ->with('success', 'Klassenbuch wurde angelegt.');
    }

    public function show(Klassenbuch $klassenbuch)
    {
        $klassenbuch->load([
            'typ',
            'gruppe.bereich',
            'gruppe.betreuer',
            'gruppe.raum',
            'gruppe.projekt.abteilung',
            'gruppe.teilnehmer',
        ]);

        $this->authorizeGruppe($klassenbuch->gruppe);
        $this->ensureWochen($klassenbuch);

        $wochen = $klassenbuch->wochen()
            ->withCount(['eintraege', 'kommentare'])
            ->get();

        return Inertia::render('Klassenbuch/Show', [
            'klassenbuch' => $klassenbuch->fresh(['typ', 'gruppe.bereich', 'gruppe.betreuer', 'gruppe.raum', 'gruppe.projekt.abteilung']),
            'wochen' => $wochen,
            'teilnehmer' => $this->teilnehmerListe($klassenbuch->gruppe),
            'canReview' => $this->canReview(auth()->user(), $klassenbuch),
        ]);
    }

    public function woche(Klassenbuch $klassenbuch, KlassenbuchWoche $woche)
    {
        $this->assertWocheBelongsToKlassenbuch($klassenbuch, $woche);

        $klassenbuch->load([
            'typ',
            'gruppe.bereich',
            'gruppe.betreuer',
            'gruppe.raum',
            'gruppe.projekt.abteilung',
            'gruppe.teilnehmer',
        ]);
        $this->authorizeGruppe($klassenbuch->gruppe);

        $canReview = $this->canReview(auth()->user(), $klassenbuch);

        $woche->load([
            'eintraege',
            'submittedBy.person',
            'reviewedBy.person',
            'kommentare' => fn ($query) => $query
                ->when(!$canReview, fn ($q) => $q->where('intern', false))
                ->with('user.person'),
        ]);

        return Inertia::render('Klassenbuch/Woche', [
            'klassenbuch' => $klassenbuch,
            'woche' => $woche,
            'wochentage' => $this->tageDerWoche($woche),
            'teilnehmer' => $this->teilnehmerListe($klassenbuch->gruppe),
            'canReview' => $canReview,
        ]);
    }

    public function storeEintrag(Request $request, Klassenbuch $klassenbuch, KlassenbuchWoche $woche)
    {
        $this->assertWocheBelongsToKlassenbuch($klassenbuch, $woche);
        $this->authorizeEditableWoche($klassenbuch, $woche);

        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:klassenbuch_eintraege,id'],
            'datum' => ['required', 'date'],
            'stunde' => ['nullable', 'integer', 'min:1', 'max:12'],
            'fach' => ['nullable', 'string', 'max:255'],
            'thema' => ['required', 'string', 'max:4000'],
            'azubi_nummern' => ['nullable', 'string', 'max:255'],
            'signum' => ['nullable', 'string', 'max:20'],
            'bemerkung' => ['nullable', 'string', 'max:2000'],
        ]);

        $datum = CarbonImmutable::parse($validated['datum']);
        if ($datum->lt($woche->start_datum) || $datum->gt($woche->end_datum)) {
            throw ValidationException::withMessages([
                'datum' => 'Das Datum liegt nicht in dieser Kalenderwoche.',
            ]);
        }

        $payload = [
            'datum' => $datum->toDateString(),
            'stunde' => $validated['stunde'] ?? null,
            'fach' => $validated['fach'] ?? null,
            'thema' => $validated['thema'],
            'azubi_nummern' => $validated['azubi_nummern'] ?? null,
            'signum' => $validated['signum'] ?? null,
            'bemerkung' => $validated['bemerkung'] ?? null,
            'updated_by_user_id' => auth()->id(),
        ];

        if (!empty($validated['id'])) {
            $eintrag = KlassenbuchEintrag::where('klassenbuch_woche_id', $woche->id)->findOrFail($validated['id']);
            $eintrag->update($payload);
        } else {
            $woche->eintraege()->create($payload + ['created_by_user_id' => auth()->id()]);
        }

        return redirect()->back()->with('success', 'Eintrag wurde gespeichert.');
    }

    public function destroyEintrag(Klassenbuch $klassenbuch, KlassenbuchWoche $woche, KlassenbuchEintrag $eintrag)
    {
        $this->assertWocheBelongsToKlassenbuch($klassenbuch, $woche);
        $this->authorizeEditableWoche($klassenbuch, $woche);

        abort_unless((int) $eintrag->klassenbuch_woche_id === (int) $woche->id, 404);
        $eintrag->delete();

        return redirect()->back()->with('success', 'Eintrag wurde entfernt.');
    }

    public function submit(Klassenbuch $klassenbuch, KlassenbuchWoche $woche)
    {
        $this->assertWocheBelongsToKlassenbuch($klassenbuch, $woche);
        $this->authorizeEditableWoche($klassenbuch, $woche);

        $woche->update([
            'status' => 'eingereicht',
            'submitted_by_user_id' => auth()->id(),
            'submitted_at' => now(),
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'locked_by_user_id' => null,
            'locked_at' => null,
        ]);

        $this->notifyReviewers($woche->fresh(['klassenbuch.gruppe.bereich', 'klassenbuch.gruppe.projekt']));

        return redirect()->back()->with('success', 'Woche wurde an die Abteilungsleitung gesendet.');
    }

    public function review(Request $request, Klassenbuch $klassenbuch, KlassenbuchWoche $woche)
    {
        $this->assertWocheBelongsToKlassenbuch($klassenbuch, $woche);
        $klassenbuch->loadMissing('gruppe.projekt');
        $this->authorizeGruppe($klassenbuch->gruppe);
        abort_unless($this->canReview(auth()->user(), $klassenbuch), 403);

        $validated = $request->validate([
            'entscheidung' => ['required', Rule::in(['ok', 'korrektur'])],
            'kommentar' => ['nullable', 'string', 'max:4000'],
            'intern' => ['nullable', 'boolean'],
        ]);

        if ($validated['entscheidung'] === 'korrektur' && empty($validated['kommentar'])) {
            throw ValidationException::withMessages([
                'kommentar' => 'Bitte schreiben Sie eine Rückmeldung, wenn eine Korrektur erforderlich ist.',
            ]);
        }

        if ($validated['entscheidung'] === 'ok') {
            $woche->update([
                'status' => 'gesperrt',
                'reviewed_by_user_id' => auth()->id(),
                'reviewed_at' => now(),
                'locked_by_user_id' => auth()->id(),
                'locked_at' => now(),
            ]);

            if (!empty($validated['kommentar'])) {
                $this->createKommentar($woche, $validated['kommentar'], 'pruefung', (bool) ($validated['intern'] ?? false));
            }

            return redirect()->back()->with('success', 'Woche wurde geprüft und gesperrt.');
        }

        $woche->update([
            'status' => 'korrektur',
            'reviewed_by_user_id' => auth()->id(),
            'reviewed_at' => now(),
            'locked_by_user_id' => null,
            'locked_at' => null,
        ]);

        $this->createKommentar($woche, $validated['kommentar'], 'korrektur', false);

        return redirect()->back()->with('warning', 'Woche wurde mit Rückmeldung zur Korrektur geöffnet.');
    }

    public function storeKommentar(Request $request, Klassenbuch $klassenbuch, KlassenbuchWoche $woche)
    {
        $this->assertWocheBelongsToKlassenbuch($klassenbuch, $woche);
        $this->authorizeGruppe($klassenbuch->gruppe);

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
            'typ' => ['nullable', Rule::in(['kommentar', 'notiz', 'korrektur'])],
            'intern' => ['nullable', 'boolean'],
        ]);

        $intern = (bool) ($validated['intern'] ?? false);
        if ($intern) {
            abort_unless($this->canReview(auth()->user(), $klassenbuch), 403);
        }

        $this->createKommentar($woche, $validated['text'], $validated['typ'] ?? 'kommentar', $intern);

        return redirect()->back()->with('success', 'Kommentar wurde gespeichert.');
    }

    public function updateKommentar(Request $request, Klassenbuch $klassenbuch, KlassenbuchWoche $woche, KlassenbuchKommentar $kommentar)
    {
        $this->assertWocheBelongsToKlassenbuch($klassenbuch, $woche);
        $klassenbuch->loadMissing('gruppe.projekt');
        $this->authorizeGruppe($klassenbuch->gruppe);
        abort_unless((int) $kommentar->klassenbuch_woche_id === (int) $woche->id, 404);
        abort_unless((int) $kommentar->user_id === (int) auth()->id() || $this->canReview(auth()->user(), $klassenbuch), 403);

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
        ]);

        $kommentar->update([
            'text' => $validated['text'],
            'edited_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Kommentar wurde aktualisiert.');
    }

    private function createKommentar(KlassenbuchWoche $woche, string $text, string $typ, bool $intern): void
    {
        $woche->kommentare()->create([
            'user_id' => auth()->id(),
            'typ' => $typ,
            'intern' => $intern,
            'text' => $text,
        ]);
    }

    private function authorizeEditableWoche(Klassenbuch $klassenbuch, KlassenbuchWoche $woche): void
    {
        $this->authorizeGruppe($klassenbuch->gruppe);

        abort_if($woche->status === 'gesperrt', 423, 'Diese Woche ist gesperrt.');
        abort_if($woche->status === 'eingereicht', 423, 'Diese Woche wartet auf Prüfung und ist vorläufig gesperrt.');
    }

    private function assertWocheBelongsToKlassenbuch(Klassenbuch $klassenbuch, KlassenbuchWoche $woche): void
    {
        abort_unless((int) $woche->klassenbuch_id === (int) $klassenbuch->id, 404);
    }

    private function authorizeGruppe(?Gruppe $gruppe): void
    {
        abort_unless($gruppe, 404);
        $gruppe->loadMissing('projekt');

        $user = auth()->user();
        $hasProject = (int) $gruppe->projekt_id === (int) $user->current_team_id;
        $ownsGroup = (int) $gruppe->personen_id === (int) $this->userPersonId($user);

        abort_unless((bool) $gruppe->projekt?->klassenbuch_aktiv, 403, 'Klassenbuch ist für dieses Projekt nicht aktiviert.');
        abort_unless($hasProject && ($ownsGroup || $this->canSeeAllGroups($user) || $this->canReviewByDepartment($user, $gruppe)), 403);
    }

    private function ensureCurrentProjectUsesKlassenbuch($user): void
    {
        $projekt = Projekt::query()->find($user?->current_team_id, ['id', 'klassenbuch_aktiv']);

        abort_unless($projekt && (bool) $projekt->klassenbuch_aktiv, 403, 'Klassenbuch ist für dieses Projekt nicht aktiviert.');
    }

    private function canSeeAllGroups($user): bool
    {
        return $this->canAny($user, ['gruppe.view.all', 'projekt.mitarbeiter.view.all']);
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

    private function canReview($user, Klassenbuch $klassenbuch): bool
    {
        if (! (bool) $klassenbuch->gruppe?->projekt?->klassenbuch_aktiv) {
            return false;
        }

        return $this->canSeeAllGroups($user)
            || $this->canReviewByDepartment($user, $klassenbuch->gruppe);
    }

    private function canReviewByDepartment($user, Gruppe $gruppe): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasAnyRole(['Administrator', 'Abteilungsleitung', 'Assistenz der Abt.-Leitung'])) {
            return true;
        }

        $abteilungId = $gruppe->projekt?->abteilung_id;
        if (!$abteilungId) {
            return false;
        }

        return DB::table('abteilungsassistents')
            ->where('abteilung_id', $abteilungId)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function userCanReviewInCurrentProject($user): bool
    {
        if (!$user?->current_team_id) {
            return false;
        }

        $gruppe = Gruppe::with('projekt')->where('projekt_id', $user->current_team_id)->first();

        return $gruppe ? $this->canReviewByDepartment($user, $gruppe) || $this->canSeeAllGroups($user) : $this->canSeeAllGroups($user);
    }

    private function userPersonId($user): ?int
    {
        return $user?->person_id ?? $user?->person?->id;
    }

    private function defaultTitel(Gruppe $gruppe, KlassenbuchTyp $typ, ?string $schuljahr): string
    {
        $bereich = $gruppe->bereich?->name ?: 'Gruppe ' . $gruppe->id;
        $jahr = $schuljahr ? ' ' . $schuljahr : '';

        return $typ->name . ' - ' . $bereich . $jahr;
    }

    private function ensureWochen(Klassenbuch $klassenbuch): void
    {
        $gruppe = $klassenbuch->gruppe;
        if (!$gruppe?->anfangsdatum) {
            return;
        }

        $start = CarbonImmutable::parse($gruppe->anfangsdatum)->startOfWeek();
        $end = CarbonImmutable::parse($gruppe->enddatum ?: $gruppe->anfangsdatum)->endOfWeek();

        for ($cursor = $start; $cursor->lte($end); $cursor = $cursor->addWeek()) {
            $weekStart = $cursor->max(CarbonImmutable::parse($gruppe->anfangsdatum));
            $weekEnd = $cursor->endOfWeek()->min(CarbonImmutable::parse($gruppe->enddatum ?: $gruppe->anfangsdatum));

            KlassenbuchWoche::firstOrCreate(
                [
                    'klassenbuch_id' => $klassenbuch->id,
                    'jahr' => (int) $cursor->isoWeekYear,
                    'kalenderwoche' => (int) $cursor->isoWeek,
                ],
                [
                    'start_datum' => $weekStart->toDateString(),
                    'end_datum' => $weekEnd->toDateString(),
                    'status' => 'offen',
                ]
            );
        }
    }

    private function tageDerWoche(KlassenbuchWoche $woche): array
    {
        $tage = [];
        $start = CarbonImmutable::parse($woche->start_datum);
        $end = CarbonImmutable::parse($woche->end_datum);

        for ($tag = $start; $tag->lte($end); $tag = $tag->addDay()) {
            if ($tag->isWeekend()) {
                continue;
            }

            $tage[] = [
                'datum' => $tag->toDateString(),
                'label' => $tag->locale('de')->isoFormat('dddd'),
                'kurz' => $tag->format('d.m.'),
            ];
        }

        return $tage;
    }

    private function teilnehmerListe(Gruppe $gruppe): Collection
    {
        $gruppe->loadMissing('teilnehmer');

        return $gruppe->teilnehmer
            ->unique('id')
            ->values()
            ->map(fn ($person, $index) => [
                'nr' => $index + 1,
                'id' => $person->id,
                'name' => trim(($person->vorname ?? '') . ' ' . ($person->nachname ?? '')),
            ]);
    }

    private function notifyReviewers(KlassenbuchWoche $woche): void
    {
        Notification::send(
            $this->notificationRecipients->forKlassenbuchWocheZurPruefung($woche, auth()->user()),
            new KlassenbuchWocheZurPruefungNotification($woche)
        );
    }
}
