<?php

namespace App\Http\Controllers;

use Log;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Abteilung;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use App\Models\Abteilungsassistent;

class AbteilungController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search'); // Benutze input(), um den Suchparameter abzurufen
        $users = User::select('id', DB::raw("CONCAT(first_name, ' ', last_name) AS full_name"))->distinct()->get();

        // Hole die Abteilungen mit Suchfilter und lade die notwendigen Beziehungen
        $abteilungen = Abteilung::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->with('user:id,first_name,last_name') // Lade den Abteilungsleiter
            ->with('abteilungsassistente.user') // Lade die Abteilungsassistenten
            ->orderBy('name') // Sortiere nach Name
            ->paginate(100)    // Wende die Paginierung an
            ->withQueryString(); // Behalte die Query-String-Parameter für die Pagination


        // Standardmäßige Rückgabe für die Inertia-Ansicht
        return Inertia::render('Abteilung/Index', [
            'abteilungen' => $abteilungen,
            'users' => $users,
        ]);

    }
    public function indexAjaxFresh(Request $request)
    {
        $search = $request->input('search'); // Benutze input(), um den Suchparameter abzurufen
        $users = User::select('id', DB::raw("CONCAT(first_name, ' ', last_name) AS full_name"))->distinct()->get();

        // Hole die Abteilungen mit Suchfilter und lade die notwendigen Beziehungen
        $abteilungen = Abteilung::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->with('user:id,first_name,last_name') // Lade den Abteilungsleiter
            ->with('abteilungsassistente.user') // Lade die Abteilungsassistenten
            ->orderBy('name') // Sortiere nach Name
            ->paginate(100)    // Wende die Paginierung an
            ->withQueryString(); // Behalte die Query-String-Parameter für die Pagination

        // Überprüfe, ob die Anfrage als AJAX-Request gesendet wurde
        if ($request->ajax()) {
            return response()->json([
                'abteilungen' => $abteilungen,
                'users' => $users,
            ]);
        };
    }
    /* public function indexAjaxFresh(Request $request)
    {
        $search = $request->input('search'); // Benutze input(), um den Suchparameter abzurufen
        $users = User::select('id', DB::raw("CONCAT(first_name, ' ', last_name) AS full_name"))->distinct()->get();

        // Hole die Abteilungen mit Suchfilter und lade die notwendigen Beziehungen
        $abteilungen = Abteilung::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->with('user:id,first_name,last_name') // Lade den Abteilungsleiter
            ->with('abteilungsassistente.user') // Lade die Abteilungsassistenten
            ->orderBy('name') // Sortiere nach Name
            ->paginate(100)    // Wende die Paginierung an
            ->withQueryString(); // Behalte die Query-String-Parameter für die Pagination

        // Überprüfe, ob die Anfrage als AJAX-Request gesendet wurde
        if ($request->ajax()) {
            return response()->json([
                'abteilungen' => $abteilungen,
                'users' => $users,
            ]);
        };
    } */

    public function store(Request $request)
    {
        // Validierung
        $validatedData = $request->validate([
            'name' => 'required|max:255',
            'abteilungsleiter' => 'required|exists:users,id',
            'assistenten' => 'array',
            'assistenten.*' => 'exists:users,id',
        ]);

        try {
            // Abteilung erstellen
            $abteilung = Abteilung::create([
                'name' => $validatedData['name'],
                'user_id' => $validatedData['abteilungsleiter'], // Abteilungsleiter
            ]);

            // Abteilungsassistenten hinzufügen, falls vorhanden
            if (isset($validatedData['assistenten']) && count($validatedData['assistenten']) > 0) {
                foreach ($validatedData['assistenten'] as $assistentenId) {
                    Abteilungsassistent::create([
                        'user_id' => $assistentenId,
                        'abteilung_id' => $abteilung->id,
                    ]);
                }
            }

            // Lade den Abteilungsleiter und die Assistenten für die Antwort
            $abteilung->load('user', 'abteilungsassistente.user');

            // Erfolgreiche Antwort mit der Abteilung, dem Abteilungsleiter und den Assistenten
            return response()->json([
                'message' => 'Abteilung erfolgreich erstellt.',
                'abteilung' => $abteilung
            ], 201);

        } catch (\Exception $e) {
            // Fehlerbehandlung
            return response()->json(['error' => 'Beim Erstellen der Abteilung ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.'], 500);
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

    public function destroy($id)
    {
        try {
            $abteilung = Abteilung::findOrFail($id); // Suche die Abteilung

            // Optional: Überprüfe, ob die Abteilung gelöscht werden kann (z.B. durch Beziehungen)
            // if ($abteilung->hasRelations()) { ... }

            $abteilung->delete(); // Lösche die Abteilung

            return response()->json(['message' => 'Abteilung erfolgreich gelöscht!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Abteilung nicht gefunden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
