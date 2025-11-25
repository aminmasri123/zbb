<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Partner;
use App\Models\Personen;
use Illuminate\Http\Request;
use App\Models\Partnerschaftstypen;
use App\Http\Controllers\Controller;
use App\Models\PartnerHasPartnerschaftstypen;

class PartnerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $search = $request->input('search'); // Benutze input(), um den Suchparameter abzurufen

        // Hole die Abteilungen mit Suchfilter und lade die notwendigen Beziehungen
        $partnerschaftstypen = Partnerschaftstypen::all();
        $partners = Partner::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('id')
            ->with('partnerschaftstypens')
            ->paginate(20)    // Wende die Paginierung an
            ->withQueryString(); // Behalte die Query-String-Parameter für die Pagination

            return Inertia::render('Partner/Index', [
            'partners' => $partners,
            'partnerschaftstypen' => $partnerschaftstypen,
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
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'beschreibung'      => 'nullable|string',
            'typ'               => 'required|array',      // partnerschaftstypen
            'typ.*'             => 'integer|exists:partnerschaftstypens,id',

            'ansprechpartner' => 'array',
            'ansprechpartner.*.vorname'     => 'required|string|max:100',
            'ansprechpartner.*.nachname'    => 'required|string|max:100',
            'ansprechpartner.*.geschlecht'  => 'nullable|string',
            'ansprechpartner.*.typ'         => 'nullable|string|max:255', // = Rolle/Funktion
        ]);

        // 1️⃣ Partner anlegen
        $partner = Partner::create([
            'name' => $data['name'],
            'beschreibung' => $data['beschreibung'] ?? null
        ]);

        // 2️⃣ Partnerschaftstypen zuordnen (Pivot ohne Ansprechpartner)
        foreach ($data['typ'] as $typId) {
            PartnerHasPartnerschaftstypen::create([
                'partner_id'             => $partner->id,
                'partnerschaftstypen_id' => $typId,
                'ansprechpartner_id'     => null,
                'rolle'                  => null,
            ]);
        }

        // 3️⃣ Ansprechpartner speichern & ihnen die Rollen pro Typ zuordnen
        if (!empty($data['ansprechpartner'])) {

            foreach ($data['ansprechpartner'] as $personData) {

                // Geschlecht normalisieren
                $geschlecht = strtolower($personData['geschlecht'] ?? '');
                $personData['geschlecht'] = match($geschlecht) {
                    'männlich' => 'm',
                    'weiblich' => 'w',
                    'divers'   => 'd',
                    default    => null,
                };

                // Person anlegen
                $person = Personen::create([
                    'vorname'    => $personData['vorname'],
                    'nachname'   => $personData['nachname'],
                    'geschlecht' => $personData['geschlecht'],
                    'typ'        => 'ansprechpartner'
                ]);

                // Ansprechpartner bekommt Rolle pro Typ
                foreach ($data['typ'] as $typId) {

                    PartnerHasPartnerschaftstypen::where('partner_id', $partner->id)
                        ->where('partnerschaftstypen_id', $typId)
                        ->update([
                            'ansprechpartner_id' => $person->id,
                            'rolle'              => $personData['typ'] ?? null
                        ]);
                }
            }
        }

       return response()->json([
    'success' => true,
    'partner' => $partner
        ->refresh()   // <<< WICHTIG!
        ->load([
            'partnerschaftstypens',
            'partnerschaftstypenZuordnung.ansprechpartner'
        ])
]);

    }



public function indexAjaxFresh()
{
    $partners = Partner::with([
        'partnerschaftstypens',
        'partnerschaftstypenZuordnung.ansprechpartner'
    ])
    ->orderBy('id')
    ->paginate(20);

    return response()->json([
        'partners' => $partners
    ]);
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
   public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'beschreibung'      => 'nullable|string',

            // Partnerschaftstypen (MultiSelect)
            'typ'               => 'required|array',
            'typ.*'             => 'integer|exists:partnerschaftstypens,id',

            // Ansprechpartner
            'ansprechpartner' => 'array',
            'ansprechpartner.*.vorname'     => 'required|string|max:100',
            'ansprechpartner.*.nachname'    => 'required|string|max:100',
            'ansprechpartner.*.geschlecht'  => 'nullable|string',
            'ansprechpartner.*.typ'         => 'nullable|string|max:255',
        ]);

        // 1️⃣ Partner laden
        $partner = Partner::findOrFail($id);

        // 2️⃣ Partner aktualisieren
        $partner->update([
            'name' => $data['name'],
            'beschreibung' => $data['beschreibung'] ?? null,
        ]);

        // 3️⃣ ALLE vorhandenen Zuordnungen löschen (Neuaufbau ist einfacher & sicherer)
        PartnerHasPartnerschaftstypen::where('partner_id', $partner->id)->delete();

        // 4️⃣ Partnerschaftstypen neu anlegen
        foreach ($data['typ'] as $typId) {
            PartnerHasPartnerschaftstypen::create([
                'partner_id'             => $partner->id,
                'partnerschaftstypen_id' => $typId,
                'ansprechpartner_id'     => null,
                'rolle'                  => null
            ]);
        }

        // 5️⃣ Ansprechpartner neu speichern + rollen zuordnen
        if (!empty($data['ansprechpartner'])) {

            foreach ($data['ansprechpartner'] as $personData) {

                // Geschlecht normalisieren
                $geschlecht = strtolower($personData['geschlecht'] ?? '');
                $personData['geschlecht'] = match($geschlecht) {
                    'männlich' => 'm',
                    'weiblich' => 'w',
                    'divers'   => 'd',
                    default    => null,
                };

                // Prüfen ob Person existiert → sonst neu erstellen
                $person = Personen::create([
                    'vorname'    => $personData['vorname'],
                    'nachname'   => $personData['nachname'],
                    'geschlecht' => $personData['geschlecht'],
                    'typ'        => 'ansprechpartner'
                ]);

                // Rolle auf alle ausgewählten Partnerschaftstypen anwenden
                foreach ($data['typ'] as $typId) {
                    PartnerHasPartnerschaftstypen::where('partner_id', $partner->id)
                        ->where('partnerschaftstypen_id', $typId)
                        ->update([
                            'ansprechpartner_id' => $person->id,
                            'rolle'              => $personData['typ'] ?? null,
                        ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'partner' => $partner->load([
                'partnerschaftstypens',
                'partnerschaftstypenZuordnung.ansprechpartner'
            ])
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
