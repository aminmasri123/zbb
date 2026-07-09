<?php

namespace App\Http\Controllers;

use Exception;
use Throwable;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Dienstwagen;
use Illuminate\Http\Request;
use App\Models\ProjektHasPersonen;
use Illuminate\Support\Facades\Log;
use App\Services\DienstwagenVerlaufService;
use App\Models\Dienstwagenfahrtenbuch;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DienstwagenfahrtenbuchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
{
    $user = auth()->user();
    $person = $user->person;

    if (!$person) {
        abort(403, "Keine Person verknüpft.");
    }

    // Projekte + Pivot-Daten des Users
    $projektIds   = $person->projekte->pluck('id');
    $standortIds  = $person->projekte->pluck('pivotModel.standort_id');
    $abteilungId  = $person->projekte()->distinct()->pluck('abteilung_id') ?? null;
    $selectedVehicleId = $request->filled('dienstwagen_id') ? $request->integer('dienstwagen_id') : null;
    /** -----------------------------------------------
     * ROLLE 1: DIENSTWAGENKOORDINATOR → Vollzugriff
     * -----------------------------------------------*/
    if ($user->can('dienstwagen.fahrtenbuch.view.all')) {

        $fahrten = Dienstwagenfahrtenbuch::with(['dienstwagen', 'fahrer'])
            ->when($selectedVehicleId, fn ($query) => $query->where('dienstwagen_id', $selectedVehicleId))
            ->orderBy('date', 'desc')
            ->get();

        $fahrer = Personen::mitarbeiter()
            ->aktiv()
            ->with(['dienstwagen', 'dienstwagenfahrten'])
            ->orderBy('nachname')->orderBy('vorname')
            ->get();

        $dienstwagen = Dienstwagen::all();

        return Inertia::render('Dienstwagen/Fahrtenbuch/Index', [
            'entries'  => $fahrten,
            'drivers'  => $fahrer,
            'vehicles' => $dienstwagen,
            'selectedVehicle' => $this->selectedVehicleFrom($dienstwagen, $selectedVehicleId),
            'selectedVehicleId' => $selectedVehicleId,
        ]);
    }
    /** -----------------------------------------------
     * ROLLE 2: ABTEILUNGSLEITER + ASSISTENZ
     * Darf ALLE sehen, die zur gleichen Abteilung gehören
     * -----------------------------------------------*/
    if ($user->can('dienstwagen.fahrtenbuch.view.abteilung')) {

        // Alle Projekte der Abteilung holen
        $abteilungsProjektIds = Projekt::whereIn('abteilung_id', $abteilungId)->pluck('id');
        // Fahrer: Mitarbeiter müssen einem Projekt der Abteilung angehören (aktiv)
        $fahrer = Personen::mitarbeiter()
            ->whereHas('projekte', function ($q) use ($abteilungsProjektIds, $standortIds) {
                $q->whereIn('projekt_id', $abteilungsProjektIds)
                ->whereIn('standort_id', $standortIds)
                ->where('status', 'aktiv');
            })
            ->with(['dienstwagen', 'dienstwagenfahrten'])
            ->active()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();

        // Fahrten: Nur Fahrten von Mitarbeitern, die in Projekten der Abteilung aktiv sind
        $fahrten = Dienstwagenfahrtenbuch::with(['dienstwagen', 'fahrer'])
                ->whereHas('fahrer.projekte', function ($q) use ($abteilungsProjektIds, $standortIds) {
                    $q->whereIn('projekt_id', $abteilungsProjektIds)
                    ->whereIn('standort_id', $standortIds)
                    ->where('status', 'aktiv');
                })
                ->when($selectedVehicleId, fn ($query) => $query->where('dienstwagen_id', $selectedVehicleId))
                ->orderBy('date', 'desc')
                ->get();

            // Fahrzeuge (Dienstwagen) nach Standort des Users
            $dienstwagen = Dienstwagen::whereIn('standort_id', $standortIds)->get();

            return Inertia::render('Dienstwagen/Fahrtenbuch/Index', [
                'entries'  => $fahrten,
                'drivers'  => $fahrer,
                'vehicles' => $dienstwagen,
                'selectedVehicle' => $this->selectedVehicleFrom($dienstwagen, $selectedVehicleId),
                'selectedVehicleId' => $selectedVehicleId,
            ]);
    }

    /** -----------------------------------------------
     * ROLLE 3: PROJEKTLEITER
     * Darf ALLE sehen, die im selben Projekt + Standort + aktiv sind
     * -----------------------------------------------*/
    if ($user->can('dienstwagen.fahrtenbuch.view.projekt')) {
        $fahrer = Personen::mitarbeiter()
            ->whereHas('projekte', function ($q) use ($projektIds, $standortIds) {
                $q->whereIn('projekt_id', $projektIds)
                  ->whereIn('standort_id', $standortIds)
                  ->where('status', 'aktiv');
            })
            ->with(['dienstwagen', 'dienstwagenfahrten'])
            ->orderBy('nachname')->orderBy('vorname')
            ->get();

        $fahrten = Dienstwagenfahrtenbuch::with(['dienstwagen', 'fahrer'])
            ->whereHas('fahrer.projekte', function ($q) use ($projektIds, $standortIds) {
                $q->whereIn('projekt_id', $projektIds)
                  ->whereIn('standort_id', $standortIds)
                  ->where('status', 'aktiv');
            })
            ->when($selectedVehicleId, fn ($query) => $query->where('dienstwagen_id', $selectedVehicleId))
            ->orderBy('date', 'desc')
            ->get();

        $dienstwagen = Dienstwagen::whereIn('standort_id', $standortIds)->get();

        return Inertia::render('Dienstwagen/Fahrtenbuch/Index', [
            'entries'  => $fahrten,
            'drivers'  => $fahrer,
            'vehicles' => $dienstwagen,
            'selectedVehicle' => $this->selectedVehicleFrom($dienstwagen, $selectedVehicleId),
            'selectedVehicleId' => $selectedVehicleId,
        ]);
    }
    /** -----------------------------------------------
     * ROLLE 4: ANLEITER / SOZIALPÄD / NORMALER MITARBEITER
     * → Sieht nur eigene Fahrten + eigene Fahrzeuge
     * -----------------------------------------------*/
    $fahrten = Dienstwagenfahrtenbuch::with(['dienstwagen', 'fahrer'])
        ->where('person_id', $person->id)
        ->when($selectedVehicleId, fn ($query) => $query->where('dienstwagen_id', $selectedVehicleId))
        ->orderBy('date', 'desc')
        ->get();

    $fahrer = Personen::where('id', $person->id)
        ->with(['dienstwagen', 'dienstwagenfahrten'])
        ->get();

    $dienstwagen = $person->dienstwagen;

    return Inertia::render('Dienstwagen/Fahrtenbuch/Index', [
        'entries'  => $fahrten,
        'drivers'  => $fahrer,
        'vehicles' => $dienstwagen,
        'selectedVehicle' => $this->selectedVehicleFrom($dienstwagen, $selectedVehicleId),
        'selectedVehicleId' => $selectedVehicleId,
    ]);
}

    public function scan($id)
    {
        Dienstwagen::findOrFail($id);

        return redirect()->route('dienstwagen.fahrtenbuch.index', [
            'dienstwagen_id' => $id,
        ]);
    }


    public function store(Request $request)
    {
        try {
            // 1. Validierung
            $data = $this->validatedFahrt($request);
            // 2. Datum parsen

            try {
                $data['date'] = Carbon::parse($data['date'])->format('Y-m-d');
            } catch (\Exception $e) {
                Log::error('Fehler beim Datum-Parsing (store): ' . $e->getMessage());
                return back()->withErrors(['date' => 'Ungültiges Datum.'])->withInput();
            }

            // -----------------------------------------
            // 3. KM-LOGIK: Prüfen ob Start-KM korrekt ist
            // -----------------------------------------
            $lastTrip = Dienstwagenfahrtenbuch::where('dienstwagen_id', $data['dienstwagen_id'])
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($lastTrip) {
                if ((int)$lastTrip->end_km !== (int)$data['start_km']) {
                    return back()
                        ->withErrors([
                            'start_km' => "Der Start-Kilometerstand muss dem letzten End-KM entsprechen: {$lastTrip->end_km} km."
                        ])
                        ->withInput();
                }
            }
            // Wenn es keine Fahrt gibt → keine Prüfung nötig

            // 4. Speichern
            $fahrt = Dienstwagenfahrtenbuch::create($data);
            $this->syncVehicleKilometerstand((int) $data['dienstwagen_id']);

            app(DienstwagenVerlaufService::class)->record(
                $fahrt->dienstwagen,
                'fahrt.created',
                'Fahrt erfasst',
                $this->tripSummary($fahrt),
                [],
                $fahrt
            );

            return redirect()
                ->route('dienstwagen.fahrtenbuch.index', $this->redirectVehicleFilter($request))
                ->with('success', 'Fahrt wurde erfolgreich gespeichert.');

        } catch (\Throwable $e) {

            Log::error('Fehler beim Speichern einer Fahrt: ' . $e->getMessage());

            return back()
                ->withErrors(['general' => 'Beim Speichern ist ein Fehler aufgetreten.'])
                ->withInput();
        }
    }

    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        try {
            $fahrt = Dienstwagenfahrtenbuch::findOrFail($id);
            // 1. Validierung
            $oldVehicleId = $fahrt->dienstwagen_id;
            $data = $this->validatedFahrt($request);

            // 2. Datum sauber parsen
            try {
                $data['date'] = Carbon::parse($data['date'])->format('Y-m-d');
            } catch (Exception $e) {
                Log::error('Fehler beim Datum-Parsing (update): ' . $e->getMessage());
                return back()->withErrors(['date' => 'Ungültiges Datum.'])->withInput();
            }

            // -----------------------------------------
            // 3. Prüfung: stimmt der Start-KM mit vorheriger Fahrt überein?
            // -----------------------------------------
            $previousTrip = $this->previousTrip((int) $data['dienstwagen_id'], $data['date'], (int) $id);

            if ($previousTrip) {
                if ((int)$previousTrip->end_km !== (int)$data['start_km']) {
                    return back()
                        ->withErrors([
                            'start_km' => "Der Start-Kilometerstand muss dem letzten End-KM entsprechen: {$previousTrip->end_km} km."
                        ])
                        ->withInput();
                }
            }

            $nextTrip = $this->nextTrip((int) $data['dienstwagen_id'], $data['date'], (int) $id);

            if ($nextTrip && (int) $nextTrip->start_km !== (int) $data['end_km']) {
                return back()
                    ->withErrors([
                        'end_km' => "Der End-Kilometerstand muss zum naechsten Start-KM passen: {$nextTrip->start_km} km."
                    ])
                    ->withInput();
            }

            // 4. Fahrt aktualisieren
            $original = $fahrt->getOriginal();
            $fahrt->fill($data);
            $dirty = $fahrt->getDirty();
            $fahrt->save();
            $fahrt->load('dienstwagen');
            $this->syncVehicleKilometerstand((int) $oldVehicleId);
            $this->syncVehicleKilometerstand((int) $data['dienstwagen_id']);

            app(DienstwagenVerlaufService::class)->record(
                $fahrt->dienstwagen,
                'fahrt.updated',
                'Fahrt aktualisiert',
                $this->tripSummary($fahrt),
                $this->formatChanges($original, $dirty),
                $fahrt
            );

            return redirect()
                ->route('dienstwagen.fahrtenbuch.index')
                ->with('success', 'Fahrt wurde erfolgreich aktualisiert.');

        } catch (\Throwable $e) {

            Log::error('Fehler beim Aktualisieren einer Fahrt: ' . $e->getMessage());

            return back()
                ->withErrors(['general' => 'Beim Aktualisieren ist ein Fehler aufgetreten.'])
                ->withInput();
        }
    }


    public function destroy($id)
    {
        try {
            $fahrt = Dienstwagenfahrtenbuch::with('dienstwagen')->findOrFail($id);
            $vehicleId = $fahrt->dienstwagen_id;

            app(DienstwagenVerlaufService::class)->record(
                $fahrt->dienstwagen,
                'fahrt.deleted',
                'Fahrt geloescht',
                $this->tripSummary($fahrt),
                [],
                $fahrt
            );

            $fahrt->delete(); // Lösche die Projekt
            $this->syncVehicleKilometerstand((int) $vehicleId);

            return response()->json(['message' => 'Fahrt erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Fahrt nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }

    public function generateFahrtenbuchReport(Request $request)
    {
        return $this->generateFahrtenbuchPDF($request);
    }

    public function generateFahrtenbuchPDF(Request $request)
    {
        $entries = $this->reportEntries($request);
        $vehicle = $this->reportVehicle($request);

        $pdf = Pdf::loadView('pdf.dienstwagen-fahrtenbuch', [
            'entries' => $entries,
            'vehicle' => $vehicle,
            'month' => $request->get('monat'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('Fahrtenbuch_' . now()->format('Ymd_His') . '.pdf');
    }

    public function generateFahrtenbuchExcel(Request $request)
    {
        $entries = $this->reportEntries($request);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Fahrtenbuch');

        $headers = ['Datum', 'Fahrzeug', 'Fahrer', 'Startort', 'Ziel', 'Start km', 'Ende km', 'Distanz', 'Fahrtart', 'Zweck', 'Geschaeftspartner', 'Bemerkung'];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($entries as $entry) {
            $sheet->fromArray([
                optional($entry->date)->format('d.m.Y'),
                $entry->dienstwagen?->kennzeichen,
                trim(($entry->fahrer?->nachname ?? '') . ' ' . ($entry->fahrer?->vorname ?? '')),
                $entry->startort,
                $entry->ziel,
                $entry->start_km,
                $entry->end_km,
                $entry->end_km - $entry->start_km,
                $entry->fahrtart,
                $entry->zweck,
                $entry->geschaeftspartner,
                $entry->bemerkung,
            ], null, 'A' . $row);
            $row++;
        }

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $filename = 'Fahrtenbuch_' . now()->format('Ymd_His') . '.xlsx';
        $path = $tmpDir . DIRECTORY_SEPARATOR . uniqid('fahrtenbuch_', true) . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function validatedFahrt(Request $request): array
    {
        return $request->validate([
            'dienstwagen_id' => 'required|exists:dienstwagens,id',
            'person_id'      => 'required|exists:personens,id',
            'date'           => 'required|date',
            'startort'       => 'nullable|string|max:255',
            'start_km'       => 'required|integer|min:0',
            'end_km'         => 'required|integer|gte:start_km',
            'zweck'          => 'required|string|max:255',
            'ziel'           => 'required|string|max:255',
            'fahrtart'       => ['required', Rule::in(['dienstlich', 'privat', 'arbeitsweg'])],
            'geschaeftspartner' => 'nullable|string|max:255',
            'bemerkung'      => 'nullable|string',
        ]);
    }

    private function previousTrip(int $vehicleId, string $date, ?int $ignoreId = null): ?Dienstwagenfahrtenbuch
    {
        return Dienstwagenfahrtenbuch::where('dienstwagen_id', $vehicleId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('date', '<=', $date)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }

    private function nextTrip(int $vehicleId, string $date, ?int $ignoreId = null): ?Dienstwagenfahrtenbuch
    {
        return Dienstwagenfahrtenbuch::where('dienstwagen_id', $vehicleId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('date', '>=', $date)
            ->orderBy('date')
            ->orderBy('id')
            ->first();
    }

    private function syncVehicleKilometerstand(int $vehicleId): void
    {
        $maxKm = Dienstwagenfahrtenbuch::where('dienstwagen_id', $vehicleId)->max('end_km');

        if ($maxKm !== null) {
            Dienstwagen::where('id', $vehicleId)->update(['kilometerstand' => $maxKm]);
        }
    }

    private function tripSummary(Dienstwagenfahrtenbuch $fahrt): string
    {
        return trim(($fahrt->date ? Carbon::parse($fahrt->date)->format('d.m.Y') : '') . ' | ' .
            ($fahrt->dienstwagen?->kennzeichen ?? 'Dienstwagen') . ' | ' .
            $fahrt->start_km . ' - ' . $fahrt->end_km . ' km | ' .
            $fahrt->ziel);
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

    private function reportEntries(Request $request)
    {
        return Dienstwagenfahrtenbuch::with(['dienstwagen', 'fahrer'])
            ->when($request->filled('dienstwagen_id'), fn ($query) => $query->where('dienstwagen_id', $request->integer('dienstwagen_id')))
            ->when($request->filled('monat'), function ($query) use ($request) {
                $start = Carbon::parse($request->get('monat') . '-01')->startOfMonth();
                $end = Carbon::parse($request->get('monat') . '-01')->endOfMonth();
                $query->whereBetween('date', [$start, $end]);
            })
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    private function reportVehicle(Request $request): ?Dienstwagen
    {
        if (! $request->filled('dienstwagen_id')) {
            return null;
        }

        return Dienstwagen::find($request->integer('dienstwagen_id'));
    }

    private function selectedVehicleFrom($vehicles, ?int $vehicleId): ?Dienstwagen
    {
        if (! $vehicleId) {
            return null;
        }

        return $vehicles->firstWhere('id', $vehicleId);
    }

    private function redirectVehicleFilter(Request $request): array
    {
        if (! $request->filled('redirect_dienstwagen_id')) {
            return [];
        }

        return [
            'dienstwagen_id' => $request->integer('redirect_dienstwagen_id'),
        ];
    }
}
