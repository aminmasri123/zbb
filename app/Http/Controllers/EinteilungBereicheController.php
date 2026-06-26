<?php

namespace App\Http\Controllers;

use App\Models\EinteilungBereiche;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EinteilungBereicheController extends Controller
{
    public function index($partnerId, $schuljahr, $teil)
    {
        $projekt = Projekt::with('bereiche')->find(Auth()->user()->current_team_id);

        // 1. Alle Bereiche laden (ohne Potenzialanalyse)
        $alle_bereiche = $projekt->bereiche
            ->where('name', '!=', 'Potenzialanalyse')
            ->values();

        // 2. Das Results-Array mit allen Bereichen und Runden 1-3 vorbefüllen (damit sie im Frontend erscheinen)
        $results = [];
        foreach ($alle_bereiche as $b) {
            $slug = Str::slug($b->name);
            $results[$slug] = [1 => [], 2 => [], 3 => []];
        }

        // 3. EIN Query für alle Einteilungen
        $alleEinteilungen = EinteilungBereiche::with(['teilnehmende.person'])
            ->whereIn('bereich_id', $alle_bereiche->pluck('id'))
            ->whereHasMorph('teilnehmende', [PersonenIstSchueler::class], function ($q) use ($partnerId, $schuljahr, $teil) {
                $q->where('schule_id', $partnerId)->where('schuljahr', $schuljahr)->where('teil', $teil);
            })
            ->get();

        // 4. Die gefundenen Teilnehmer in das vorbefüllte Array einsortieren
        foreach ($alleEinteilungen as $e) {
            $slug = Str::slug($alle_bereiche->firstWhere('id', $e->bereich_id)->name);
            $results[$slug][$e->runde][] = $this->formatTeilnehmer($e);
        }

        return Inertia::render('Teilnehmer/Einteilung/Index', [
            'results' => $results,
            'alle_bereiche' => $alle_bereiche,
            'updated_at' => $alleEinteilungen->max('updated_at')?->toIso8601String(),
        ]);
    }

    private function formatTeilnehmer($e)
    {
        $m = $e->teilnehmende;
        $isSchueler = $m instanceof \App\Models\PersonenIstSchueler;

        // Wir holen alle 3 Einteilungen für diesen Teilnehmer
        $alleRunden = $m->einteilungen->pluck('bereich_id', 'runde');

        return [
            'id'         => $m->id,
            'vorname'    => $isSchueler ? ($m->person->vorname ?? '') : $m->vorname,
            'nachname'   => $isSchueler ? ($m->person->nachname ?? '') : $m->nachname,
            'geschlecht' => $isSchueler ? ($m->person->geschlecht ?? '') : $m->geschlecht,
            'klasse'     => $isSchueler ? $m->klasse : '-',
            // Hier speichern wir die IDs für die Dropdowns (Runde 1, 2, 3)
            'einteilung_ids' => [
                1 => $alleRunden[1] ?? '',
                2 => $alleRunden[2] ?? '',
                3 => $alleRunden[3] ?? ''
            ]
        ];
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
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

    /**
     * Update the specified resource in storage.
     */
   public function update(Request $request)
    {
        $request->validate([
            'schueler_id' => 'required|integer|exists:personen_ist_schuelers,id',
            'runde_1' => 'nullable|integer',
            'runde_2' => 'nullable|integer',
            'runde_3' => 'nullable|integer',
            'seite' => 'nullable|string',
        ]);

        $schueler = PersonenIstSchueler::findOrFail($request->schueler_id);

        foreach ([1, 2, 3] as $runde) {
            $bereichId = $request->{'runde_'.$runde};
            $eintrag = $schueler->einteilungen()->where('runde', $runde)->first();

            if ($bereichId) {
                if ($eintrag) {
                    $eintrag->update(['bereich_id' => $bereichId]);
                } else {
                    $schueler->einteilungen()->create([
                        'bereich_id' => $bereichId,
                        'runde' => $runde,
                    ]);
                }
            } else {
                if ($eintrag) $eintrag->delete();
            }
        }

        // 🔹 Lade die neuen Einteilungen zurück, um sie sofort an das Frontend zu senden
        $einteilung_ids = $schueler->einteilungen()->pluck('bereich_id', 'runde')->toArray();

        return response()->json([
            'message' => 'Einteilung erfolgreich aktualisiert',
            'schueler_id' => $schueler->id,
            'einteilung_ids' => $einteilung_ids,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
