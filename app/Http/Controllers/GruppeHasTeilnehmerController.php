<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Tage;
use Inertia\Inertia;
use App\Models\Gruppe;
use App\Models\Zeiten;
use App\Models\Personen;
use App\Models\Standort;
use Illuminate\Http\Request;
use App\Models\GruppeHasPersonen;
use App\Http\Controllers\Controller;
use App\Models\Anwesenheitsstatuten;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GruppeHasTeilnehmerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }





    public function store(Request $request)
    {
        $validated = $request->validate([
            'gruppe_id'    => 'required|exists:gruppes,id',
            'teilnehmer'   => 'required|array|min:1',
            'teilnehmer.*' => 'integer|exists:personens,id',
            'startzeit'    => 'required|date_format:H:i',
            'endzeit'      => 'required|date_format:H:i',
            'startdatum'   => 'required|date',
            'enddatum'     => 'required|date',
        ]);

        $anwesenheitsstatuten = Anwesenheitsstatuten::where('status', 'unentschuldigt')->first();

        $ids = array_map('intval', $validated['teilnehmer']);
        $gruppe = Gruppe::findOrFail($validated['gruppe_id']);


        // IDs, die bereits existieren
        $already = $gruppe->teilnehmer()
            ->whereIn('personens.id', $ids)
            ->pluck('personens.id')
            ->all();

        $new = array_values(array_diff($ids, $already));

        // ⏰ geplante & tatsächliche Zeiten anlegen
        $zeitGeplant = Zeiten::firstOrCreate([
            'startzeit' => $validated['startzeit'],
            'endzeit'   => $validated['endzeit'],
        ]);

        $zeitTatsaechlich = Zeiten::create([
            'startzeit' => $validated['startzeit'],
            'endzeit'   => $validated['endzeit'],
        ]);

        // 📅 Alle Tage zwischen Start- und Enddatum ermitteln
        $start = Carbon::parse($validated['startdatum']);
        $end = Carbon::parse($validated['enddatum']);

        $tageIDs = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            // Feiertage / Wochenenden kannst du hier optional überspringen
            $tag = Tage::firstOrCreate([
                'datum' => $date->format('Y-m-d'),
            ], [
                'wochentag' => $date->locale('de')->dayName,
                'feiertag_typ' => 'kein_feiertag',
            ]);

            $tageIDs[] = $tag->id;
        }

        // 🔥 Für jeden Teilnehmer & Tag Eintrag erstellen
        if (count($new) > 0) {
            foreach ($new as $teilnehmerId) {
                foreach ($tageIDs as $tagId) {
                    $gruppe->teilnehmer()->attach($teilnehmerId, [
                        'user_id' => $gruppe->personen_id,
                        'zeitgeplant_id' => $zeitGeplant->id,
                        'zeittatsaechlich_id' => $zeitTatsaechlich->id,
                        'anwesenheitsstatuten_id' => $anwesenheitsstatuten->id,
                        'bemerkung' => null,
                        'tage_id' => $tagId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // ✅ Rückgabe
        $addedTeilnehmer = Personen::whereIn('id', $new)->get(['id', 'vorname', 'nachname']);
        $alreadyTeilnehmer = Personen::whereIn('id', $already)->get(['id', 'vorname', 'nachname']);

        return response()->json([
            'success' => true,
            'message' => count($new) > 0
                ? 'Teilnehmer mit Zeiten und Tagen erfolgreich hinzugefügt.'
                : 'Keine neuen Teilnehmer hinzugefügt.',
            'added'   => $addedTeilnehmer,
            'already' => $alreadyTeilnehmer,
        ]);
    }






    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {

        $gruppe = Gruppe::with([
            'teilnehmer',
            'bereich',

        ])->findOrFail($id);


        $gruppe->teilnehmer->each(function ($t) {
            $t->zeitgeplant = $t->pivot->zeitgeplant;
            $t->zeittatsaechlich = $t->pivot->zeittatsaechlich;
            $t->status = $t->pivot->status;
            $t->tag = $t->pivot->tag;
            $t->user = $t->pivot->user;
        });

        // Gruppiere nach Teilnehmer
        $gruppe->teilnehmer = $gruppe->teilnehmer->unique('id')->values();

        $anwesenheitsstatuten = Anwesenheitsstatuten::all();

        $user = auth()->user();

         $person = Personen::findOrFail($user->id);
        $userStandorte = $person->standorte()->pluck('standorts.id')->toArray();
        $projekt = $user->current_team_id;

        $teilnehmer = Personen::Teilnehmer()
            ->with('standorte', 'projekte')
            ->whereHas('standorte', function($query) use ($userStandorte) {
                    $query->whereIn('standorts.id', $userStandorte);
                })->whereHas('projekte', function ($query) use ($projekt) {
                // prüfe auf die id-Spalte der Projekte
                $query->where('projekts.id', $projekt);
            })
             ->get();
        return Inertia::render('Gruppe/GruppeHasTeilnehmer/Index', [
            'gruppe' => $gruppe,
            'teilnehmer' => $teilnehmer,
            'anwesenheitsstatuten' => $anwesenheitsstatuten,
        ]);

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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
         try {
            $gruppeHasPersonen = GruppeHasPersonen::findOrFail($id);
            $gruppeHasPersonen->delete();

            return response()->json(['success' => true, 'message' => 'Anwesenheit erfolgreich gelöscht!']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Anwesenheit nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
