<?php

namespace App\Http\Controllers;

use App\Models\Bereich;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;

class BereichController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $search = $request->input('search'); // Benutze input(), um den Suchparameter abzurufen

        // Hole die Abteilungen mit Suchfilter und lade die notwendigen Beziehungen
        $bereiche = Bereich::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name') // Sortiere nach Name
            ->paginate(20)    // Wende die Paginierung an
            ->withQueryString(); // Behalte die Query-String-Parameter für die Pagination

        // Standardmäßige Rückgabe für die Inertia-Ansicht
        return Inertia::render('Bereich/Index', [
            'bereiche' => $bereiche,
            'unterweisungThemen' => $this->unterweisungThemen(),
        ]);
    }

    public function indexAjaxFresh(Request $request)
    {
        $search = $request->input('search'); // Benutze input(), um den Suchparameter abzurufen

        // Hole die Abteilungen mit Suchfilter und lade die notwendigen Beziehungen
        $bereiche = Bereich::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name') // Sortiere nach Name
            ->paginate(20)    // Wende die Paginierung an
            ->withQueryString(); // Behalte die Query-String-Parameter für die Pagination

        // Überprüfe, ob die Anfrage als AJAX-Request gesendet wurde
        if ($request->ajax()) {
            return response()->json([
                'bereiche' => $bereiche,
            ]);
        }

        // Standardmäßige Rückgabe für die Inertia-Ansicht
        return Inertia::render('Bereich/Index', [
            'bereiche' => $bereiche,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        // Validierung
        $validatedData = $request->validate([
            'name' => 'required|string|max:30',
            'code' => 'nullable|string|max:10',
            'beschreibung' => 'nullable|string|max:200',
            'unterweisung_themen' => ['nullable', 'array'],
            'unterweisung_themen.*' => ['string', 'in:'.implode(',', array_keys(config('unterweisung.themen', [])))],
        ]);

        try {
            // Abteilung erstellen
            $bereich = Bereich::create([
                'name' => trim($validatedData['name']),
                'code' => $this->nullableTrimmedValue($validatedData['code'] ?? null),
                'beschreibung' => $this->nullableTrimmedValue($validatedData['beschreibung'] ?? null),
                'unterweisung_themen' => array_values(array_unique($validatedData['unterweisung_themen'] ?? [])),
            ]);

            // Ajax Automatisch anzeigen
            return response()->json([
                'message' => 'Bereich erfolgreich erstellt.',
                'bereich' => $bereich,
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
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:30',
            'code' => 'nullable|string|max:10',
            'beschreibung' => 'nullable|string|max:200',
            'unterweisung_themen' => ['nullable', 'array'],
            'unterweisung_themen.*' => ['string', 'in:'.implode(',', array_keys(config('unterweisung.themen', [])))],
        ]);

        $bereich = Bereich::findOrFail($id);
        $bereich->update([
            'name' => trim($validated['name']),
            'code' => $this->nullableTrimmedValue($validated['code'] ?? null),
            'beschreibung' => $this->nullableTrimmedValue($validated['beschreibung'] ?? null),
            'unterweisung_themen' => array_values(array_unique($validated['unterweisung_themen'] ?? [])),
        ]);

        return response()->json([
            'message' => 'Bereich erfolgreich aktualisiert',
            'bereich' => $bereich,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        try {
            $bereich = Bereich::findOrFail($id);

            // Optional: Überprüfe, ob die Abteilung gelöscht werden kann (z.B. durch Beziehungen)
            // if ($abteilung->hasRelations()) { ... }

            $bereich->delete(); // Lösche die Abteilung

            return response()->json(['message' => 'Abteilung erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Abteilung nicht gefunden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: '.$e->getMessage()], 500);
        }
    }

    private function nullableTrimmedValue(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function unterweisungThemen(): array
    {
        return collect(config('unterweisung.themen', []))
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
            ->values()
            ->all();
    }
}
