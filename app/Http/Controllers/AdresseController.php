<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Models\Adresse;
use Illuminate\Http\Request;

class AdresseController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Validierung der Eingaben
            $data = $request->validate([
                'strasse' => 'required|string|max:255',
                'hausnummer' => 'required|string|max:50',
                'plz' => 'required|string|max:20',
                'stadt' => 'required|string|max:100',
                'land' => 'required|string|max:100',
                'model_type' => 'required|string',
                'model_id' => 'required|integer'
            ]);

            // Erstellen der Adresse
            //$adresse = Adresse::create($data);
            $adresse = Adresse::updateOrCreate(
    [
        'model_type' => $data['model_type'],
        'model_id'   => $data['model_id'],
    ],
    [
        'strasse'     => $data['strasse'],
        'hausnummer'  => $data['hausnummer'],
        'plz'         => $data['plz'],
        'stadt'       => $data['stadt'],
        'land'        => $data['land'],
    ]
);

            // Erfolgreiche Rückmeldung an den Nutzer
            return redirect()->back()->with('success', 'Adresse erfolgreich hinzugefügt!');

        } catch (QueryException $e) {
            // Fehler bei der Datenbankabfrage (z. B. Verbindungsfehler, falsche Spalte)
            Log::error('Datenbankfehler beim Erstellen einer Adresse: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Datenbankfehler: Adresse konnte nicht gespeichert werden.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Fehler in der Validierung – Laravel behandelt das normalerweise automatisch,
            // aber falls du manuell abfangen willst:
            return redirect()->back()->withErrors($e->validator)->withInput();

        } catch (\Exception $e) {
            // Allgemeine Fehler (z. B. unerwartete Laufzeitfehler)
            Log::error('Allgemeiner Fehler beim Speichern der Adresse: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut.');
        }

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
