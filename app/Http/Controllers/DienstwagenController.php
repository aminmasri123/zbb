<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Personen;
use App\Models\Standort;
use App\Models\Dienstwagen;
use App\Notifications\ConfiguredEventNotification;
use App\Services\DienstwagenVerlaufService;
use App\Services\NotificationRecipientService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class DienstwagenController extends Controller
{
    public function index()
    {
        return Inertia::render('Dienstwagen/Index', [
            'vehicles'  => Dienstwagen::with('standort')
                ->withCount(['offeneMeldungen', 'aktiveBuchungen'])
                ->orderBy('created_at', 'desc')
                ->get(),
            'standorte' => Standort::orderBy('name')->get()
        ]);
    }

    public function create()
    {
        return Inertia::render('Dienstwagen/Create', [
            'drivers'   => Personen::orderBy('nachname')->get(),
            'locations' => Standort::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            ...$this->vehicleRules(),
            'kennzeichen' => 'required|string|unique:dienstwagens,kennzeichen',
            'bild' => 'nullable|image|max:4096',
            'allowed_drivers' => 'nullable|array',
            'allowed_drivers.*' => 'integer|exists:personens,id',
        ]);

        $drivers = $data['allowed_drivers'] ?? [];
        unset($data['allowed_drivers'], $data['bild']);

        $data = $this->normalizeVehicleDates($data);

        if ($request->hasFile('bild')) {
            $data['bild_path'] = $request->file('bild')->store('dienstwagen/bilder', 'public');
        }

        $vehicle = Dienstwagen::create($data);

        if (!empty($drivers)) {
            $vehicle->fahrer()->sync($drivers);
        }

        app(DienstwagenVerlaufService::class)->record(
            $vehicle,
            'fahrzeug.created',
            'Dienstwagen angelegt',
            'Fahrzeug ' . $vehicle->kennzeichen . ' wurde angelegt.',
            ['fahrer_ids' => ['old' => [], 'new' => array_values($drivers)]],
            $vehicle
        );

        Notification::send(
            app(NotificationRecipientService::class)->forEvent('dienstwagen.created', [
                'actor' => $request->user(),
                'creator_user' => $request->user(),
            ]),
            new ConfiguredEventNotification([
                'event_key' => 'dienstwagen.created',
                'message' => 'Neuer Dienstwagen "' . $vehicle->kennzeichen . '" wurde erstellt.',
                'link' => route('dienstwagen.index'),
                'id' => $vehicle->id,
                'typ' => 'Dienstwagen',
            ])
        );

        return redirect()->route('dienstwagen.index')
            ->with('success', 'Fahrzeug erfolgreich hinzugefügt.');
    }

    public function edit($id)
    {
        $vehicle = Dienstwagen::findOrFail($id);
        return Inertia::render('Dienstwagen/Edit', [
            'vehicle'  => $vehicle->load(['fahrer', 'standort']),
            'drivers'  => Personen::orderBy('nachname')->get(),
            'locations'=> Standort::orderBy('name')->get(),
        ]);
    }

    public function verlauf($id)
    {
        $vehicle = Dienstwagen::with('standort')->findOrFail($id);

        return Inertia::render('Dienstwagen/Verlauf/Index', [
            'vehicle' => $vehicle,
            'entries' => $vehicle->verlaeufe()
                ->with(['user', 'person'])
                ->latest('created_at')
                ->get(),
        ]);
    }

    public function fahrtenbuchCode($id)
    {
        $vehicle = Dienstwagen::with('standort')->findOrFail($id);

        return Inertia::render('Dienstwagen/Fahrtenbuch/CodePrint', [
            'vehicle' => $vehicle,
            'scanUrl' => route('dienstwagen.fahrtenbuch.scan', $vehicle->id),
            'fahrtenbuchUrl' => route('dienstwagen.fahrtenbuch.index', [
                'dienstwagen_id' => $vehicle->id,
            ]),
        ]);
    }


    public function update(Request $request, $id,)
    {
        $dienstwagen = Dienstwagen::findOrFail($id);

        $data = $request->validate([
            ...$this->vehicleRules(),
            'kennzeichen' => 'required|string|unique:dienstwagens,kennzeichen,' . $dienstwagen->id,
            'bild' => 'nullable|image|max:4096',
            'remove_image' => 'nullable|boolean',
            'allowed_drivers' => 'nullable|array',
            'allowed_drivers.*'=> 'integer|exists:personens,id',
        ]);

        $drivers = $data['allowed_drivers'] ?? [];
        $removeImage = (bool) ($data['remove_image'] ?? false);
        unset($data['allowed_drivers'], $data['bild'], $data['remove_image']);

        $data = $this->normalizeVehicleDates($data);

        if ($removeImage && $dienstwagen->bild_path) {
            Storage::disk('public')->delete($dienstwagen->bild_path);
            $data['bild_path'] = null;
        }

        if ($request->hasFile('bild')) {
            if ($dienstwagen->bild_path) {
                Storage::disk('public')->delete($dienstwagen->bild_path);
            }

            $data['bild_path'] = $request->file('bild')->store('dienstwagen/bilder', 'public');
        }

        $original = $dienstwagen->getOriginal();
        $dienstwagen->fill($data);
        $dirty = $dienstwagen->getDirty();
        $dienstwagen->save();

        $oldDrivers = $dienstwagen->fahrer()->pluck('personens.id')->values()->all();
        $dienstwagen->fahrer()->sync($drivers);
        $newDrivers = array_values($drivers);

        $changes = $this->formatChanges($original, $dirty);
        sort($oldDrivers);
        sort($newDrivers);

        if ($oldDrivers !== $newDrivers) {
            $changes['allowed_drivers'] = [
                'old' => $oldDrivers,
                'new' => $newDrivers,
            ];
        }

        if (! empty($changes)) {
            app(DienstwagenVerlaufService::class)->record(
                $dienstwagen,
                'fahrzeug.updated',
                'Dienstwagen aktualisiert',
                'Stammdaten oder Zuordnungen wurden aktualisiert.',
                $changes,
                $dienstwagen
            );
        }

        return redirect()->route('dienstwagen.index')
            ->with('success', 'Fahrzeugdaten erfolgreich aktualisiert.');
    }

    public function destroy($id)
    {
         try {
            $dienstwagen = Dienstwagen::findOrFail($id);

            app(DienstwagenVerlaufService::class)->record(
                $dienstwagen,
                'fahrzeug.deleted',
                'Dienstwagen geloescht',
                'Fahrzeug ' . $dienstwagen->kennzeichen . ' wurde geloescht.',
                [],
                $dienstwagen
            );

            if ($dienstwagen->bild_path) {
                Storage::disk('public')->delete($dienstwagen->bild_path);
            }

            $dienstwagen->delete(); // Lösche die Projekt

            return response()->json(['message' => 'Dienstwagen erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Dienstwagen nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }

    private function vehicleRules(): array
    {
        return [
            'typ' => 'required|string|max:255',
            'marke' => 'required|string|max:255',
            'modell' => 'required|string|max:255',
            'baujahr' => 'required|integer|min:1900|max:' . ((int) date('Y') + 1),
            'kraftstoffart' => 'required|string|max:255',
            'kilometerstand' => 'required|integer|min:0',
            'standort_id' => 'required|integer|exists:standorts,id',
            'status' => 'required|string|max:50',
            'naechste_wartung' => 'nullable|date',
            'fin' => 'nullable|string|max:255',
            'hsn_tsn' => 'nullable|string|max:255',
            'tuev_bis' => 'nullable|date',
            'au_bis' => 'nullable|date',
            'oelwechsel_am' => 'nullable|date',
            'oelwechsel_km' => 'nullable|integer|min:0',
            'versicherung_bis' => 'nullable|date',
            'steuer_faellig_am' => 'nullable|date',
            'inspektion_am' => 'nullable|date',
            'reifenwechsel_am' => 'nullable|date',
            'leasing_bis' => 'nullable|date',
            'tankkarte' => 'nullable|string|max:255',
            'notizen' => 'nullable|string',
        ];
    }

    private function normalizeVehicleDates(array $data): array
    {
        foreach ([
            'naechste_wartung',
            'tuev_bis',
            'au_bis',
            'oelwechsel_am',
            'versicherung_bis',
            'steuer_faellig_am',
            'inspektion_am',
            'reifenwechsel_am',
            'leasing_bis',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $data[$field] ? Carbon::parse($data[$field])->format('Y-m-d') : null;
            }
        }

        return $data;
    }

    private function formatChanges(array $original, array $dirty): array
    {
        $changes = [];

        foreach ($dirty as $field => $newValue) {
            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $newValue,
            ];
        }

        return $changes;
    }
}
