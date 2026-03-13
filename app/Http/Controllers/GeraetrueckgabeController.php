<?php

namespace App\Http\Controllers;

use App\Models\Geraet;
use App\Models\Geraetausgabe;
use App\Models\GeraetHasRueckgabe;
use App\Models\Geraetrueckgabe;
use App\Models\Personen;
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
        $rueckgaben = Geraetrueckgabe::with([
            'ausgabe.kontakte',
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
        dd($request->all());
        $validated = $request->validate([
            'ausgabeschein_nr' => 'required|exists:ausgabes,id',
            'ausleiher' => 'required|exists:kontaktes,id',
            'rueckgabescheinNr' => 'required|unique:rueckgabes,rueckgabescheinNr',
            'sn' => 'required|array',
            'sn.*' => 'exists:geraets,id',
            'rueckgabedatum' => 'required|date',
            'ablageort' => 'required'
        ]);

        $success = [];
        $errors = [];

        $rueckgabe = Geraetrueckgabe::create([
            'ausgabe_id' => $validated['ausgabeschein_nr'],
            'kontakte_id' => $validated['ausleiher'],
            'rueckgabescheinNr' => $validated['rueckgabescheinNr'],
            'rueckgabe' => $validated['rueckgabedatum']
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

        /*
        |-------------------------------------
        | Notifications
        |-------------------------------------
        */
        /*
        $roles = Role::whereIn('name', ['Administrator', 'IT-Administrator'])
            ->with('users')
            ->get();

        foreach ($roles as $role) {
            foreach ($role->users as $user) {
                $user->notify(new CreateRueckgabeNotification($success));
            }
        } */

        return redirect()
            ->route('geraet.rueckgabe.index')
            ->with('success', 'Rückgabe erfolgreich erstellt.');
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
        //
    }

    public function geraete($id)
    {
        $ausgabe = Geraetausgabe::with('geraete')->findOrFail($id);

        return response()->json([
            'geraete' => $ausgabe->geraete
        ]);
    }
}
