<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Bereich;
use Illuminate\Http\Request;

class BereichController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $search = $request->input('search'); // Benutze input(), um den Suchparameter abzurufen
    
        // Hole die Abteilungen mit Suchfilter und lade die notwendigen Beziehungen
        $bereiche = Bereich::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name') // Sortiere nach Name
            ->paginate(20)    // Wende die Paginierung an
            ->withQueryString(); // Behalte die Query-String-Parameter für die Pagination
    
        // Standardmäßige Rückgabe für die Inertia-Ansicht
        return Inertia::render('Bereich/Index', [
            'bereiche' => $bereiche,
        ]);
    }
    public function indexAjaxFresh(Request $request)
    {
        $search = $request->input('search'); // Benutze input(), um den Suchparameter abzurufen
    
        // Hole die Abteilungen mit Suchfilter und lade die notwendigen Beziehungen
        $bereiche = Bereich::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name') // Sortiere nach Name
            ->paginate(20)    // Wende die Paginierung an
            ->withQueryString(); // Behalte die Query-String-Parameter für die Pagination
    
        // Überprüfe, ob die Anfrage als AJAX-Request gesendet wurde
        if ($request->ajax()) {
            return response()->json([
                'bereiche' => $bereiche,
            ]);
        };
        // Standardmäßige Rückgabe für die Inertia-Ansicht
        return Inertia::render('Bereich/Index', [
            'bereiche' => $bereiche,
        ]);
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

    public function store(Request $request)
    {
        // Validierung
        $validatedData = $request->validate([
            'name' => 'required|max:50',
            'beschreibung' => '',
        ]);
        
        try {
            // Abteilung erstellen
            $bereich = Bereich::create([
                'name' => $validatedData['name'],
                'beschreibung' => $validatedData['beschreibung'], // Abteilungsleiter
            ]);
            
            //Ajax Automatisch anzeigen
            return response()->json([
                'message' => 'Abteilung erfolgreich erstellt.',
                'bereich' => $bereich
            ], 201);

        } catch (\Exception $e) {
            // Fehlerbehandlung
            return response()->json(['error' => 'Beim Erstellen des Bereiches ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.'], 500);
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
            $bereich = Bereich::findOrFail($id); 

            // Optional: Überprüfe, ob die Abteilung gelöscht werden kann (z.B. durch Beziehungen)
            // if ($abteilung->hasRelations()) { ... }

            $bereich->delete(); // Lösche die Abteilung

            return response()->json(['message' => 'Abteilung erfolgreich gelöscht!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Abteilung nicht gefunden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }

}
