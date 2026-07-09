<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Dienstwagen;
use Illuminate\Http\Request;
use App\Services\DienstwagenVerlaufService;
use App\Models\Dienstwagenkostenaufzeichnungen;

class DienstwagenkostenController extends Controller
{
   public function index()
    {
        return Inertia::render('Dienstwagen/Kosten/Index', [
            'costs'    => Dienstwagenkostenaufzeichnungen::with('dienstwagen')->latest()->get(),
            'vehicles' => Dienstwagen::orderBy('kennzeichen')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'dienstwagen_id' => 'required|exists:dienstwagens,id',
            'art'            => 'required|string|max:255',
            'datum'          => 'required|date',
            'betrag'         => 'required|numeric|min:0',
            'beschreibung'   => 'nullable|string',
        ]);

         if (!empty($data['datum'])) {
            try {
                $data['datum'] = Carbon::parse($data['datum'])->format('Y-m-d');
            } catch (Exception $e) {
                throw new Exception("Ungültiges Datumsformat übermittelt: " . $data['datum']);
            }
        }

        $record = Dienstwagenkostenaufzeichnungen::create($data)->load('dienstwagen');

        app(DienstwagenVerlaufService::class)->record(
            $record->dienstwagen,
            'kosten.created',
            'Kosten erfasst',
            $record->art . ' - ' . number_format((float) $record->betrag, 2, ',', '.') . ' EUR',
            [],
            $record
        );

        return back()->with('success', 'Kosten erfasst.');
    }

    public function update(Request $request, $id)
    {
        $record = Dienstwagenkostenaufzeichnungen::with('dienstwagen')->findOrFail($id);

        $data = $request->validate([
            'dienstwagen_id' => 'required|exists:dienstwagens,id',
            'art'            => 'required|string|max:255',
            'datum'          => 'required|date',
            'betrag'         => 'required|numeric|min:0',
            'beschreibung'   => 'nullable|string',
        ]);

        if (!empty($data['datum'])) {
            $data['datum'] = Carbon::parse($data['datum'])->format('Y-m-d');
        }

        $original = $record->getOriginal();
        $record->fill($data);
        $dirty = $record->getDirty();
        $record->save();
        $record->load('dienstwagen');

        app(DienstwagenVerlaufService::class)->record(
            $record->dienstwagen,
            'kosten.updated',
            'Kosten aktualisiert',
            $record->art . ' - ' . number_format((float) $record->betrag, 2, ',', '.') . ' EUR',
            $this->formatChanges($original, $dirty),
            $record
        );

        return back()->with('success', 'Kosten aktualisiert.');
    }

    public function destroy($id)
    {
        $record = Dienstwagenkostenaufzeichnungen::with('dienstwagen')->findOrFail($id);

        app(DienstwagenVerlaufService::class)->record(
            $record->dienstwagen,
            'kosten.deleted',
            'Kosten geloescht',
            $record->art . ' - ' . number_format((float) $record->betrag, 2, ',', '.') . ' EUR',
            [],
            $record
        );

        $record->delete();

        return response()->json(['message' => 'Kosten gelöscht.']);
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
