<?php

namespace App\Http\Controllers;

use App\Models\Teilnehmer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Kontakte;

class KontaktController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        $data = $request->validate([
            'kontakttyp_id' => 'required|exists:kontakttypens,id',
            'wert' => 'required|string|max:255',
            'model_type' => 'required|string',
            'model_id' => 'required|integer'
        ]);

        $kontakt = Kontakte::create($data);

        // Optional: zurückgeben, falls du ein Partial reload machen willst
        return redirect()->back()->with('success', 'Kontakt erfolgreich hinzugefügt!');
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

   
    public function destroy($id)
    {
        try {
            $kontakt = Kontakte::findOrFail($id);

            if (!$kontakt) {
                return response()->json(['message' => 'Kontakt nicht gefunden.'], 404);
            }

            $kontakt->delete();

            return response()->json(['message' => 'Kontakt erfolgreich entfernt.'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Teilnehmer nicht gefunden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }


}
