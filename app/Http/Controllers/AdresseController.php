<?php

namespace App\Http\Controllers;

use App\Models\Adresse;
use Illuminate\Http\Request;

class AdresseController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'strasse' => 'required|string|max:255',
            'hausnummer' => 'required|string|max:50',
            'plz' => 'required|string|max:20',
            'stadt' => 'required|string|max:100',
            'land' => 'required|string|max:100',
            'model_type' => 'required|string',
            'model_id' => 'required|integer'
        ]);

        $adresse = Adresse::create($data);

        // Optional: zurückgeben, falls du ein Partial reload machen willst
        return redirect()->back()->with('success', 'Adresse erfolgreich hinzugefügt!');
    }

    public function destroy($id)
    {
        try {
            $adresse = Adresse::findOrFail($id);

            if (!$adresse) {
                return response()->json(['message' => 'Adresse nicht gefunden.'], 404);
            }

            $adresse->delete();

            return response()->json(['message' => 'Adresse erfolgreich entfernt.'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Adresse nicht gefunden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
