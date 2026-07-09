<?php

namespace App\Http\Controllers;

use App\Models\Geraet;
use App\Models\ItTicket;
use App\Models\Personen;
use App\Models\Standort;
use App\Models\User;
use App\Notifications\ConfiguredEventNotification;
use App\Services\NotificationRecipientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ItServiceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAny($request, [
            'it.service.index',
            'it.ticket.store',
            'it.ticket.update',
            'it.geraet.update',
            'geraet.index',
            'geraet.update',
        ]);

        $canManageTickets = $this->userCanAny($request, [
            'it.service.index',
            'it.ticket.update',
            'it.ticket.destroy',
        ]);

        $tickets = ItTicket::query()
            ->with($this->ticketRelations())
            ->when(! $canManageTickets, fn ($query) => $query->where('gemeldet_von_user_id', $request->user()?->id))
            ->latest()
            ->get();

        $geraete = Geraet::query()
            ->with(['standort:id,name', 'verantwortlichePerson:id,vorname,nachname'])
            ->withCount([
                'itTickets as offene_it_tickets_count' => fn ($query) => $query->offen(),
            ])
            ->latest()
            ->get();

        $personal = Personen::mitarbeiter()
            ->aktiv()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get(['id', 'vorname', 'nachname']);

        return Inertia::render('ITService/Index', [
            'tickets' => $tickets,
            'geraete' => $geraete,
            'standorte' => Standort::orderBy('name')->get(['id', 'name']),
            'personal' => $personal,
            'ticketOptions' => [
                'statuses' => $this->statusOptions(),
                'priorities' => $this->priorityOptions(),
                'categories' => $this->categoryOptions(),
            ],
            'deviceOptions' => [
                'statuses' => $this->deviceStatusOptions(),
                'categories' => $this->deviceCategoryOptions(),
                'zustand' => $this->zustandOptions(),
            ],
            'itPermissions' => [
                'canCreateTicket' => $request->user()->can('it.ticket.store'),
                'canUpdateTicket' => $request->user()->can('it.ticket.update'),
                'canDeleteTicket' => $request->user()->can('it.ticket.destroy'),
                'canCreateDevice' => $request->user()->can('it.geraet.store') || $request->user()->can('geraet.store'),
                'canUpdateDevice' => $request->user()->can('it.geraet.update') || $request->user()->can('geraet.update'),
                'canDeleteDevice' => $request->user()->can('it.geraet.destroy') || $request->user()->can('geraet.destroy') || $request->user()->can('geraet.delete'),
            ],
        ]);
    }

    public function storeTicket(Request $request)
    {
        $this->authorizeAny($request, ['it.ticket.store', 'it.ticket.update']);

        $validated = $request->validate($this->ticketRules());

        $ticket = ItTicket::create([
            ...$validated,
            'status' => 'neu',
            'gemeldet_von_user_id' => $request->user()?->id,
            'gemeldet_von_personen_id' => $request->user()?->person_id,
        ]);

        $ticket->forceFill([
            'ticket_nr' => $this->ticketNumber($ticket),
        ])->save();

        Notification::send(
            app(NotificationRecipientService::class)->forEvent('it.ticket.created', [
                'actor' => $request->user(),
                'creator_user' => $request->user(),
            ], fn () => User::permission('it.ticket.update')
                ->get()
                ->reject(fn (User $user) => (int) $user->id === (int) $request->user()?->id)
                ->values()),
            new ConfiguredEventNotification([
                'event_key' => 'it.ticket.created',
                'message' => 'Neues IT-Ticket "' . $ticket->titel . '" wurde erstellt.',
                'link' => route('it-service.index'),
                'id' => $ticket->id,
                'typ' => 'IT-Ticket',
            ])
        );

        return response()->json([
            'message' => 'IT-Ticket wurde erstellt.',
            'ticket' => $this->loadTicket($ticket),
        ], 201);
    }

    public function updateTicket(Request $request, ItTicket $ticket)
    {
        $this->authorizeAny($request, ['it.ticket.update']);

        $validated = $request->validate($this->ticketRules($ticket));
        $validated = $this->applyTicketStatusDates($validated, $ticket, $request);

        $ticket->update($validated);

        return response()->json([
            'message' => 'IT-Ticket wurde aktualisiert.',
            'ticket' => $this->loadTicket($ticket),
        ]);
    }

    public function destroyTicket(Request $request, ItTicket $ticket)
    {
        $this->authorizeAny($request, ['it.ticket.destroy']);

        $ticket->delete();

        return response()->json([
            'message' => 'IT-Ticket wurde geloescht.',
        ]);
    }

    public function storeGeraet(Request $request)
    {
        $this->authorizeAny($request, ['it.geraet.store', 'geraet.store']);

        $validated = $this->normalizeGeraetData($request->validate($this->geraetRules()));
        $geraet = Geraet::create($validated);

        return response()->json([
            'message' => 'Gerät wurde angelegt.',
            'geraet' => $this->loadGeraet($geraet),
        ], 201);
    }

    public function updateGeraet(Request $request, Geraet $geraet)
    {
        $this->authorizeAny($request, ['it.geraet.update', 'geraet.update']);

        $validated = $this->normalizeGeraetData($request->validate($this->geraetRules($geraet->id)));
        $geraet->update($validated);

        return response()->json([
            'message' => 'Gerät wurde aktualisiert.',
            'geraet' => $this->loadGeraet($geraet),
        ]);
    }

    public function destroyGeraet(Request $request, Geraet $geraet)
    {
        $this->authorizeAny($request, ['it.geraet.destroy', 'geraet.destroy', 'geraet.delete']);

        if ($this->geraetHasReferences($geraet)) {
            $geraet->update([
                'status' => 'ausgesondert',
                'verfuegbarkeit' => false,
            ]);

            return response()->json([
                'message' => 'Gerät ist verknüpft und wurde ausgesondert.',
                'geraet' => $this->loadGeraet($geraet),
                'deactivated' => true,
            ]);
        }

        $geraet->delete();

        return response()->json([
            'message' => 'Gerät wurde geloescht.',
            'deleted' => true,
        ]);
    }

    private function ticketRules(?ItTicket $ticket = null): array
    {
        return [
            'standort_id' => ['nullable', 'integer', 'exists:standorts,id'],
            'geraet_id' => ['nullable', 'integer', 'exists:geraets,id'],
            'betroffene_personen_id' => ['nullable', 'integer', 'exists:personens,id'],
            'zugewiesen_an_personen_id' => ['nullable', 'integer', 'exists:personens,id'],
            'titel' => ['required', 'string', 'max:150'],
            'kategorie' => ['required', Rule::in(ItTicket::CATEGORIES)],
            'prioritaet' => ['required', Rule::in(ItTicket::PRIORITIES)],
            'status' => [$ticket ? 'required' : 'sometimes', Rule::in(ItTicket::STATUSES)],
            'raum' => ['nullable', 'string', 'max:120'],
            'kontakt' => ['nullable', 'string', 'max:160'],
            'beschreibung' => ['nullable', 'string', 'max:3000'],
            'planung' => ['nullable', 'string', 'max:3000'],
            'loesung' => ['nullable', 'string', 'max:3000'],
            'interne_notiz' => ['nullable', 'string', 'max:3000'],
            'faellig_am' => ['nullable', 'date'],
            'geplant_am' => ['nullable', 'date'],
        ];
    }

    private function geraetRules(?int $geraetId = null): array
    {
        return [
            'sn' => ['required', 'string', 'max:20', Rule::unique('geraets', 'sn')->ignore($geraetId)],
            'productID' => ['required', 'string', 'max:30'],
            'inventarnummer' => ['nullable', 'string', 'max:80', Rule::unique('geraets', 'inventarnummer')->ignore($geraetId)],
            'geraet' => ['required', 'string', 'max:20'],
            'kategorie' => ['nullable', 'string', 'max:80'],
            'zustand' => ['required', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(array_keys($this->deviceStatusLabels()))],
            'verfuegbarkeit' => ['sometimes', 'boolean'],
            'imLager' => ['nullable', 'string', 'max:200'],
            'standort_id' => ['nullable', 'integer', 'exists:standorts,id'],
            'verantwortliche_personen_id' => ['nullable', 'integer', 'exists:personens,id'],
            'raum' => ['nullable', 'string', 'max:120'],
            'hersteller' => ['required', 'string', 'max:20'],
            'modell' => ['nullable', 'string', 'max:20'],
            'ip_adresse' => ['nullable', 'string', 'max:80'],
            'mac_adresse' => ['nullable', 'string', 'max:80'],
            'betriebssystem' => ['nullable', 'string', 'max:120'],
            'baujahr' => ['nullable', 'date'],
            'garantiefrist' => ['nullable', 'date', 'after_or_equal:baujahr'],
            'letzte_wartung_am' => ['nullable', 'date'],
            'naechste_wartung_am' => ['nullable', 'date'],
            'notiz' => ['nullable', 'string', 'max:3000'],
        ];
    }

    private function normalizeGeraetData(array $data): array
    {
        $data['status'] = $data['status'] ?? 'aktiv';
        $data['verfuegbarkeit'] = (bool) ($data['verfuegbarkeit'] ?? true);

        return $data;
    }

    private function applyTicketStatusDates(array $data, ItTicket $ticket, Request $request): array
    {
        $status = $data['status'] ?? $ticket->status;

        if ($status === 'in_bearbeitung' && ! $ticket->begonnen_at) {
            $data['begonnen_at'] = now();
        }

        if (in_array($status, ['geloest', 'geschlossen'], true)) {
            $data['geloest_at'] = $ticket->geloest_at ?? now();
            $data['geloest_von_personen_id'] = $ticket->geloest_von_personen_id ?: $request->user()?->person_id;
        } else {
            $data['geloest_at'] = null;
            $data['geloest_von_personen_id'] = null;
        }

        $data['geschlossen_at'] = $status === 'geschlossen'
            ? ($ticket->geschlossen_at ?? now())
            : null;

        return $data;
    }

    private function loadTicket(ItTicket $ticket): ItTicket
    {
        return $ticket->fresh($this->ticketRelations());
    }

    private function ticketRelations(): array
    {
        return [
            'standort:id,name',
            'geraet:id,sn,productID,inventarnummer,geraet,kategorie,hersteller,modell,standort_id,raum',
            'gemeldetVonPerson:id,vorname,nachname',
            'betroffenePerson:id,vorname,nachname',
            'zugewiesenAnPerson:id,vorname,nachname',
            'geloestVonPerson:id,vorname,nachname',
        ];
    }

    private function loadGeraet(Geraet $geraet): Geraet
    {
        return $geraet->fresh([
            'standort:id,name',
            'verantwortlichePerson:id,vorname,nachname',
        ])->loadCount([
            'itTickets as offene_it_tickets_count' => fn ($query) => $query->offen(),
        ]);
    }

    private function ticketNumber(ItTicket $ticket): string
    {
        return 'IT-' . now()->format('Y') . '-' . str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT);
    }

    private function geraetHasReferences(Geraet $geraet): bool
    {
        if ($geraet->itTickets()->exists()) {
            return true;
        }

        foreach (['geraet_has_ausgabes', 'geraet_has_rueckgabes'] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)
                && DB::table($table)->where('geraet_id', $geraet->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function authorizeAny(Request $request, array $permissions): void
    {
        if ($this->userCanAny($request, $permissions)) {
            return;
        }

        abort(403);
    }

    private function userCanAny(Request $request, array $permissions): bool
    {
        $user = $request->user();

        foreach ($permissions as $permission) {
            if ($user?->can($permission)) {
                return true;
            }
        }

        return false;
    }

    private function statusOptions(): array
    {
        return collect($this->statusLabels())
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    private function priorityOptions(): array
    {
        return collect($this->priorityLabels())
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    private function categoryOptions(): array
    {
        return collect($this->categoryLabels())
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    private function deviceStatusOptions(): array
    {
        return collect($this->deviceStatusLabels())
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    private function deviceCategoryOptions(): array
    {
        return collect([
            'computer' => 'Computer',
            'notebook' => 'Notebook',
            'monitor' => 'Monitor',
            'drucker' => 'Drucker',
            'netzwerk' => 'Netzwerk',
            'telefon' => 'Telefon',
            'mobilgeraet' => 'Mobilgerät',
            'zubehoer' => 'Zubehör',
            'sonstiges' => 'Sonstiges',
        ])->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    private function zustandOptions(): array
    {
        return collect([
            'Brandneu',
            'Neuwertig',
            'Leichte Gebrauchsspuren',
            'Starke Gebrauchsspuren',
            'Reparaturbedürftig',
            'Defekt',
        ])->map(fn (string $value) => ['value' => $value, 'label' => $value])
            ->values()
            ->all();
    }

    private function statusLabels(): array
    {
        return [
            'neu' => 'Neu',
            'gesichtet' => 'Gesichtet',
            'geplant' => 'Geplant',
            'in_bearbeitung' => 'In Bearbeitung',
            'wartet_auf_rueckmeldung' => 'Warten auf Rückmeldung',
            'wartet_auf_extern' => 'Warten auf extern',
            'geloest' => 'Gelöst',
            'geschlossen' => 'Geschlossen',
        ];
    }

    private function priorityLabels(): array
    {
        return [
            'niedrig' => 'Niedrig',
            'normal' => 'Normal',
            'hoch' => 'Hoch',
            'kritisch' => 'Kritisch',
        ];
    }

    private function categoryLabels(): array
    {
        return [
            'hardware' => 'Hardware',
            'software' => 'Software',
            'netzwerk' => 'Netzwerk',
            'drucker' => 'Drucker',
            'telefon' => 'Telefon',
            'zugang' => 'Zugang',
            'sicherheit' => 'Sicherheit',
            'sonstiges' => 'Sonstiges',
        ];
    }

    private function deviceStatusLabels(): array
    {
        return [
            'aktiv' => 'Aktiv',
            'reserve' => 'Reserve',
            'wartung' => 'Wartung',
            'defekt' => 'Defekt',
            'ausgesondert' => 'Ausgesondert',
        ];
    }
}
