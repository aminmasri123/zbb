<?php

namespace App\Http\Controllers;

use App\Models\Geraet;
use App\Models\Geraetausgabe;
use App\Models\GeraetHasRueckgabe;
use App\Models\Geraetrueckgabe;
use App\Models\Personen;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GeraetrueckgabeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function index()
    {
       /*  $id=1;
         $geraete = DB::table('geraets')
            ->join('geraet_has_ausgabes', 'geraets.id', '=', 'geraet_has_ausgabes.geraet_id')

            ->where('geraet_has_ausgabes.ausgabe_id', $id)

            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('geraet_has_rueckgabes')
                    ->whereColumn('geraet_has_rueckgabes.geraet_id', 'geraets.id');
            })

            ->select('geraets.*')
            ->get();



            dd($geraete);
 */
















        $rueckgaben = Geraetrueckgabe::with([
            'ausgabe.ausleiher',
            'ausgabe.projekte',
            'geraete'
        ])->get();

        $rueckgeber = Personen::where('typ', 'mitarbeiter')->select('id', 'nachname', 'vorname')->get();

        $ausgaben = Geraetausgabe::with([
            'geraete',
            'ausleiher',
            'projekte'
        ])->get();

        $geraete = Geraet::all();

        $ablageorte = Geraet::distinct()
            ->whereNotNull('imLager')
            ->pluck('imLager');
/*
        $ausgegebeneGeraete = DB::table('geraets')
            ->leftJoin('geraet_has_ausgabes', 'geraets.id', '=', 'geraet_has_ausgabes.geraet_id')
            ->whereNotNull('geraet_has_ausgabes.geraet_id')
            ->select('geraets.*')
            ->get(); */
        return Inertia::render('Geraet/Rueckgabe/Index', [
            'rueckgaben' => $rueckgaben,
            'rueckgeber' => $rueckgeber,
            'ausgaben' => $ausgaben,
            /**
             * Zeigt die Geräte, die ausgegeben wurden, aber noch nicht zurückgegeben wurden.
             */
           
            'geraete' => $geraete,
            'ablageorte' => $ablageorte,
            //'ausgegebeneGeraete' => $ausgegebeneGeraete
        ]);
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
            'ausgabeschein_nr' => 'required|exists:geraetausgabes,id',
            'ausleiher' => 'required|exists:personens,id',
            'rueckgabescheinNr' => 'required|unique:geraetrueckgabes,rueckgabescheinNr',
            'sn' => 'required|array',
            'sn.*' => 'exists:geraets,id',
            'rueckgabedatum' => 'required|date',
            'ablageort' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {

            $success = [];
            $errors = [];

            $rueckgabedatum = Carbon::parse($validated['rueckgabedatum'])->format('Y-m-d');

            $rueckgabe = Geraetrueckgabe::create([
                'ausgabe_id' => $validated['ausgabeschein_nr'],
                'ausleiher_id' => $validated['ausleiher'],
                'rueckgabescheinNr' => $validated['rueckgabescheinNr'],
                'rueckgabe' => $rueckgabedatum
            ]);

            foreach ($validated['sn'] as $geraetId) {

                $geraet = Geraet::find($geraetId);

                if (!$geraet) {
                    $errors[] = "Gerät nicht gefunden: {$geraetId}";
                    continue;
                }
            
                GeraetHasRueckgabe::create([
                    'geraet_id' => $geraet->id,
                    'rueckgabe_id' => $rueckgabe->id
                ]);

                $geraet->update([
                    'verfuegbarkeit' => true,
                    'imLager' => $validated['ablageort']
                ]);

                $success[] = $geraet->sn;
            }

            DB::commit();

            return redirect()
                ->route('geraet.rueckgabe.index')
                ->with('success', 'Rückgabe erfolgreich erstellt.');

        } catch (Exception $e) {

            DB::rollBack();

            return redirect()
                ->back()
                ->withErrors([
                    'error' => 'Fehler beim Speichern der Rückgabe: ' . $e->getMessage()
                ]);
        }
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
            $geraetrueckgabe = Geraetrueckgabe::findOrFail($id);

            $geraetrueckgabe->delete();

            return response()->json(['message' => 'Rückgabe erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Rückgabe nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }

    public function geraete($id)
    {
        
        $ausgabe = DB::table('geraets')
            ->join('geraet_has_ausgabes', 'geraets.id', '=', 'geraet_has_ausgabes.geraet_id')

            ->where('geraet_has_ausgabes.ausgabe_id', $id)

            ->whereNotExists(function ($query) use ($id) {
                $query->select(DB::raw(1))
                    ->from('geraet_has_rueckgabes')
                    ->join('geraetrueckgabes', 'geraetrueckgabes.id', '=', 'geraet_has_rueckgabes.rueckgabe_id')
                    ->whereColumn('geraet_has_rueckgabes.geraet_id', 'geraets.id')
                    ->where('geraetrueckgabes.ausgabe_id', $id);
            })

            ->select('geraets.*')
            ->get();

        return response()->json($ausgabe);
    }


     
}
