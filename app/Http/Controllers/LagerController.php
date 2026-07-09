<?php

namespace App\Http\Controllers;

use App\Models\LagerArtikel;
use App\Models\LagerBewegung;
use App\Models\LagerReservierung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LagerController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeLager($request, 'lager.index');

        $artikel = LagerArtikel::query()
            ->with([
                'reservierungen' => fn ($query) => $query
                    ->with(['angefordertVonPerson', 'angefordertVonUser'])
                    ->where('status', LagerReservierung::STATUS_RESERVIERT)
                    ->latest(),
            ])
            ->withSum([
                'reservierungen as reserviert_sum' => fn ($query) => $query
                    ->where('status', LagerReservierung::STATUS_RESERVIERT),
            ], 'menge')
            ->when(! $request->user()->can('lager.artikel.update'), fn ($query) => $query->aktiv())
            ->orderBy('name')
            ->get()
            ->map(fn (LagerArtikel $artikel) => $this->withAvailability($artikel));

        return Inertia::render('Lager/Index', [
            'artikel' => $artikel,
            'currentUserId' => $request->user()->id,
            'lagerPermissions' => [
                'canCreateArtikel' => $request->user()->can('lager.artikel.store'),
                'canUpdateArtikel' => $request->user()->can('lager.artikel.update'),
                'canDeleteArtikel' => $request->user()->can('lager.artikel.destroy'),
                'canBookBewegung' => $request->user()->can('lager.bewegung.store'),
                'canReserve' => $request->user()->can('lager.reservierung.store'),
                'canUpdateReservierung' => $request->user()->can('lager.reservierung.update'),
            ],
        ]);
    }

    public function storeArtikel(Request $request)
    {
        $this->authorizeLager($request, 'lager.artikel.store');

        $validated = $this->normalizeArtikelData($request->validate($this->artikelRules()));
        $initialBestand = (float) ($validated['bestand'] ?? 0);

        DB::transaction(function () use ($request, $validated, $initialBestand) {
            $artikel = LagerArtikel::create($validated);

            if ($initialBestand > 0) {
                LagerBewegung::create([
                    'lager_artikel_id' => $artikel->id,
                    'gebucht_von_user_id' => $request->user()?->id,
                    'gebucht_von_personen_id' => $request->user()?->person_id,
                    'typ' => LagerBewegung::TYP_EINGANG,
                    'menge' => $initialBestand,
                    'bestand_nachher' => $initialBestand,
                    'bemerkung' => 'Initialbestand',
                ]);
            }
        });

        return back()->with('success', 'Lagerartikel wurde angelegt.');
    }

    public function updateArtikel(Request $request, LagerArtikel $artikel)
    {
        $this->authorizeLager($request, 'lager.artikel.update');

        $validated = $this->normalizeArtikelData($request->validate($this->artikelRules($artikel->id, false)));
        $artikel->update($validated);

        return back()->with('success', 'Lagerartikel wurde aktualisiert.');
    }

    public function destroyArtikel(Request $request, LagerArtikel $artikel)
    {
        $this->authorizeLager($request, 'lager.artikel.destroy');

        if ($artikel->bewegungen()->exists() || $artikel->reservierungen()->exists()) {
            $artikel->update(['aktiv' => false]);

            return back()->with('success', 'Lagerartikel wurde deaktiviert.');
        }

        $artikel->delete();

        return back()->with('success', 'Lagerartikel wurde geloescht.');
    }

    public function storeBewegung(Request $request, LagerArtikel $artikel)
    {
        $this->authorizeLager($request, 'lager.bewegung.store');

        $validated = $request->validate([
            'typ' => ['required', Rule::in([
                LagerBewegung::TYP_EINGANG,
                LagerBewegung::TYP_AUSGANG,
                LagerBewegung::TYP_KORREKTUR,
            ])],
            'menge' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'bemerkung' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $artikel, $validated) {
            $lockedArtikel = LagerArtikel::whereKey($artikel->id)->lockForUpdate()->firstOrFail();
            $bestand = (float) $lockedArtikel->bestand;
            $menge = (float) $validated['menge'];
            $reserviert = $this->reservedAmount($lockedArtikel->id);

            $bestandNachher = match ($validated['typ']) {
                LagerBewegung::TYP_EINGANG => $bestand + $menge,
                LagerBewegung::TYP_AUSGANG => $bestand - $menge,
                LagerBewegung::TYP_KORREKTUR => $menge,
            };

            if ($validated['typ'] === LagerBewegung::TYP_AUSGANG && $menge > max(0, $bestand - $reserviert)) {
                throw ValidationException::withMessages([
                    'menge' => 'Die Menge ist groesser als der aktuell verfuegbare Bestand.',
                ]);
            }

            if ($validated['typ'] === LagerBewegung::TYP_KORREKTUR && $bestandNachher < $reserviert) {
                throw ValidationException::withMessages([
                    'menge' => 'Die Korrektur darf nicht unter die bereits reservierte Menge fallen.',
                ]);
            }

            $lockedArtikel->update(['bestand' => $bestandNachher]);

            LagerBewegung::create([
                'lager_artikel_id' => $lockedArtikel->id,
                'gebucht_von_user_id' => $request->user()?->id,
                'gebucht_von_personen_id' => $request->user()?->person_id,
                'typ' => $validated['typ'],
                'menge' => $menge,
                'bestand_nachher' => $bestandNachher,
                'bemerkung' => $validated['bemerkung'] ?? null,
            ]);
        });

        return back()->with('success', 'Lagerbewegung wurde gebucht.');
    }

    public function storeReservierung(Request $request, LagerArtikel $artikel)
    {
        $this->authorizeLager($request, 'lager.reservierung.store');

        $validated = $request->validate([
            'menge' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'zweck' => ['nullable', 'string', 'max:255'],
            'bemerkung' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $artikel, $validated) {
            $lockedArtikel = LagerArtikel::whereKey($artikel->id)->lockForUpdate()->firstOrFail();

            if (! $lockedArtikel->aktiv) {
                throw ValidationException::withMessages([
                    'lager_artikel_id' => 'Dieser Lagerartikel ist nicht aktiv.',
                ]);
            }

            $menge = (float) $validated['menge'];
            $verfuegbar = max(0, (float) $lockedArtikel->bestand - $this->reservedAmount($lockedArtikel->id));

            if ($menge > $verfuegbar) {
                throw ValidationException::withMessages([
                    'menge' => 'Die Menge ist groesser als der aktuell verfuegbare Bestand.',
                ]);
            }

            LagerReservierung::create([
                'lager_artikel_id' => $lockedArtikel->id,
                'angefordert_von_user_id' => $request->user()?->id,
                'angefordert_von_personen_id' => $request->user()?->person_id,
                'menge' => $menge,
                'status' => LagerReservierung::STATUS_RESERVIERT,
                'zweck' => $validated['zweck'] ?? null,
                'bemerkung' => $validated['bemerkung'] ?? null,
            ]);
        });

        return back()->with('success', 'Artikel wurde intern reserviert.');
    }

    public function updateReservierung(Request $request, LagerReservierung $reservierung)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                LagerReservierung::STATUS_AUSGEGEBEN,
                LagerReservierung::STATUS_STORNIERT,
            ])],
            'bemerkung' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->authorizeReservierungUpdate($request, $reservierung, $validated['status']);

        if ($validated['status'] === LagerReservierung::STATUS_STORNIERT) {
            $reservierung->update([
                'status' => LagerReservierung::STATUS_STORNIERT,
                'storniert_at' => now(),
                'bemerkung' => $validated['bemerkung'] ?? $reservierung->bemerkung,
            ]);

            return back()->with('success', 'Reservierung wurde storniert.');
        }

        DB::transaction(function () use ($request, $reservierung, $validated) {
            $lockedReservierung = LagerReservierung::whereKey($reservierung->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReservierung->status !== LagerReservierung::STATUS_RESERVIERT) {
                throw ValidationException::withMessages([
                    'status' => 'Diese Reservierung ist bereits abgeschlossen.',
                ]);
            }

            $artikel = LagerArtikel::whereKey($lockedReservierung->lager_artikel_id)
                ->lockForUpdate()
                ->firstOrFail();

            $menge = (float) $lockedReservierung->menge;

            if ((float) $artikel->bestand < $menge) {
                throw ValidationException::withMessages([
                    'menge' => 'Der Bestand reicht fuer diese Ausgabe nicht mehr aus.',
                ]);
            }

            $bestandNachher = (float) $artikel->bestand - $menge;
            $artikel->update(['bestand' => $bestandNachher]);

            $lockedReservierung->update([
                'status' => LagerReservierung::STATUS_AUSGEGEBEN,
                'ausgegeben_at' => now(),
                'bemerkung' => $validated['bemerkung'] ?? $lockedReservierung->bemerkung,
            ]);

            LagerBewegung::create([
                'lager_artikel_id' => $artikel->id,
                'lager_reservierung_id' => $lockedReservierung->id,
                'gebucht_von_user_id' => $request->user()?->id,
                'gebucht_von_personen_id' => $request->user()?->person_id,
                'typ' => LagerBewegung::TYP_AUSGANG,
                'menge' => $menge,
                'bestand_nachher' => $bestandNachher,
                'bemerkung' => $validated['bemerkung'] ?? 'Ausgabe aus interner Lagerreservierung',
            ]);
        });

        return back()->with('success', 'Reservierung wurde ausgegeben.');
    }

    private function artikelRules(?int $artikelId = null, bool $includeBestand = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'kategorie' => ['nullable', 'string', 'max:80'],
            'artikelnummer' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('lager_artikel', 'artikelnummer')->ignore($artikelId),
            ],
            'einheit' => ['nullable', 'string', 'max:30'],
            'mindestbestand' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'lagerort' => ['nullable', 'string', 'max:255'],
            'lieferant' => ['nullable', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string', 'max:2000'],
            'aktiv' => ['sometimes', 'boolean'],
        ];

        if ($includeBestand) {
            $rules['bestand'] = ['nullable', 'numeric', 'min:0', 'max:999999999.99'];
        }

        return $rules;
    }

    private function withAvailability(LagerArtikel $artikel): LagerArtikel
    {
        $reserviert = (float) ($artikel->reserviert_sum ?? 0);
        $bestand = (float) $artikel->bestand;
        $mindestbestand = (float) $artikel->mindestbestand;

        $artikel->setAttribute('reserviert', round($reserviert, 2));
        $artikel->setAttribute('verfuegbar', round(max(0, $bestand - $reserviert), 2));
        $artikel->setAttribute('unter_mindestbestand', $mindestbestand > 0 && $bestand <= $mindestbestand);
        $artikel->makeHidden('reserviert_sum');

        return $artikel;
    }

    private function normalizeArtikelData(array $data): array
    {
        $data['einheit'] = $data['einheit'] ?? 'Stk';
        $data['mindestbestand'] = $data['mindestbestand'] ?? 0;

        if (array_key_exists('bestand', $data)) {
            $data['bestand'] = $data['bestand'] ?? 0;
        }

        return $data;
    }

    private function reservedAmount(int $artikelId): float
    {
        return (float) LagerReservierung::where('lager_artikel_id', $artikelId)
            ->where('status', LagerReservierung::STATUS_RESERVIERT)
            ->sum('menge');
    }

    private function authorizeLager(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission), 403);
    }

    private function authorizeReservierungUpdate(Request $request, LagerReservierung $reservierung, string $targetStatus): void
    {
        $user = $request->user();

        if ($user?->can('lager.reservierung.update')) {
            return;
        }

        $isOwnReservation = (int) $reservierung->angefordert_von_user_id === (int) $user?->id;
        $mayCancelOwn = $targetStatus === LagerReservierung::STATUS_STORNIERT
            && $isOwnReservation
            && $user?->can('lager.reservierung.store');

        abort_unless($mayCancelOwn, 403);
    }
}
