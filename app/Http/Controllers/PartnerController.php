<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Adresse;
use App\Models\Kontakte;
use App\Models\Kontakttypen;
use App\Models\Partner;
use App\Models\PartnerHasPartnerschaftstypen;
use App\Models\Partnerschaftstypen;
use App\Models\Personen;
use App\Models\Projekt;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PartnerController extends Controller
{
    public function index(Request $request)
    {
        $kontaktypens = Kontakttypen::all();
        $search = $request->input('search');

        $user = auth()->user();
        $userProjektAktiv = $user->current_team_id;
        $projekt = Projekt::find($userProjektAktiv)->with('bereiche')->first();
        $projektName = $projekt?->name;
        $anzahlBereiche = $projekt?->bereiche->count();
        $partnerschaftstypen = Partnerschaftstypen::all();


        $partners = Partner::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->with([
                'partnerschaftstypens',
                'ansprechpartners',
                'ansprechpartners.partnerTyp',
                'ansprechpartners.adresses',
                'ansprechpartners.kontaktes',
                'ansprechpartners.kontaktes.kontakttyp',
                'schueler',
            ])
            ->join('partner_has_partnerschaftstypens', 'partners.id', '=', 'partner_has_partnerschaftstypens.partner_id')
            ->join('projekt_has_ansprechpartners', 'partner_has_partnerschaftstypens.id', '=', 'projekt_has_ansprechpartners.ansprechpartner_id')
            ->where('projekt_has_ansprechpartners.projekt_id', $userProjektAktiv)
            ->select('partners.*')
            ->distinct()
            ->orderBy('partners.id')

            ->paginate(20);


        return Inertia::render('Partner/Index', [
            'partners' => $partners,
            'partnerschaftstypen' => $partnerschaftstypen,
            'projektName' => $projektName,
            'kontaktypens' => $kontaktypens,
            'anzahlBereiche' => $anzahlBereiche
        ]);
    }
    public function indexAjaxFresh()
    {
        $user = auth()->user();
        $userProjektAktiv = $user->current_team_id;

       $partners = Partner::with([
        'partnerschaftstypens',
        'ansprechpartners',
        'ansprechpartners.partnerTyp',
        'ansprechpartners.adresses',
        'ansprechpartners.kontaktes',
        'ansprechpartners.kontaktes.kontakttyp',
            ])
            ->join('partner_has_partnerschaftstypens', 'partners.id', '=', 'partner_has_partnerschaftstypens.partner_id')
            ->join('projekt_has_ansprechpartners', 'partner_has_partnerschaftstypens.id', '=', 'projekt_has_ansprechpartners.ansprechpartner_id')
            ->where('projekt_has_ansprechpartners.projekt_id', $userProjektAktiv)
            ->select('partners.*')
             ->distinct()
            ->orderBy('id')
            ->paginate(20);

        return response()->json([
            'partners' => $partners
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

              // ✅ Adresse optional
            'ansprechpartner.*.adresse.strasse'     => 'nullable|string|max:255',
            'ansprechpartner.*.adresse.hausnummer'  => 'nullable|string|max:10',
            'ansprechpartner.*.adresse.plz'         => 'nullable|string|max:10',
            'ansprechpartner.*.adresse.stadt'       => 'nullable|string|max:255',
            'ansprechpartner.*.adresse.land'        => 'nullable|string|max:255',
            'ansprechpartner.*.adresse.zusatzinfo'  => 'nullable|string|max:255',


            // ✅ Neue Kontakte
            'ansprechpartner.*.email' => 'nullable|email|max:255',
            'ansprechpartner.*.tel'   => 'nullable|string|max:50',
            'ansprechpartner.*.handy' => 'nullable|string|max:50',
        ]);

        $user = auth()->user();
        $userProjektAktiv = $user->current_team_id;



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

                // ✅ Optional Adresse speichern
                if (!empty($personData['adresse'])) {
                    $adresseData = $personData['adresse'];
                    Adresse::create([
                        'model_type' => Personen::class,
                        'model_id'   => $person->id,
                        'strasse'    => $adresseData['strasse'] ?? null,
                        'hausnummer' => $adresseData['hausnummer'] ?? null,
                        'plz'        => $adresseData['plz'] ?? null,
                        'stadt'      => $adresseData['stadt'] ?? null,
                        'land'       => $adresseData['land'] ?? 'Deutschland',
                        'zusatzinfo' => $adresseData['zusatzinfo'] ?? null,
                    ]);
                }

                    $emailTyp = Kontakttypen::where('name', 'Email')->first()->id;
                    $telefonTyp = Kontakttypen::where('name', 'Telefon')->first()->id;
                    $handyTyp = Kontakttypen::where('name', 'Mobile')->first()->id;


                    if (!empty($personData['email'])) {
                        Kontakte::create([
                            'model_type' => Personen::class,
                            'model_id' => $person->id,
                            'kontakttyp_id' => $emailTyp,
                            'wert' => $personData['email']
                        ]);
                    }

                    if (!empty($personData['tel'])) {
                        Kontakte::create([
                            'model_type' => Personen::class,
                            'model_id' => $person->id,
                            'kontakttyp_id' => $telefonTyp,
                            'wert' => $personData['tel']
                        ]);
                    }

                    if (!empty($personData['handy'])) {
                        Kontakte::create([
                            'model_type' => Personen::class,
                            'model_id' => $person->id,
                            'kontakttyp_id' => $handyTyp,
                            'wert' => $personData['handy']
                        ]);
                     }
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

        // 4️⃣ Pivot-Tabelle projekt_has_ansprechpartners füllen
        if (!empty($data['ansprechpartner'])) {
            foreach ($data['ansprechpartner'] as $personData) {
                $person = Personen::where('vorname', $personData['vorname'])
                            ->where('nachname', $personData['nachname'])
                            ->first(); // bereits angelegt oben

                // jedem Typ die Beziehung zum Projekt speichern
                foreach ($data['typ'] as $typId) {

                    // Ansprechpartner ID aus PartnerHasPartnerschaftstypen holen
                    $ansprechpartnerPivot = PartnerHasPartnerschaftstypen::where('partner_id', $partner->id)
                                            ->where('partnerschaftstypen_id', $typId)
                                            ->first();

                    if ($ansprechpartnerPivot) {
                        DB::table('projekt_has_ansprechpartners')->insert([
                            'projekt_id' => $userProjektAktiv, // oder $projekt->id, falls Projekt separat angelegt
                            'ansprechpartner_id' => $ansprechpartnerPivot->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

       return response()->json([
            'success' => true,
            'partner' => $partner
                ->refresh()   // <<< WICHTIG!
                ->load([
                    'partnerschaftstypens',
                    'ansprechpartners',
                     'ansprechpartners.adresses'
                ])
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
            'name' => 'required|string|max:255',
            'beschreibung' => 'nullable|string',

            'typ' => 'required|array',
            'typ.*' => 'integer|exists:partnerschaftstypens,id',

            'ansprechpartner' => 'nullable|array',
            'ansprechpartner.*.id' => 'nullable|integer',
            'ansprechpartner.*.vorname' => 'required|string|max:100',
            'ansprechpartner.*.nachname' => 'required|string|max:100',
            'ansprechpartner.*.geschlecht' => 'nullable|string',
            'ansprechpartner.*.typ' => 'nullable|string|max:255',

            'ansprechpartner.*.adresse' => 'nullable|array',
            'ansprechpartner.*.adresse.strasse' => 'nullable|string|max:255',
            'ansprechpartner.*.adresse.hausnummer' => 'nullable|string|max:20',
            'ansprechpartner.*.adresse.plz' => 'nullable|string|max:20',
            'ansprechpartner.*.adresse.stadt' => 'nullable|string|max:255',

            'ansprechpartner.*.kontakte' => 'nullable|array',
            'ansprechpartner.*.kontakte.*.kontakttyp_id' => 'nullable|integer',
            'ansprechpartner.*.kontakte.*.wert' => 'nullable|string|max:255',
            'ansprechpartner.*.kontakte.*.bemerkung' => 'nullable|string|max:255',
        ]);

        $partner = Partner::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | 1 Partner aktualisieren
        |--------------------------------------------------------------------------
        */

        $partner->update([
            'name' => $data['name'],
            'beschreibung' => $data['beschreibung'] ?? null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2 Ansprechpartner speichern oder aktualisieren
        |--------------------------------------------------------------------------
        */

        if (!empty($data['ansprechpartner'])) {

            foreach ($data['ansprechpartner'] as $personData) {

    $geschlecht = strtolower($personData['geschlecht'] ?? '');
    $geschlecht = match ($geschlecht) {
        'männlich','m' => 'm',
        'weiblich','w' => 'w',
        'divers','d' => 'd',
        default => null
    };

    $person = Personen::updateOrCreate(
        ['id' => $personData['id'] ?? null],
        [
            'vorname' => $personData['vorname'],
            'nachname' => $personData['nachname'],
            'geschlecht' => $geschlecht,
            'typ' => 'ansprechpartner'
        ]
    );

    // Pivot Partner + Typ
    foreach ($data['typ'] as $typId) {
        PartnerHasPartnerschaftstypen::updateOrCreate(
            [
                'partner_id' => $partner->id,
                'partnerschaftstypen_id' => $typId,
                'ansprechpartner_id' => $person->id
            ],
            ['rolle' => $personData['typ'] ?? null]
        );
    }

    // Adresse
    if (!empty($personData['adresse'])) {
       $person->adresses()->updateOrCreate(
            [], // Bedingung leer, weil morphMany automatisch filtert auf model_id & model_type
            [
                'strasse' => $personData['adresse']['strasse'] ?? null,
                'hausnummer' => $personData['adresse']['hausnummer'] ?? null,
                'plz' => $personData['adresse']['plz'] ?? null,
                'stadt' => $personData['adresse']['stadt'] ?? null,
            ]
        );
    }

    // Kontakte
    if (!empty($personData['kontakte'])) {
        $person->kontaktes()->delete(); // alte löschen
            foreach ($personData['kontakte'] as $kontakt) {
                if (!empty($kontakt['wert'])) {
                    $person->kontaktes()->create([
                        'personen_id' => $person->id,
                        'kontakttyp_id' => $kontakt['kontakttyp_id'] ?? null,
                        'wert' => $kontakt['wert'],
                        'bemerkung' => $kontakt['bemerkung'] ?? null
                    ]);
                }
            }
    }
}
        }

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'partner' => $partner
            ->refresh()->load([
                'partnerschaftstypens',
                'ansprechpartners',
                'ansprechpartners.adresses',
                'ansprechpartners.kontaktes',
            ])
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $partner = Partner::findOrFail($id);

            // Lösche alle Ansprechpartner + deren Daten
            foreach ($partner->ansprechpartners as $person) {
                $person->delete(); // Adresse und Kontakte werden automatisch gelöscht
            }

            // Partner selbst löschen
            $partner->delete();

            return response()->json(['message' => 'Partner erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Partner nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }

}
