<?php

namespace App\Http\Controllers;

use App\Models\Geraet;
use App\Models\Geraetausgabe;
use App\Models\GeraetHasAusgabe;
use App\Models\Personen;
use App\Models\Projekt;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GeraetausgabeController extends Controller
{
    public function index()
    {
       // dd(Geraetausgabe::with(['ausleiher','projekte', 'projekte.kostenstellen' ,'geraete'])->get());
        return Inertia::render('Geraet/Ausgabe/Index', [
            'ausgaben' => Geraetausgabe::with(['ausleiher','projekte', 'projekte.kostenstellen' ,'geraete'])->get(),
            'ausleiher' => Personen::where('typ', 'mitarbeiter')->select('id', 'nachname', 'vorname')->get(),
            'projekte' => Projekt::all(),
            'geraete' => Geraet::where('verfuegbarkeit', true)->get(),
            //'geraete' => Geraet::whereNull('ausgabe_id')->get(),
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
            'ausgabeschein_nr' => 'required|unique:geraetausgabes,ausgabescheinNr',
            'ausleiher' => 'required|exists:personens,id',
            'projekt' => 'required|exists:projekts,id',
            'sn' => 'required|array',
            'sn.*' => 'exists:geraets,sn',
            'ausleihdatum' => 'required|date'
        ]);
        DB::beginTransaction();
        try {
            $ausleihdatum = Carbon::parse($validated['ausleihdatum'])->format('Y-m-d');
            $ausgabe = Geraetausgabe::create([
                'ausgabescheinNr' => $validated['ausgabeschein_nr'],
                'kontakte_id' => $validated['ausleiher'],
                'projekte_id' => $validated['projekt'],
                'ausgabe' => $ausleihdatum,
            ]);


            $success = [];
            $error = [];

        foreach ($validated['sn'] as $SN) {

                $geraet = Geraet::where('sn', $SN)->first();
                $geraet->update([
                    'verfuegbarkeit' => false,
                ]);
                if (!$geraet) {
                    $error[] = $SN;
                    continue;
                }

                GeraetHasAusgabe::create([
                    'geraet_id' => $geraet->id,
                    'ausgabe_id' => $ausgabe->id
                ]);

                $kontakt = Personen::find($validated['ausleiher']);

               /*  $geraet->update([
                    'verfuegbarkeit' => false,
                    'imLager' => $kontakt->vorname . ' ' . $kontakt->nachname
                ]); */

                $success[] = $SN;
            }


            DB::commit();

            return redirect()
                ->route('geraet.ausgabe.index')
                ->with('success', 'Ausgabe erfolgreich erstellt.');

        } /* catch (Exception $e) {

            DB::rollBack();

            return redirect()
                ->back()
                ->withErrors([
                    'error' => 'Fehler beim Speichern der Ausgabe.'
                ]);
        } */

                catch (Exception $e) {
    DB::rollBack();
    dd($e->getMessage(), $e->getTraceAsString());
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




    //Löschung darf nicht erfolgen, wenn die Ausgabe mit einem Gerät verknüpft ist. In diesem Fall muss zuerst
    // die Verknüpfung aufgehoben und das Gerät als verfügbar markiert werden.
    public function destroy($id)
    {
         try {
            $geraetausgabe = Geraetausgabe::findOrFail($id);

            $geraetausgabe->delete();

            return response()->json(['message' => 'Ausgabe erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Ausgabe nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
