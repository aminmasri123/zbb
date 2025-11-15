<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Dienstwagen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Models\Dienstwagenwartungsaufzeichnungen;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DienstwagenwartungController extends Controller
{
   public function index()
    {
        return Inertia::render('Dienstwagen/Wartungen/Index', [
            'records'  => Dienstwagenwartungsaufzeichnungen::with('dienstwagen')
                            ->orderBy('datum', 'desc')
                            ->get(),

            'vehicles' => Dienstwagen::orderBy('kennzeichen')->get(),
        ]);
    }

    /**
     * Neuen Wartungseintrag speichern
     */
public function store(Request $request)
{
    try {
        // 🔹 Eingabedaten validieren
        $data = $request->validate([
            'dienstwagen_id'  => 'required|exists:dienstwagens,id',
            'art'             => 'required|string|max:255',
            'datum'           => 'required|date',
            'kilometerstand'  => 'required|integer|min:0',
            'werkstatt'       => 'nullable|string|max:255',
            'kosten'          => 'nullable|numeric|min:0',
            'notizen'         => 'nullable|string',
        ]);

        // 🔹 Datum korrigieren (ISO → MySQL-kompatibel)
        if (!empty($data['datum'])) {
            try {
                $data['datum'] = Carbon::parse($data['datum'])->format('Y-m-d');
            } catch (Exception $e) {
                throw new Exception("Ungültiges Datumsformat übermittelt: " . $data['datum']);
            }
        }

        // 🔹 Eintrag speichern
        Dienstwagenwartungsaufzeichnungen::create($data);

        // 🔹 Erfolgsmeldung an UI zurückgeben
        return back()->with('success', '✅ Wartungseintrag erfolgreich hinzugefügt.');
    }

    // 🔸 Datenbankfehler abfangen
    catch (QueryException $e) {
        Log::error('SQL-Fehler beim Speichern eines Wartungseintrags:', [
            'error' => $e->getMessage(),
            'data' => $request->all(),
        ]);

        return back()->with('error', '❌ Ein Datenbankfehler ist aufgetreten. Bitte erneut versuchen.');
    }

    // 🔸 Andere Fehler abfangen
    catch (Exception $e) {
        Log::error('Allgemeiner Fehler beim Speichern eines Wartungseintrags:', [
            'error' => $e->getMessage(),
            'data' => $request->all(),
        ]);

        return back()->with('error', '⚠️ ' . $e->getMessage());
    }
}

public function update(Request $request, $id)
{
    try {
        // 🔹 Eintrag abrufen
        $wartung = Dienstwagenwartungsaufzeichnungen::findOrFail($id);

        // 🔹 Eingabedaten validieren
        $data = $request->validate([
            'dienstwagen_id'  => 'required|exists:dienstwagens,id',
            'art'             => 'required|string|max:255',
            'datum'           => 'required|date',
            'kilometerstand'  => 'required|integer|min:0',
            'werkstatt'       => 'nullable|string|max:255',
            'kosten'          => 'nullable|numeric|min:0',
            'notizen'         => 'nullable|string',
        ]);

        // 🔹 Datum formatieren
        if (!empty($data['datum'])) {
            try {
                $data['datum'] = Carbon::parse($data['datum'])->format('Y-m-d');
            } catch (Exception $e) {
                throw new Exception("Ungültiges Datumsformat übermittelt: " . $data['datum']);
            }
        }

        // 🔹 Update durchführen
        $wartung->update($data);

        // 🔹 Aktualisierten Datensatz mit Relation zurückgeben
        $updated = Dienstwagenwartungsaufzeichnungen::with('dienstwagen')->find($id);

        return back()->with([
            'success' => '✅ Wartungseintrag erfolgreich aktualisiert.',
            'updated_record' => $updated
        ]);

    } catch (QueryException $e) {
        Log::error('SQL-Fehler beim Aktualisieren eines Wartungseintrags:', [
            'error' => $e->getMessage(),
            'id' => $id,
            'data' => $request->all(),
        ]);
        return back()->with('error', '❌ Datenbankfehler beim Aktualisieren.');
    } catch (Exception $e) {
        Log::error('Allgemeiner Fehler beim Aktualisieren eines Wartungseintrags:', [
            'error' => $e->getMessage(),
            'id' => $id,
            'data' => $request->all(),
        ]);
        return back()->with('error', '⚠️ ' . $e->getMessage());
    }
}


    /**
     * Wartungseintrag löschen
     */
    public function destroy($id)
    {
         try {
            $dienstwagenwartung = Dienstwagenwartungsaufzeichnungen::findOrFail($id);

            $dienstwagenwartung->delete(); // Lösche die Projekt

            return response()->json(['message' => 'Dienstwagen erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Dienstwagen nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
}
