<?php

namespace App\Http\Controllers;

use App\Models\Materialanforderung;
use App\Models\MaterialanforderungGenehmigung;
use App\Models\MaterialanforderungLoeschprotokoll;
use App\Models\Projekt;
use App\Models\Standort;
use App\Models\PurchaseRule;
use App\Services\Purchasing\PurchaseWorkflow;
use App\Services\Purchasing\PurchaseOrderExport;
use App\Services\Purchasing\PurchaseOfferWriter;
use Illuminate\Support\Facades\Storage;
use App\Notifications\UpdateMaterialanforderungNotification;
use App\Services\NotificationRecipientService;
use App\Services\Projects\ActiveProjectContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MaterialanforderungController extends Controller
{
    public function __construct(
        private readonly NotificationRecipientService $notificationRecipients,
        private readonly ActiveProjectContext $activeProjectContext,
        private readonly PurchaseWorkflow $purchasing,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canOpenMaterialRequest($user) || $user->can('materialanforderung.index') || $user->can('materialanforderung.settings.manage'), 403);
        $search = trim((string) $request->input('search', ''));
        $activeProject = $this->activeProjectContext->currentAvailableFor($user);

        $query = Materialanforderung::with(['projekt', 'standort', 'besteller.person', 'artikeln', 'vergabevermerk'])
            ->withExists([
                'genehmigungen as von_mir_bearbeitet' => fn ($approval) =>
                    $approval->where('genehmiger_id', $user->id),
            ]);

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('materialanforderungs.id', 'like', "%{$search}%")
                    ->orWhere('materialanforderungs.bestellnummer', 'like', "%{$search}%")
                    ->orWhere('materialanforderungs.bemerkungen', 'like', "%{$search}%")
                    ->orWhere('materialanforderungs.kostenstelle', 'like', "%{$search}%")
                    ->orWhereHas('projekt', fn ($projekt) => $projekt->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('artikeln', fn ($artikel) => $artikel->where('artikel', 'like', "%{$search}%"));
            });
        }

        $assignedProjectIds = $user->projekte()->pluck('projekts.id');
        $currentProjectId = $user->current_team_id;
        $query->where(function ($visibility) use ($user, $assignedProjectIds, $currentProjectId) {
            $visibility->where(function ($own) use ($user, $currentProjectId) {
                $own->where('materialanforderungs.ersteller_id', $user->id);

                if ($currentProjectId) {
                    $own->where('projekt_id', $currentProjectId);
                }
            });

            if ($this->purchasing->isDirector($user)) {
                $visibility->orWhereIn('status', ['gf_pruefung', 'gf_genehmigt']);
            }

            // Anyone who took part in the approval keeps permanent read access.
            $visibility->orWhereHas('genehmigungen', fn ($approval) =>
                $approval->where('genehmiger_id', $user->id)
            );

            if ($user->can('materialanforderung.sachlische_freigabe.index')) {
                $visibility->orWhere(function ($approval) use ($assignedProjectIds) {
                    $approval->where('status', 'eingereicht')
                        ->whereIn('projekt_id', $assignedProjectIds);
                });
            }

            if ($user->can('materialanforderung.kaufmännische_freigabe.index')
                || $user->can('materialanforderung.kaufmännische_freigabe.update')
                || $user->can('materialanforderung.bestellwesen.update')) {
                $visibility->orWhereNotIn('status', ['entwurf', 'eingereicht']);
            }

            if ($user->can('materialanforderung.bestellte.destroy')) {
                $visibility->orWhereIn('status', ['bestellt', 'teilweise_geliefert']);
            }
        });

        return Inertia::render('Bestellungen/Materialanforderung/Index', [
            'anforderungen' => $query->latest()->get(),
            'filters' => ['search' => $search],
            'canManageRules' => $user->can('materialanforderung.settings.manage'),
            'canCreateRequest' => $user->can('materialanforderung.create')
                && $activeProject !== null,
            'canOpenRequest' => $this->canOpenMaterialRequest($user),
            'hasActiveProject' => $activeProject !== null,
        ]);
    }

    public function create(Request $request)
    {
        $projekt = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($projekt, 409, 'Zum Anlegen einer Materialanforderung muss ein Projekt zugewiesen und ausgewählt sein.');

        return Inertia::render('Bestellungen/Materialanforderung/Create', [
            'user' => $request->user()->person,
            'projekt' => $projekt,
            'kostenstellen' => $this->kostenstellen($projekt),
            'standorte' => Standort::orderBy('name')->get(['id', 'name']),
            'purchaseRules' => PurchaseRule::current(),
            'offerUploadLimits' => PurchaseOfferWriter::uploadLimits(),
        ]);
    }

    public function store(Request $request, PurchaseOfferWriter $offerWriter)
    {
        $projekt = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($projekt, 409, 'Zum Anlegen einer Materialanforderung muss ein Projekt zugewiesen und ausgewählt sein.');
        $rules = $this->requestRules($projekt) + [
            'angebote' => ['nullable', 'array', 'list', 'max:'.PurchaseOfferWriter::uploadLimits()['max_offers']],
            'angebote.*' => ['required', 'array'],
            'positionen.*.client_key' => ['required_with:angebote', 'nullable', 'string', 'max:64', 'distinct'],
        ];
        foreach (array_slice((array) $request->input('angebote', []), 0, PurchaseOfferWriter::uploadLimits()['max_offers'], true) as $index => $offer) {
            $rules += PurchaseOfferWriter::rules('angebote.'.$index.'.', 'position_key');
        }
        $data = $this->purchasing->normalizePrices($request->validate($rules));

        $paths = [];
        try {
            $anforderung = DB::transaction(function () use ($request, $projekt, $data, $offerWriter, &$paths) {
                [$netto, $brutto] = $this->calculateTotals($data['positionen'], $data['versand_netto'] ?? 0, $data['versand_mwst'] ?? 19, $data['versand_brutto']);

                $anforderung = Materialanforderung::create([
                    'projekt_id' => $projekt->id,
                    'standort_id' => $data['standort_id'] ?? null,
                    'preisart' => $data['preisart'], 'versand_brutto' => $data['versand_brutto'],
                    'versand_netto' => $data['versand_netto'] ?? 0, 'versand_mwst' => $data['versand_mwst'] ?? 19,
                    'lieferant_adresse' => $data['lieferant_adresse'] ?? null,
                    'lieferantenreferenz' => $data['lieferantenreferenz'] ?? null,
                    'kostenstelle' => $data['kostenstelle'],
                    'benoetigt_am' => $data['benoetigt_am'] ?? null,
                    'prioritaet' => $data['prioritaet'],
                    'ersteller_id' => $request->user()->id,
                    'bemerkungen' => $data['bemerkungen'] ?? null,
                    'gesamtpreis' => $netto,
                    'endsumme' => $brutto,
                    'status' => 'entwurf',
                ]);

                $itemIds = [];
                foreach ($data['positionen'] as $position) {
                    $item = $anforderung->artikeln()->create($this->positionValues($position));
                    if (isset($position['client_key'])) $itemIds[$position['client_key']] = $item->id;
                }

                $anforderung->vergabevermerk()->create($this->vergabeValues($data['vergabe'] ?? []));
                $this->purchasing->number($anforderung);
                $anforderung->refresh();
                foreach ($data['angebote'] ?? [] as $index => $offer) {
                    foreach ($offer['positionen'] as &$line) {
                        if (!isset($itemIds[$line['position_key']])) {
                            throw ValidationException::withMessages(['angebote.'.$index.'.positionen' => 'Eine Angebotsposition passt nicht mehr zum Bedarf. Bitte die Positionen prüfen.']);
                        }
                        $line['id'] = $itemIds[$line['position_key']];
                    }
                    unset($line);
                    $offerWriter->store($anforderung, $offer, $request->file('angebote.'.$index.'.datei'), (int) $request->user()->id, $paths, 'angebote.'.$index.'.');
                }

                return $anforderung;
            });
        } catch (\Throwable $e) {
            foreach ($paths as $path) Storage::disk('local')->delete($path);
            throw $e;
        }

        return redirect()->route('materialanforderung.show', $anforderung)
            ->with('success', empty($data['angebote']) ? 'Materialanforderung wurde als Entwurf gespeichert.' : 'Materialanforderung und Angebote wurden gemeinsam als Entwurf gespeichert.');
    }

    public function update(Request $request)
    {
        $anforderung = Materialanforderung::with(['artikeln', 'projekt'])->findOrFail($request->id);
        abort_unless($request->user()->can('materialanforderung.update'), 403);
        abort_unless((int) $anforderung->ersteller_id === (int) $request->user()->id, 403);
        abort_unless(in_array($anforderung->status, ['entwurf', 'zur_ueberarbeitung'], true), 403);

        $payload = $request->all();
        $payload['positionen'] = $payload['positionen'] ?? $payload['artikeln'] ?? [];
        $request->replace($payload);
        $data = $this->purchasing->normalizePrices($request->validate($this->requestRules($anforderung->projekt, true)));

        DB::transaction(function () use ($request, $anforderung, $data) {
            $anforderung = Materialanforderung::whereKey($anforderung->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($anforderung->status, ['entwurf', 'zur_ueberarbeitung'], true), 403);
            if (isset($data['revision']) && (int) $data['revision'] !== $anforderung->revision) {
                throw ValidationException::withMessages(['revision' => 'Die Materialanforderung wurde zwischenzeitlich geändert. Bitte neu laden.']);
            }
            $before = $this->purchasing->contentHash($anforderung);
            $keptIds = collect($data['positionen'])->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $anforderung->artikeln()->whereNotIn('id', $keptIds)->delete();

            foreach ($data['positionen'] as $position) {
                $values = $this->positionValues($position);
                $existing = isset($position['id']) ? $anforderung->artikeln()->find($position['id']) : null;
                $existing ? $existing->update($values) : $anforderung->artikeln()->create($values);
            }

            [$netto, $brutto] = $this->calculateTotals($data['positionen'], $data['versand_netto'] ?? 0, $data['versand_mwst'] ?? 19, $data['versand_brutto']);
            $anforderung->update([
                'standort_id' => $data['standort_id'] ?? null,
                'preisart' => $data['preisart'], 'versand_brutto' => $data['versand_brutto'],
                'versand_netto' => $data['versand_netto'] ?? 0, 'versand_mwst' => $data['versand_mwst'] ?? 19,
                'lieferant_adresse' => $data['lieferant_adresse'] ?? null,
                'lieferantenreferenz' => $data['lieferantenreferenz'] ?? null,
                'kostenstelle' => $data['kostenstelle'],
                'benoetigt_am' => $data['benoetigt_am'] ?? null,
                'prioritaet' => $data['prioritaet'],
                'bemerkungen' => $data['bemerkungen'] ?? null,
                'gesamtpreis' => $netto,
                'endsumme' => $brutto,
            ]);

            $anforderung->vergabevermerk()->updateOrCreate(
                ['anforderung_id' => $anforderung->id],
                $this->vergabeValues($data['vergabe'] ?? [])
            );
            if ($before !== $this->purchasing->contentHash($anforderung->fresh())) {
                $anforderung->update(['revision' => $anforderung->revision + 1, 'approval_policy' => null, 'selected_offer_id' => null]);
            }
        });

        return back()->with('success', 'Materialanforderung wurde aktualisiert.');
    }

    public function destroy(Request $request, Materialanforderung $materialanforderung)
    {
        $isEditableDraft = in_array($materialanforderung->status, ['entwurf', 'zur_ueberarbeitung'], true);
        $isFinalized = in_array($materialanforderung->status, ['bestellt', 'teilweise_geliefert', 'geliefert'], true);

        if ($isEditableDraft) {
            abort_unless($request->user()->can('materialanforderung.destroy'), 403);
            abort_unless((int) $materialanforderung->ersteller_id === (int) $request->user()->id, 403);

            try {
                DB::transaction(function () use ($materialanforderung) {
                    $this->deleteMaterialanforderungNotifications($materialanforderung->id);
                    $materialanforderung->delete();
                });
            } catch (\Throwable $exception) {
                report($exception);

                return back()->with('error', 'Die Materialanforderung konnte nicht gelöscht werden. Es wurden keine Daten entfernt.');
            }

            return redirect()->route('materialanforderung.index')
                ->with('success', "Materialanforderung #{$materialanforderung->id} wurde erfolgreich gelöscht.");
        }

        abort_unless($isFinalized, 422, 'Nur bestellte oder gelieferte Materialanforderungen können mit dem Sonderrecht endgültig gelöscht werden.');
        abort_unless($request->user()->can('materialanforderung.bestellte.destroy'), 403);

        $expectedConfirmation = "LÖSCHEN #{$materialanforderung->id}";
        $data = $request->validate([
            'begruendung' => ['required', 'string', 'min:10', 'max:2000'],
            'bestaetigung' => ['required', 'string', Rule::in([$expectedConfirmation])],
        ], [
            'begruendung.required' => 'Eine Löschbegründung ist erforderlich.',
            'begruendung.min' => 'Die Löschbegründung muss mindestens 10 Zeichen enthalten.',
            'bestaetigung.in' => "Bitte geben Sie exakt „{$expectedConfirmation}“ ein.",
        ]);

        try {
            DB::transaction(function () use ($request, $materialanforderung, $data) {
                $materialanforderung->loadMissing([
                    'projekt',
                    'besteller.person',
                    'artikeln',
                    'vergabevermerk',
                    'genehmigungen.genehmiger.person',
                ]);

                MaterialanforderungLoeschprotokoll::create([
                    'materialanforderung_id' => $materialanforderung->id,
                    'projekt_id' => $materialanforderung->projekt_id,
                    'ersteller_id' => $materialanforderung->ersteller_id,
                    'geloescht_von_id' => $request->user()->id,
                    'status' => $materialanforderung->status,
                    'bestellnummer' => $materialanforderung->bestellnummer ?? $materialanforderung->vergabevermerk?->bestellnummer,
                    'endsumme' => $materialanforderung->endsumme,
                    'begruendung' => $data['begruendung'],
                    'snapshot' => $this->deletionSnapshot($materialanforderung),
                    'geloescht_am' => now(),
                ]);

                $this->deleteMaterialanforderungNotifications($materialanforderung->id);
                $materialanforderung->delete();
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Die bestellte Materialanforderung konnte nicht gelöscht werden. Es wurden keine Daten entfernt.');
        }

        return redirect()->route('materialanforderung.index')
            ->with('success', "Materialanforderung #{$materialanforderung->id} und alle zugehörigen Daten wurden endgültig gelöscht. Das Löschprotokoll bleibt erhalten.");
    }

    public function show(Request $request, $id)
    {
        $anforderung = Materialanforderung::with(['projekt', 'standort', 'angebote', 'besteller.person', 'artikeln', 'vergabevermerk'])
            ->findOrFail($id);
        abort_unless($this->mayView($request->user(), $anforderung), 403);

        $verlauf = MaterialanforderungGenehmigung::with('genehmiger.person')
            ->where('anforderung_id', $id)
            ->latest()
            ->get();

        $kommentare = $anforderung->kommentare()
            ->with([
                'user.person:id,vorname,nachname',
                'artikel:id,anforderung_id,pos,artikel',
                'attachments',
                'geklaertVon.person:id,vorname,nachname',
            ])
            ->oldest()
            ->get();

        $notification = $request->user()->notifications()
            ->where('data->id', $id)
            ->where('data->typ', 'Materialanforderung')
            ->first();
        $notification?->markAsRead();

        return Inertia::render('Bestellungen/Materialanforderung/Show', [
            'anforderung' => $anforderung,
            'standorte' => Standort::orderBy('name')->get(['id', 'name']),
            'purchaseRules' => PurchaseRule::current(),
            'approval' => $this->purchasing->summary($anforderung),
            'canConfirmDirector' => $this->purchasing->isDirector($request->user()),
            'kostenstellen' => $this->kostenstellen($anforderung->projekt),
            'canConfirmSachlich' => $request->user()->can('materialanforderung.sachlische_freigabe.update')
                && $this->isAssignedToProject($request->user(), $anforderung->projekt_id),
            'canConfirmKaufmaenisch' => $request->user()->can('materialanforderung.kaufmännische_freigabe.update'),
            'canEditMaterialanforderung' => $request->user()->can('materialanforderung.update')
                && (int) $anforderung->ersteller_id === (int) $request->user()->id,
            'canBestellen' => $request->user()->can('materialanforderung.bestellwesen.update'),
            'canDeleteMaterialanforderung' => $request->user()->can('materialanforderung.destroy')
                && (int) $anforderung->ersteller_id === (int) $request->user()->id
                && in_array($anforderung->status, ['entwurf', 'zur_ueberarbeitung'], true),
            'canDeleteFinalizedMaterialanforderung' => $request->user()->can('materialanforderung.bestellte.destroy')
                && in_array($anforderung->status, ['bestellt', 'teilweise_geliefert', 'geliefert'], true),
            'verlauf' => $verlauf,
            'kommentare' => $kommentare,
            'kommentarGruende' => collect(MaterialanforderungKommentarController::REASONS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'currentUserId' => $request->user()->id,
            'canUseChat' => $request->user()->hasStoredPermission('chat.use')
                && $request->user()->person?->typ === 'mitarbeiter',
        ]);
    }

    public function genehmigen(Request $request, $id, $status)
    {
        abort_unless(in_array($status, [
            'eingereicht', 'sachlich_genehmigt', 'kaufmaennisch_genehmigt', 'gf_pruefung', 'gf_genehmigt', 'abgelehnt',
            'zur_ueberarbeitung', 'zurueckgezogen', 'storniert', 'bestellt', 'teilweise_geliefert', 'geliefert',
        ], true), 422, 'Ungültiger Status.');

        $anforderung = DB::transaction(function () use ($request, $id, &$status) {
            $anforderung = Materialanforderung::with(['artikeln', 'vergabevermerk'])->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->authorizeTransition($request->user(), $anforderung, $status);

            if ($status === 'eingereicht') {
                if (!$anforderung->standort_id) throw ValidationException::withMessages(['standort_id' => 'Bitte wählen Sie den zuständigen Standort aus.']);
                $policy = $this->purchasing->evaluate($anforderung);
                $this->purchasing->requireOffers($anforderung, $policy);
                $anforderung->update(['approval_policy' => $policy, 'selected_offer_id' => null]);
            }
            if ($status === 'sachlich_genehmigt' && ($anforderung->approval_policy['gf_required'] ?? false)) {
                MaterialanforderungGenehmigung::create(['anforderung_id' => $anforderung->id,
                    'genehmiger_id' => $request->user()->id, 'status' => 'sachlich_genehmigt', 'kommentar' => $request->input('anmerkung')]);
                $status = 'gf_pruefung';
            } elseif ($status === 'gf_pruefung') {
                $request->validate(['anmerkung' => ['required', 'string', 'max:2000']]);
                $policy = $anforderung->approval_policy ?: $this->purchasing->evaluate($anforderung);
                $policy['gf_required'] = true;
                $policy['reasons'][] = 'Manuelle Weiterleitung durch die kaufmännische Leitung';
                $anforderung->update(['approval_policy' => $policy]);
            }
            if ($status === 'gf_genehmigt') {
                $this->purchasing->requireOffers($anforderung, $anforderung->approval_policy ?? []);
                if ($anforderung->angebote()->where('revision', $anforderung->revision)->exists()) {
                    $data = $request->validate(['angebot_id' => ['required', 'integer'], 'anmerkung' => ['required', 'string', 'max:2000']]);
                    $this->purchasing->selectOffer($anforderung, (int) $data['angebot_id']);
                }
            }
            if ($status === 'bestellt' && $anforderung->selected_offer_id) {
                $this->purchasing->requireOffers($anforderung, $anforderung->approval_policy ?? [], $anforderung->angebote()->findOrFail($anforderung->selected_offer_id));
            }
            if ($status === 'bestellt' && $anforderung->kommentare()
                ->where('antwort_erforderlich', true)
                ->whereNull('geklaert_am')
                ->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Die Bestellung ist gesperrt, solange noch eine Rückfrage offen ist.',
                ]);
            }

            if (in_array($status, ['zur_ueberarbeitung', 'zurueckgezogen', 'storniert', 'abgelehnt'], true)) {
                $request->validate(['anmerkung' => ['required', 'string', 'max:2000']]);
            }

            if ($status === 'bestellt') {
                if (isset($anforderung->approval_policy['rule_id']) &&
                    (!$anforderung->vergabevermerk?->lieferant || !$anforderung->lieferant_adresse ||
                    ($anforderung->vergabevermerk?->lieferung_art !== 'Dienstleistung' && $anforderung->vergabevermerk?->lieferung_option === 'per Lieferung' && !$anforderung->vergabevermerk?->lieferadresse))) {
                    throw ValidationException::withMessages(['status' => 'Für die Bestellung fehlen der Anbieter, seine Anschrift oder bei Lieferung die Lieferanschrift. Bitte zur Überarbeitung zurückgeben.']);
                }
                $this->purchasing->number($anforderung);
                $anforderung->refresh();
                $anforderung->update(['bestellt_am' => now()]);
                $anforderung->update(['order_snapshot' => app(PurchaseOrderExport::class)->snapshot($anforderung, $request->user())]);
            }

            if ($status === 'teilweise_geliefert') {
                $data = $request->validate([
                    'liefermengen' => ['required', 'array'],
                    'liefermengen.*' => ['required', 'integer', 'min:0'],
                ]);
                $totalDelivered = 0;
                $totalOrdered = 0;
                foreach ($anforderung->artikeln as $artikel) {
                    $menge = (int) ($data['liefermengen'][$artikel->id] ?? 0);
                    if ($menge > (int) $artikel->stueck) {
                        throw ValidationException::withMessages([
                            "liefermengen.{$artikel->id}" => "Beim Artikel „{$artikel->artikel}“ darf die Liefermenge von {$menge} die bestellte Menge von {$artikel->stueck} nicht überschreiten.",
                        ]);
                    }
                    $artikel->update(['gelieferte_menge' => $menge]);
                    $totalDelivered += $menge;
                    $totalOrdered += (int) $artikel->stueck;
                }
                if ($totalDelivered === 0) {
                    throw ValidationException::withMessages([
                        'liefermengen' => 'Für eine Teillieferung muss mindestens ein Artikel als geliefert eingetragen sein.',
                    ]);
                }
                if ($totalDelivered >= $totalOrdered) {
                    throw ValidationException::withMessages([
                        'liefermengen' => 'Alle Artikel sind vollständig geliefert. Bitte verwenden Sie stattdessen „Vollständig geliefert“.',
                    ]);
                }
            }

            if ($status === 'geliefert') {
                foreach ($anforderung->artikeln as $artikel) {
                    $artikel->update(['gelieferte_menge' => $artikel->stueck]);
                }
            }

            MaterialanforderungGenehmigung::create([
                'anforderung_id' => $anforderung->id,
                'genehmiger_id' => $request->user()->id,
                'status' => $status,
                'kommentar' => $request->input('anmerkung'),
            ]);
            // A withdrawal is an audit event; the request itself returns to an editable draft.
            $anforderung->update([
                'status' => $status === 'zurueckgezogen' ? 'entwurf' : $status,
            ]);

            return $anforderung;
        });

        $recipients = $this->notificationRecipients
            ->forMaterialanforderung($anforderung, $status, $request->user());

        if ($status === 'zurueckgezogen') {
            // Remove the now obsolete approval request before sending the withdrawal notice.
            $recipients->each(function ($recipient) use ($anforderung) {
                $recipient->unreadNotifications()
                    ->where('data->id', $anforderung->id)
                    ->where('data->typ', 'Materialanforderung')
                    ->delete();
            });
        }

        Notification::send(
            $recipients,
            new UpdateMaterialanforderungNotification($anforderung, $status, $request->user())
        );

        $message = $status === 'zurueckgezogen'
            ? 'Die Einreichung wurde zurückgezogen. Die Materialanforderung ist wieder als Entwurf bearbeitbar.'
            : 'Status wurde aktualisiert.';

        return back()->with('success', $message);
    }

    public function exportPdf(Request $request, Materialanforderung $materialanforderung)
    {
        abort_unless($this->canOpenMaterialRequest($request->user()), 403);
        abort_unless($this->mayView($request->user(), $materialanforderung), 403);

        $materialanforderung->load([
            'projekt', 'besteller.person', 'artikeln', 'vergabevermerk',
            'genehmigungen' => fn ($query) => $query->with('genehmiger.person')->oldest(),
        ]);

        return Pdf::loadView('pdf.materialanforderung', ['anforderung' => $materialanforderung])
            ->setPaper('a4')
            ->download('Materialanforderung-' . $materialanforderung->id . '.pdf');
    }

    public function exportOrder(Request $request, Materialanforderung $materialanforderung, string $format, PurchaseOrderExport $export)
    {
        abort_unless($this->mayView($request->user(), $materialanforderung), 403);
        abort_unless(in_array($format, ['pdf', 'docx'], true), 404);
        abort_unless(in_array($materialanforderung->status, ['bestellt', 'teilweise_geliefert', 'geliefert'], true), 409, 'Der Bestellschein ist nach der Bestellung verfügbar.');
        $path = $export->export($materialanforderung, $format);
        return response()->download($path, 'Bestellschein-'.str_replace('/', '-', $materialanforderung->fresh()->bestellnummer).'.'.$format);
    }

    private function requestRules(Projekt $projekt, bool $forUpdate = false): array
    {
        $kostenstellenIds = $projekt->kostenstellen()->pluck('kostenstelles.id');

        return [
            'standort_id' => ['nullable', 'integer', 'exists:standorts,id'],
            'revision' => ['nullable', 'integer'],
            'preisart' => ['sometimes', 'required', Rule::in(['brutto', 'netto'])],
            'versand_netto' => ['nullable', 'numeric', 'between:0,99999999'],
            'versand_mwst' => ['nullable', 'numeric', 'between:0,100'],
            'lieferant_adresse' => ['nullable', 'string', 'max:1000'],
            'lieferantenreferenz' => ['nullable', 'string', 'max:100'],
            'kostenstelle' => [
                'required', 'string',
                Rule::exists('kostenstelles', 'kostenstelle')->where(fn ($query) => $query->whereIn('id', $kostenstellenIds)),
            ],
            'benoetigt_am' => ['nullable', 'date'],
            'prioritaet' => ['required', Rule::in(['normal', 'dringend'])],
            'bemerkungen' => ['nullable', 'string', 'max:4000'],
            'positionen' => ['required', 'array', 'min:1'],
            'positionen.*.id' => $forUpdate ? ['nullable', 'integer'] : ['prohibited'],
            'positionen.*.pos' => ['required', 'integer', 'min:1'],
            'positionen.*.artikel' => ['required', 'string', 'max:255'],
            'positionen.*.link' => ['nullable', 'url', 'max:2000'],
            'positionen.*.stueck' => ['required', 'integer', 'min:1', 'max:999999'],
            'positionen.*.art_nr' => ['nullable', 'string', 'max:100'],
            'positionen.*.einzelpreis' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'positionen.*.mwst' => ['required', 'numeric', 'between:0,100'],
            'vergabe' => ['required', 'array'],
            'vergabe.kurzbeschreibung' => ['nullable', 'string', 'max:2000'],
            'vergabe.lieferung_art' => ['required', Rule::in(['Lieferleistung', 'Dienstleistung'])],
            'vergabe.begruendung_optionen' => ['nullable', 'array'],
            'vergabe.begruendung_optionen.*' => ['string', Rule::in([
                'nur_ein_anbieter', 'besondere_gruende', 'besondere_dringlichkeit',
                'zubehoer_ersatzteile', 'vertragliche_gruende', 'guenstigster_anbieter',
            ])],
            'vergabe.begruendung' => ['nullable', 'string', 'max:4000'],
            'vergabe.lieferant' => ['nullable', 'string', 'max:255'],
            'vergabe.lieferung_option' => ['exclude_if:vergabe.lieferung_art,Dienstleistung', 'required_if:vergabe.lieferung_art,Lieferleistung', Rule::in(['per Abholung', 'per Lieferung'])],
            'vergabe.lieferadresse' => ['exclude_unless:vergabe.lieferung_art,Lieferleistung', 'exclude_unless:vergabe.lieferung_option,per Lieferung', 'required', 'string', 'max:1000'],
            'vergabe.leistungsort' => ['exclude_unless:vergabe.lieferung_art,Dienstleistung', 'nullable', 'string', 'max:1000'],
            'vergabe.bestellnummer' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function calculateTotals(array $positionen, $shipping = 0, $shippingTax = 19, $shippingGross = null): array
    {
        return $this->purchasing->totals($positionen, $shipping, $shippingTax, $shippingGross);
    }

    private function positionValues(array $position): array
    {
        return [
            'pos' => $position['pos'],
            'artikel' => $position['artikel'],
            'link' => $position['link'] ?? null,
            'stueck' => $position['stueck'],
            'art_nr' => $position['art_nr'] ?? null,
            'einzelpreis' => $position['einzelpreis'],
            'einzelpreis_brutto' => $position['einzelpreis_brutto'],
            'mwst' => $position['mwst'],
            'gesamtpreis' => $position['gesamtpreis'],
        ];
    }

    private function vergabeValues(array $vergabe): array
    {
        return [
            'kurzbeschreibung' => $vergabe['kurzbeschreibung'] ?? null,
            'lieferung_art' => $vergabe['lieferung_art'] ?? 'Lieferleistung',
            'begruendung' => $vergabe['begruendung'] ?? null,
            'begruendung_optionen' => $vergabe['begruendung_optionen'] ?? [],
            'lieferant' => $vergabe['lieferant'] ?? null,
            'lieferung_option' => ($vergabe['lieferung_art'] ?? '') === 'Dienstleistung' ? null : ($vergabe['lieferung_option'] ?? 'per Lieferung'),
            'lieferadresse' => ($vergabe['lieferung_art'] ?? '') === 'Dienstleistung' ? null : ($vergabe['lieferadresse'] ?? null),
            'leistungsort' => ($vergabe['lieferung_art'] ?? '') === 'Dienstleistung' ? ($vergabe['leistungsort'] ?? null) : null,
            // Internal numbers are assigned by PurchaseWorkflow, never by form input.
        ];
    }

    private function kostenstellen(Projekt $projekt)
    {
        return $projekt->kostenstellen()
            ->orderByPivot('gueltig_von', 'desc')
            ->orderByPivot('id', 'desc')
            ->limit(3)
            ->get(['kostenstelles.id', 'kostenstelles.kostenstelle']);
    }

    private function isAssignedToProject($user, int $projektId): bool
    {
        return $user->projekte()->whereKey($projektId)->exists();
    }

    public function mayView($user, Materialanforderung $anforderung): bool
    {
        if (!$this->canOpenMaterialRequest($user) && !$user->can('materialanforderung.update')) return false;
        if ($this->purchasing->isDirector($user) && in_array($anforderung->status, ['gf_pruefung', 'gf_genehmigt'], true)) return true;
        if ((int) $anforderung->ersteller_id === (int) $user->id) {
            return true;
        }

        if ($anforderung->genehmigungen()->where('genehmiger_id', $user->id)->exists()) {
            return true;
        }

        if ($user->can('materialanforderung.bestellte.destroy')
            && in_array($anforderung->status, ['bestellt', 'teilweise_geliefert'], true)) {
            return true;
        }

        if ($user->can('materialanforderung.sachlische_freigabe.index')
            && $anforderung->status === 'eingereicht'
            && $this->isAssignedToProject($user, $anforderung->projekt_id)) {
            return true;
        }

        return ($user->can('materialanforderung.kaufmännische_freigabe.index')
                || $user->can('materialanforderung.kaufmännische_freigabe.update')
                || $user->can('materialanforderung.bestellwesen.update'))
            && ! in_array($anforderung->status, ['entwurf', 'eingereicht'], true);
    }

    private function canOpenMaterialRequest($user): bool
    {
        if ($this->purchasing->isDirector($user)) return true;
        return collect([
            'materialanforderung.show',
            'materialanforderung.sachlische_freigabe.show',
            'materialanforderung.sachlische_freigabe.index',
            'materialanforderung.sachlische_freigabe.update',
            'materialanforderung.kaufmännische_freigabe.show',
            'materialanforderung.kaufmännische_freigabe.index',
            'materialanforderung.kaufmännische_freigabe.update',
            'materialanforderung.bestellwesen.update',
            'materialanforderung.bestellte.destroy',
        ])->contains(fn (string $permission) => $user->can($permission));
    }

    private function authorizeTransition($user, Materialanforderung $anforderung, string $targetStatus): void
    {
        $allowed = match ($targetStatus) {
            'eingereicht' => (int) $anforderung->ersteller_id === (int) $user->id
                && $user->can('materialanforderung.update')
                && in_array($anforderung->status, ['entwurf', 'zur_ueberarbeitung'], true),
            'sachlich_genehmigt' => $user->can('materialanforderung.sachlische_freigabe.update')
                && $this->isAssignedToProject($user, $anforderung->projekt_id)
                && $anforderung->status === 'eingereicht',
            'kaufmaennisch_genehmigt' => $user->can('materialanforderung.kaufmännische_freigabe.update')
                && $anforderung->status === 'sachlich_genehmigt'
                && !($anforderung->approval_policy['gf_required'] ?? false),
            'gf_pruefung' => $user->can('materialanforderung.kaufmännische_freigabe.update')
                && PurchaseRule::current()->manual_referral
                && in_array($anforderung->status, ['sachlich_genehmigt', 'kaufmaennisch_genehmigt'], true),
            'gf_genehmigt', 'abgelehnt' => $this->purchasing->isDirector($user) && $anforderung->status === 'gf_pruefung',
            'bestellt' => $user->can('materialanforderung.bestellwesen.update')
                && in_array($anforderung->status, ['kaufmaennisch_genehmigt', 'gf_genehmigt'], true)
                && (!($anforderung->approval_policy['gf_required'] ?? false) || $anforderung->status === 'gf_genehmigt'),
            'teilweise_geliefert' => $user->can('materialanforderung.bestellwesen.update')
                && in_array($anforderung->status, ['bestellt', 'teilweise_geliefert'], true),
            'geliefert' => $user->can('materialanforderung.bestellwesen.update')
                && in_array($anforderung->status, ['bestellt', 'teilweise_geliefert'], true),
            'zur_ueberarbeitung' => ($user->can('materialanforderung.sachlische_freigabe.update')
                    && $this->isAssignedToProject($user, $anforderung->projekt_id)
                    && $anforderung->status === 'eingereicht')
                || ($user->can('materialanforderung.kaufmännische_freigabe.update') && $anforderung->status === 'sachlich_genehmigt')
                || ($user->can('materialanforderung.bestellwesen.update') && in_array($anforderung->status, ['kaufmaennisch_genehmigt', 'gf_genehmigt'], true))
                || ($this->purchasing->isDirector($user) && $anforderung->status === 'gf_pruefung'),
            'zurueckgezogen' => (int) $anforderung->ersteller_id === (int) $user->id
                && $user->can('materialanforderung.update')
                && $anforderung->status === 'eingereicht',
            'storniert' => (int) $anforderung->ersteller_id === (int) $user->id
                && $user->can('materialanforderung.update')
                && in_array($anforderung->status, ['entwurf', 'eingereicht', 'zur_ueberarbeitung'], true),
            default => false,
        };

        abort_unless($allowed, 403);
    }

    private function deleteMaterialanforderungNotifications(int $anforderungId): void
    {
        DB::table('notifications')
            ->where('data->id', $anforderungId)
            ->where('data->typ', 'Materialanforderung')
            ->delete();
    }

    private function deletionSnapshot(Materialanforderung $anforderung): array
    {
        return [
            'id' => $anforderung->id,
            'projekt' => [
                'id' => $anforderung->projekt_id,
                'name' => $anforderung->projekt?->name,
            ],
            'antragsteller' => [
                'user_id' => $anforderung->ersteller_id,
                'name' => $anforderung->besteller?->name,
            ],
            'status' => $anforderung->status,
            'bestellnummer' => $anforderung->bestellnummer, 'approval_policy' => $anforderung->approval_policy,
            'order_snapshot' => $anforderung->order_snapshot, 'angebote' => $anforderung->angebote->toArray(),
            'kostenstelle' => $anforderung->kostenstelle,
            'benoetigt_am' => $anforderung->benoetigt_am?->toDateString(),
            'prioritaet' => $anforderung->prioritaet,
            'bemerkungen' => $anforderung->bemerkungen,
            'preisart' => $anforderung->preisart, 'versand_brutto' => $anforderung->versand_brutto,
            'gesamtpreis' => $anforderung->gesamtpreis,
            'endsumme' => $anforderung->endsumme,
            'erstellt_am' => $anforderung->created_at?->toDateTimeString(),
            'artikel' => $anforderung->artikeln->map(fn ($artikel) => [
                'pos' => $artikel->pos,
                'artikel' => $artikel->artikel,
                'stueck' => $artikel->stueck,
                'gelieferte_menge' => $artikel->gelieferte_menge,
                'art_nr' => $artikel->art_nr,
                'einzelpreis' => $artikel->einzelpreis,
                'einzelpreis_brutto' => $artikel->einzelpreis_brutto,
                'mwst' => $artikel->mwst,
                'gesamtpreis' => $artikel->gesamtpreis,
            ])->values()->all(),
            'vergabevermerk' => $anforderung->vergabevermerk ? [
                'kurzbeschreibung' => $anforderung->vergabevermerk->kurzbeschreibung,
                'lieferung_art' => $anforderung->vergabevermerk->lieferung_art,
                'begruendung' => $anforderung->vergabevermerk->begruendung,
                'begruendung_optionen' => $anforderung->vergabevermerk->begruendung_optionen,
                'lieferant' => $anforderung->vergabevermerk->lieferant,
                'lieferung_option' => $anforderung->vergabevermerk->lieferung_option,
                'lieferadresse' => $anforderung->vergabevermerk->lieferadresse,
                'leistungsort' => $anforderung->vergabevermerk->leistungsort,
                'bestellnummer' => $anforderung->vergabevermerk->bestellnummer,
            ] : null,
            'genehmigungen' => $anforderung->genehmigungen->map(fn ($genehmigung) => [
                'status' => $genehmigung->status,
                'genehmiger_id' => $genehmigung->genehmiger_id,
                'genehmiger' => $genehmigung->genehmiger?->name,
                'kommentar' => $genehmigung->kommentar,
                'erstellt_am' => $genehmigung->created_at?->toDateTimeString(),
            ])->values()->all(),
        ];
    }
}
