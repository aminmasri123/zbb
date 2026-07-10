<?php

namespace App\Http\Controllers;

use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Personen;
use App\Models\PotenzialanalyseBericht;
use App\Models\PotenzialanalyseBeurteilung;
use App\Models\PotenzialanalyseKompetenzbewertung;
use App\Models\PotenzialanalyseKriterium;
use App\Models\PotenzialanalyseSelbsteinschaetzung;
use App\Models\PotenzialanalyseUebung;
use App\Models\PotenzialanalyseUebungErgebnis;
use App\Models\Projekt;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PotenzialanalyseController extends Controller
{
    private const PA_MERKMALE = [
        'feinmotorik',
        'grobmotorik',
        'wahrnehmung_symmetrie',
        'analyse_problemloesefaehigkeit',
        'arbeitsplanung',
        'motivation_leistungsbereitschaft',
        'durchhaltevermoegen',
        'sorgfalt',
        'kommunikation',
        'teamfaehigkeit',
        'umgangsformen',
    ];

    public function storeUebung(Request $request, Projekt $projekt)
    {
        $this->authorizeProjectConfig($projekt);
        $this->ensureProjektUsesPotenzialanalyse($projekt);

        $validated = $this->validateUebung($request, $projekt);

        PotenzialanalyseUebung::create([
            ...$validated,
            'projekt_id' => $projekt->id,
        ]);

        return response()->json([
            'message' => 'Uebung wurde angelegt.',
            'uebungen' => $this->projektUebungen($projekt),
        ], 201);
    }

    public function updateUebung(Request $request, PotenzialanalyseUebung $uebung)
    {
        $uebung->load('projekt');
        $this->authorizeProjectConfig($uebung->projekt);

        $uebung->update($this->validateUebung($request, $uebung->projekt));

        return response()->json([
            'message' => 'Uebung wurde aktualisiert.',
            'uebungen' => $this->projektUebungen($uebung->projekt),
        ]);
    }

    public function destroyUebung(PotenzialanalyseUebung $uebung)
    {
        $uebung->load('projekt');
        $this->authorizeProjectConfig($uebung->projekt);
        $projekt = $uebung->projekt;

        $uebung->delete();

        return response()->json([
            'message' => 'Uebung wurde geloescht.',
            'uebungen' => $this->projektUebungen($projekt),
        ]);
    }

    public function storeKriterium(Request $request, PotenzialanalyseUebung $uebung)
    {
        $uebung->load('projekt');
        $this->authorizeProjectConfig($uebung->projekt);

        PotenzialanalyseKriterium::create([
            ...$this->validateKriterium($request),
            'uebung_id' => $uebung->id,
        ]);

        return response()->json([
            'message' => 'Kriterium wurde angelegt.',
            'uebungen' => $this->projektUebungen($uebung->projekt),
        ], 201);
    }

    public function updateKriterium(Request $request, PotenzialanalyseKriterium $kriterium)
    {
        $kriterium->load('uebung.projekt');
        $this->authorizeProjectConfig($kriterium->uebung->projekt);

        $kriterium->update($this->validateKriterium($request));

        return response()->json([
            'message' => 'Kriterium wurde aktualisiert.',
            'uebungen' => $this->projektUebungen($kriterium->uebung->projekt),
        ]);
    }

    public function destroyKriterium(PotenzialanalyseKriterium $kriterium)
    {
        $kriterium->load('uebung.projekt');
        $this->authorizeProjectConfig($kriterium->uebung->projekt);
        $projekt = $kriterium->uebung->projekt;

        $kriterium->delete();

        return response()->json([
            'message' => 'Kriterium wurde geloescht.',
            'uebungen' => $this->projektUebungen($projekt),
        ]);
    }

    public function updateTeilnehmer(Request $request, Gruppe $gruppe, Personen $personen)
    {
        $gruppe->loadMissing('projekt');
        abort_unless($this->canUseGroup(auth()->user(), $gruppe), 403);
        $this->ensureProjektUsesPotenzialanalyse($gruppe->projekt);
        $this->ensureTeilnehmerInGroup($gruppe, $personen);

        $validated = $request->validate([
            'uebungen' => ['nullable', 'array'],
            'uebungen.*.punkte' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'uebungen.*.zeit_min' => ['nullable', 'integer', 'min:0', 'max:999'],
            'uebungen.*.zeit_sec' => ['nullable', 'integer', 'min:0', 'max:59'],
            'uebungen.*.zeit' => ['nullable', 'integer', 'min:0', 'max:59999'],
            'selbsteinschaetzung' => ['nullable', 'array'],
            'selbsteinschaetzung.*.bewertung' => ['nullable', 'integer', 'min:1', 'max:5'],
            'selbsteinschaetzung.*.bemerkung' => ['nullable', 'string', 'max:5000'],
            'kompetenzen' => ['nullable', 'array'],
            'kompetenzen.*.bewertung' => ['nullable', 'integer', 'min:1', 'max:5'],
            'kompetenzen.*.bemerkung' => ['nullable', 'string', 'max:5000'],
            'beurteilungen' => ['nullable', 'array'],
            'beurteilungen.*.bewertung' => ['nullable', 'integer', 'min:1', 'max:5'],
            'beurteilungen.*.bemerkung' => ['nullable', 'string', 'max:5000'],
            'selbsteinschaetzungen' => ['nullable', 'array'],
            'selbsteinschaetzungen.*.bewertung' => ['nullable', 'integer', 'min:1', 'max:5'],
            'selbsteinschaetzungen.*.bemerkung' => ['nullable', 'string', 'max:5000'],
            'bericht' => ['nullable', 'array'],
            'bericht.status' => ['nullable', Rule::in(['entwurf', 'in_bearbeitung', 'fertig', 'geprueft'])],
            'bericht.staerken' => ['nullable', 'string'],
            'bericht.entwicklungsfelder' => ['nullable', 'string'],
            'bericht.empfehlung' => ['nullable', 'string'],
            'bericht.bericht_text' => ['nullable', 'string'],
        ]);

        $kriteriumIds = $this->projektKriteriumIds((int) $gruppe->projekt_id);
        $uebungen = $this->projektUebungenMap((int) $gruppe->projekt_id);

        DB::transaction(function () use ($gruppe, $personen, $validated, $kriteriumIds, $uebungen) {
            $this->syncUebungErgebnisse(
                $validated['uebungen'] ?? [],
                $gruppe,
                $personen,
                $uebungen
            );

            $this->syncKompetenzbewertungen(
                'selbst',
                $validated['selbsteinschaetzung'] ?? [],
                $gruppe,
                $personen
            );

            $this->syncKompetenzbewertungen(
                'anleiter',
                $validated['kompetenzen'] ?? [],
                $gruppe,
                $personen
            );

            $this->syncBewertungen(
                PotenzialanalyseBeurteilung::class,
                $validated['beurteilungen'] ?? [],
                $gruppe,
                $personen,
                $kriteriumIds,
                false
            );

            $this->syncBewertungen(
                PotenzialanalyseSelbsteinschaetzung::class,
                $validated['selbsteinschaetzungen'] ?? [],
                $gruppe,
                $personen,
                $kriteriumIds,
                true
            );

            $this->syncBericht($validated['bericht'] ?? [], $gruppe, $personen);
        });

        return response()->json([
            'message' => 'Potenzialanalyse wurde gespeichert.',
            'teilnehmer' => $this->teilnehmerPayload($gruppe, $personen->id),
        ]);
    }

    private function validateUebung(Request $request, Projekt $projekt): array
    {
        $maxTag = max(1, (int) ($projekt->potenzialanalyse_tage ?: 60));

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'tag' => ['nullable', 'integer', 'min:1', 'max:' . $maxTag],
            'beschreibung' => ['nullable', 'string'],
            'hoechstwert' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'auswertbar' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'aktiv' => ['nullable', 'boolean'],
        ]) + [
            'hoechstwert' => null,
            'auswertbar' => false,
            'sort_order' => 0,
            'aktiv' => true,
        ];
    }

    private function validateKriterium(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'beschreibung' => ['nullable', 'string'],
            'skala_min' => ['nullable', 'integer', 'min:1', 'max:10'],
            'skala_max' => ['nullable', 'integer', 'min:1', 'max:10'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'aktiv' => ['nullable', 'boolean'],
        ]) + [
            'skala_min' => 1,
            'skala_max' => 5,
            'sort_order' => 0,
            'aktiv' => true,
        ];

        if ((int) $validated['skala_max'] < (int) $validated['skala_min']) {
            throw ValidationException::withMessages([
                'skala_max' => 'Die maximale Skala darf nicht kleiner als die minimale Skala sein.',
            ]);
        }

        return $validated;
    }

    private function syncUebungErgebnisse(
        array $entries,
        Gruppe $gruppe,
        Personen $personen,
        Collection $uebungen
    ): void {
        foreach ($entries as $uebungId => $entry) {
            $uebungId = (int) $uebungId;
            $uebung = $uebungen->get($uebungId);

            if (! $uebung) {
                continue;
            }

            $punkte = $entry['punkte'] ?? null;
            $punkte = $punkte === '' ? null : $punkte;

            if ($punkte !== null) {
                $punkte = (int) $punkte;

                if ($uebung->hoechstwert !== null && $punkte > (int) $uebung->hoechstwert) {
                    throw ValidationException::withMessages([
                        "uebungen.$uebungId.punkte" => "Die Punkte fuer {$uebung->name} duerfen maximal {$uebung->hoechstwert} betragen.",
                    ]);
                }
            }

            $zeit = $this->normalizeUebungZeit($entry);

            if ($punkte === null && $zeit === 0) {
                PotenzialanalyseUebungErgebnis::query()
                    ->where('gruppe_id', $gruppe->id)
                    ->where('personen_id', $personen->id)
                    ->where('uebung_id', $uebungId)
                    ->delete();
                continue;
            }

            PotenzialanalyseUebungErgebnis::updateOrCreate(
                [
                    'gruppe_id' => $gruppe->id,
                    'personen_id' => $personen->id,
                    'uebung_id' => $uebungId,
                ],
                [
                    'user_id' => auth()->id(),
                    'punkte' => $punkte,
                    'zeit' => $zeit,
                ]
            );
        }
    }

    private function syncKompetenzbewertungen(
        string $typ,
        array $entries,
        Gruppe $gruppe,
        Personen $personen
    ): void {
        foreach ($entries as $merkmal => $entry) {
            if (! in_array($merkmal, self::PA_MERKMALE, true)) {
                continue;
            }

            $bewertung = $entry['bewertung'] ?? null;
            $bewertung = $bewertung === '' ? null : $bewertung;
            $bemerkung = $entry['bemerkung'] ?? null;

            if ($bewertung === null && blank($bemerkung)) {
                PotenzialanalyseKompetenzbewertung::query()
                    ->where('gruppe_id', $gruppe->id)
                    ->where('personen_id', $personen->id)
                    ->where('typ', $typ)
                    ->where('merkmal', $merkmal)
                    ->delete();
                continue;
            }

            PotenzialanalyseKompetenzbewertung::updateOrCreate(
                [
                    'gruppe_id' => $gruppe->id,
                    'personen_id' => $personen->id,
                    'typ' => $typ,
                    'merkmal' => $merkmal,
                ],
                [
                    'user_id' => auth()->id(),
                    'bewertung' => $bewertung !== null ? (int) $bewertung : null,
                    'bemerkung' => $bemerkung,
                ]
            );
        }
    }

    private function normalizeUebungZeit(array $entry): int
    {
        if (array_key_exists('zeit', $entry) && $entry['zeit'] !== null && $entry['zeit'] !== '') {
            return max(0, (int) $entry['zeit']);
        }

        $minuten = max(0, (int) ($entry['zeit_min'] ?? 0));
        $sekunden = max(0, (int) ($entry['zeit_sec'] ?? 0));

        return ($minuten * 60) + $sekunden;
    }

    private function syncBewertungen(
        string $modelClass,
        array $entries,
        Gruppe $gruppe,
        Personen $personen,
        Collection $kriteriumIds,
        bool $submitted
    ): void {
        foreach ($entries as $kriteriumId => $entry) {
            $kriteriumId = (int) $kriteriumId;
            if (! $kriteriumIds->contains($kriteriumId)) {
                continue;
            }

            $payload = [
                'bewertung' => $entry['bewertung'] ?? null,
                'bemerkung' => $entry['bemerkung'] ?? null,
                'user_id' => auth()->id(),
            ];

            if ($submitted) {
                $payload['submitted_at'] = now();
            }

            $modelClass::updateOrCreate(
                [
                    'gruppe_id' => $gruppe->id,
                    'personen_id' => $personen->id,
                    'kriterium_id' => $kriteriumId,
                ],
                $payload
            );
        }
    }

    private function syncBericht(array $berichtData, Gruppe $gruppe, Personen $personen): void
    {
        $status = $berichtData['status'] ?? 'entwurf';
        $existing = PotenzialanalyseBericht::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $personen->id)
            ->first();

        PotenzialanalyseBericht::updateOrCreate(
            [
                'gruppe_id' => $gruppe->id,
                'personen_id' => $personen->id,
            ],
            [
                'user_id' => auth()->id(),
                'status' => $status,
                'staerken' => $berichtData['staerken'] ?? null,
                'entwicklungsfelder' => $berichtData['entwicklungsfelder'] ?? null,
                'empfehlung' => $berichtData['empfehlung'] ?? null,
                'bericht_text' => $berichtData['bericht_text'] ?? null,
                'fertiggestellt_at' => in_array($status, ['fertig', 'geprueft'], true)
                    ? ($existing?->fertiggestellt_at ?? now())
                    : null,
            ]
        );
    }

    private function projektUebungen(Projekt $projekt): Collection
    {
        return $projekt->fresh()
            ->potenzialanalyseUebungen()
            ->with('kriterien')
            ->get();
    }

    private function projektKriteriumIds(int $projektId): Collection
    {
        return PotenzialanalyseKriterium::query()
            ->whereHas('uebung', fn ($query) => $query->where('projekt_id', $projektId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    private function projektUebungenMap(int $projektId): Collection
    {
        return PotenzialanalyseUebung::query()
            ->where('projekt_id', $projektId)
            ->get()
            ->keyBy('id');
    }

    private function teilnehmerPayload(Gruppe $gruppe, int $personenId): array
    {
        return [
            'uebungen' => $this->uebungErgebnissePayload($gruppe, $personenId),
            'selbsteinschaetzung' => $this->kompetenzbewertungenPayload($gruppe, $personenId, 'selbst'),
            'kompetenzen' => $this->kompetenzbewertungenPayload($gruppe, $personenId, 'anleiter'),
            'beurteilungen' => PotenzialanalyseBeurteilung::query()
                ->where('gruppe_id', $gruppe->id)
                ->where('personen_id', $personenId)
                ->get()
                ->keyBy('kriterium_id')
                ->map(fn ($entry) => [
                    'bewertung' => $entry->bewertung,
                    'bemerkung' => $entry->bemerkung,
                ])
                ->all(),
            'selbsteinschaetzungen' => PotenzialanalyseSelbsteinschaetzung::query()
                ->where('gruppe_id', $gruppe->id)
                ->where('personen_id', $personenId)
                ->get()
                ->keyBy('kriterium_id')
                ->map(fn ($entry) => [
                    'bewertung' => $entry->bewertung,
                    'bemerkung' => $entry->bemerkung,
                ])
                ->all(),
            'bericht' => PotenzialanalyseBericht::query()
                ->where('gruppe_id', $gruppe->id)
                ->where('personen_id', $personenId)
                ->first()?->only([
                    'status',
                    'staerken',
                    'entwicklungsfelder',
                    'empfehlung',
                    'bericht_text',
                    'fertiggestellt_at',
                ]) ?? [
                    'status' => 'entwurf',
                    'staerken' => null,
                    'entwicklungsfelder' => null,
                    'empfehlung' => null,
                    'bericht_text' => null,
                    'fertiggestellt_at' => null,
            ],
        ];
    }

    private function uebungErgebnissePayload(Gruppe $gruppe, int $personenId): array
    {
        return PotenzialanalyseUebungErgebnis::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $personenId)
            ->get()
            ->keyBy('uebung_id')
            ->map(fn ($entry) => $this->formatUebungErgebnis($entry))
            ->all();
    }

    private function kompetenzbewertungenPayload(Gruppe $gruppe, int $personenId, string $typ): array
    {
        return PotenzialanalyseKompetenzbewertung::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $personenId)
            ->where('typ', $typ)
            ->get()
            ->keyBy('merkmal')
            ->map(fn ($entry) => [
                'bewertung' => $entry->bewertung,
                'bemerkung' => $entry->bemerkung,
            ])
            ->all();
    }

    private function formatUebungErgebnis(PotenzialanalyseUebungErgebnis $entry): array
    {
        $zeit = (int) ($entry->zeit ?? 0);

        return [
            'punkte' => $entry->punkte,
            'zeit' => $zeit,
            'zeit_min' => intdiv($zeit, 60),
            'zeit_sec' => $zeit % 60,
        ];
    }

    private function authorizeProjectConfig(?Projekt $projekt): void
    {
        abort_unless($projekt, 404);

        $user = auth()->user();
        abort_unless($user?->can('projekt.update') || $user?->can('projekt.store') || $user?->can('projekt.index'), 403);
    }

    private function ensureProjektUsesPotenzialanalyse(?Projekt $projekt): void
    {
        if (! $projekt?->potenzialanalyse_aktiv) {
            throw ValidationException::withMessages([
                'potenzialanalyse_aktiv' => 'Dieses Projekt nutzt keine Potenzialanalyse.',
            ]);
        }
    }

    private function ensureTeilnehmerInGroup(Gruppe $gruppe, Personen $personen): void
    {
        $exists = GruppeHasPersonen::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $personen->id)
            ->exists();

        abort_unless($exists, 404);
    }

    private function canUseGroup($user, ?Gruppe $gruppe): bool
    {
        if (!$user || !$gruppe) {
            return false;
        }

        if ($user->can('gruppe.view.all') || $user->can('projekt.mitarbeiter.view.all')) {
            return true;
        }

        return (int) $gruppe->personen_id === (int) $this->userPersonId($user);
    }

    private function userPersonId($user): ?int
    {
        return $user?->person_id ?? $user?->person?->id;
    }
}
