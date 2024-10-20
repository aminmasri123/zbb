<?php

namespace App\Http\Controllers;

use App\Models\Abteilung;
use Inertia\Inertia;
use App\Models\Projekt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjektController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $search = $request->input('search'); // Benutze input(), um den Suchparameter abzurufen
        $abteilungen = Abteilung::select('id', 'name')->get();
        // Hole die Projekte mit Suchfilter und lade die notwendigen Beziehungen
        $projekte = Projekt::query()
        ->when($search, function ($query) use ($search) {
            $query->where('projekts.name', 'like', "%{$search}%"); // Beachte: 'projekts.name' ist hier qualifiziert
        })
            ->join('abteilungs', 'projekts.abteilung_id', '=', 'abteilungs.id') // Führe einen Join durch
            ->select('projekts.*', 'abteilungs.name as abteilung_name') // Wähle die gewünschten Spalten aus
            ->orderBy('projekts.name') // Sortiere nach Projektname
            ->paginate(100) // Paginierung
            ->withQueryString();
            
        // Standardmäßige Rückgabe für die Inertia-Ansicht
        return Inertia::render('Projekt/Index', [
            'projekte' => $projekte,
            'abteilungen' => $abteilungen
        ]);
    }
    public function indexAjaxFresh(Request $request)
    {
        $search = $request->input('search'); // Benutze input(), um den Suchparameter abzurufen
        $abteilungen = Abteilung::select('id', 'name')->get();

        $projekte = Projekt::query()
        ->when($search, function ($query) use ($search) {
            $query->where('projekts.name', 'like', "%{$search}%"); // Beachte: 'projekts.name' ist hier qualifiziert
        })
            ->join('abteilungs', 'projekts.abteilung_id', '=', 'abteilungs.id') // Führe einen Join durch
            ->select('projekts.*', 'abteilungs.name as abteilung_name') // Wähle die gewünschten Spalten aus
            ->orderBy('projekts.name') // Sortiere nach Projektname
            ->paginate(100) // Paginierung
            ->withQueryString();
    
        // Überprüfe, ob die Anfrage als AJAX-Request gesendet wurde
        if ($request->ajax()) {
            return response()->json([
                'projekte' => $projekte,
                'abteilungen' => $abteilungen
            ]);
        };
       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validierung
        $validatedData = $request->validate([
            'name' => 'required|max:50',
            'kostenstelle' => 'required|max:50',
            'abteilung' => 'required|exists:abteilungs,id'
        ]);
        
        try {
            // Abteilung erstellen
            $projekt = Projekt::create([
                'name' => $validatedData['name'],
                'kostenstelle' => $validatedData['kostenstelle'], 
                'abteilung_id' => $validatedData['abteilung'], 
            ]);
            
            //Ajax Automatisch anzeigen
            return response()->json([
                'message' => 'Projekt erfolgreich erstellt.',
                'projekt' => $projekt
            ], 201);

        } catch (\Exception $e) {
            // Fehlerbehandlung
            return response()->json(['error' => 'Beim Erstellen des Projektes ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.'], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $projekt = Projekt::findOrFail($id); 

            // Optional: Überprüfe, ob die Projekt gelöscht werden kann (z.B. durch Beziehungen)
            // if ($abteilung->hasRelations()) { ... }

            $projekt->delete(); // Lösche die Projekt

            return response()->json(['message' => 'Projekt erfolgreich gelöscht!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Projekt nicht gefunden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
