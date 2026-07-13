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
use App\Models\ProjektHasPartner;
use App\Models\User;
use App\Services\Projects\ActiveProjectContext;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PartnerController extends Controller
{
    public function __construct(private readonly ActiveProjectContext $activeProjectContext)
    {
    }

    private function activeProjectId(User $user): int
    {
        $project = $this->activeProjectContext->currentAvailableFor($user);
        abort_unless($project, 409, 'Bitte wählen Sie zuerst ein aktives Projekt aus.');

        return $project->id;
    }

    private function projektPartnerPivotIds($projektId)
    {
        return DB::table('projekt_has_ansprechpartners')
            ->where('projekt_id', $projektId)
            ->pluck('ansprechpartner_id');
    }

    private function partnerRelationsForProject($projektId)
    {
        $pivotIds = $this->projektPartnerPivotIds($projektId);

        return [
            'partnerschaftstypens' => function ($query) use ($pivotIds) {
                $query->whereIn('partner_has_partnerschaftstypens.id', $pivotIds);
            },
            'ansprechpartners' => function ($query) use ($pivotIds) {
                $query->whereIn('partner_has_partnerschaftstypens.id', $pivotIds);
            },
            'ansprechpartners.partnerTyp',
            'ansprechpartners.adresses',
            'ansprechpartners.kontaktes',
            'ansprechpartners.kontaktes.kontakttyp',
            'schueler',
        ];
    }

    private function applyPartnerSearch($query, $search)
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        $term = "%{$search}%";

        return $query->where(function ($query) use ($term) {
            $query->where('partners.name', 'like', $term)
                ->orWhere('partners.beschreibung', 'like', $term)
                ->orWhereHas('partnerschaftstypens', function ($typeQuery) use ($term) {
                    $typeQuery->where('bezeichnung', 'like', $term)
                        ->orWhere('beschreibung', 'like', $term);
                })
                ->orWhereHas('ansprechpartners', function ($personQuery) use ($term) {
                    $personQuery->where('vorname', 'like', $term)
                        ->orWhere('nachname', 'like', $term)
                        ->orWhereHas('partnerTyp', function ($typeQuery) use ($term) {
                            $typeQuery->where('bezeichnung', 'like', $term)
                                ->orWhere('beschreibung', 'like', $term);
                        })
                        ->orWhereHas('adresses', function ($addressQuery) use ($term) {
                            $addressQuery->where('strasse', 'like', $term)
                                ->orWhere('hausnummer', 'like', $term)
                                ->orWhere('plz', 'like', $term)
                                ->orWhere('stadt', 'like', $term)
                                ->orWhere('land', 'like', $term)
                                ->orWhere('zusatzinfo', 'like', $term);
                        })
                        ->orWhereHas('kontaktes', function ($contactQuery) use ($term) {
                            $contactQuery->where('wert', 'like', $term)
                                ->orWhere('bemerkung', 'like', $term)
                                ->orWhereHas('kontakttyp', function ($contactTypeQuery) use ($term) {
                                    $contactTypeQuery->where('name', 'like', $term);
                                });
                        });
                });
        });
    }

    public function index(Request $request)
    {
        $kontaktypens = Kontakttypen::all();
        $search = $request->input('search');

        $user = auth()->user();
        $userProjektAktiv = $this->activeProjectId($user);
        /* $projekt = Projekt::find($userProjektAktiv)->with('bereiche')->first(); */
        $projekt = Projekt::with('bereiche')->find($userProjektAktiv);
        $projektName = $projekt?->name;
        $anzahlBereiche = $projekt?->bereiche->count();
        $partnerschaftstypen = Partnerschaftstypen::all();

        $partners = $this->applyPartnerSearch(Partner::query(), $search)
            ->with($this->partnerRelationsForProject($userProjektAktiv))
            ->join('projekt_has_partners', 'partners.id', '=', 'projekt_has_partners.partner_id')
            ->where('projekt_has_partners.projekt_id', $userProjektAktiv)
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
    public function indexAjaxFresh(Request $request)
    {
        $user = auth()->user();
        $userProjektAktiv = $this->activeProjectId($user);
        $search = $request->input('search');

       $partners = $this->applyPartnerSearch(Partner::query(), $search)
            ->with($this->partnerRelationsForProject($userProjektAktiv))
            ->join('projekt_has_partners', 'partners.id', '=', 'projekt_has_partners.partner_id')
            ->where('projekt_has_partners.projekt_id', $userProjektAktiv)
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
        $userProjektAktiv = $this->activeProjectId($user);



        // 1️⃣ Partner anlegen
        $partner = Partner::create([
            'name' => $data['name'],
            'beschreibung' => $data['beschreibung'] ?? null
        ]);

        ProjektHasPartner::updateOrCreate([
            'projekt_id' => $userProjektAktiv,
            'partner_id' => $partner->id,
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
                            'partnerschaftstypen_id' => $typId,
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
                ->load($this->partnerRelationsForProject($userProjektAktiv))
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

        $user = auth()->user();
        $userProjektAktiv = $this->activeProjectId($user);
        $assignedPivotIds = [];
        $assignedPersonIds = [];

        ProjektHasPartner::updateOrCreate([
            'projekt_id' => $userProjektAktiv,
            'partner_id' => $partner->id,
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

    $assignedPersonIds[] = $person->id;

    // Pivot Partner + Typ
    foreach ($data['typ'] as $typId) {
        $pivot = PartnerHasPartnerschaftstypen::updateOrCreate(
            [
                'partner_id' => $partner->id,
                'partnerschaftstypen_id' => $typId,
                'ansprechpartner_id' => $person->id
            ],
            ['rolle' => $personData['typ'] ?? null]
        );

        $assignedPivotIds[] = $pivot->id;

        DB::table('projekt_has_ansprechpartners')->updateOrInsert(
            [
                'projekt_id' => $userProjektAktiv,
                'ansprechpartner_id' => $pivot->id,
            ],
            [
                'partnerschaftstypen_id' => $typId,
                'updated_at' => now(),
                'created_at' => now(),
            ]
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

        $currentProjectPivotIds = DB::table('projekt_has_ansprechpartners')
            ->join('partner_has_partnerschaftstypens', 'projekt_has_ansprechpartners.ansprechpartner_id', '=', 'partner_has_partnerschaftstypens.id')
            ->where('projekt_has_ansprechpartners.projekt_id', $userProjektAktiv)
            ->where('partner_has_partnerschaftstypens.partner_id', $partner->id)
            ->pluck('partner_has_partnerschaftstypens.id');

        $obsoletePivotIds = $currentProjectPivotIds
            ->diff(array_unique($assignedPivotIds))
            ->values();

        DB::table('projekt_has_ansprechpartners')
            ->where('projekt_id', $userProjektAktiv)
            ->whereIn('ansprechpartner_id', $obsoletePivotIds)
            ->delete();

        PartnerHasPartnerschaftstypen::whereIn('id', $obsoletePivotIds)->delete();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'partner' => $partner
            ->refresh()->load($this->partnerRelationsForProject($userProjektAktiv))
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $partner = Partner::findOrFail($id);
            $user = auth()->user();
            $userProjektAktiv = $this->activeProjectId($user);

            $projectPivotIds = DB::table('projekt_has_ansprechpartners')
                ->join('partner_has_partnerschaftstypens', 'projekt_has_ansprechpartners.ansprechpartner_id', '=', 'partner_has_partnerschaftstypens.id')
                ->where('projekt_has_ansprechpartners.projekt_id', $userProjektAktiv)
                ->where('partner_has_partnerschaftstypens.partner_id', $partner->id)
                ->pluck('partner_has_partnerschaftstypens.id');

            $projectPersonIds = PartnerHasPartnerschaftstypen::whereIn('id', $projectPivotIds)
                ->pluck('ansprechpartner_id')
                ->filter()
                ->unique();

            DB::table('projekt_has_ansprechpartners')
                ->where('projekt_id', $userProjektAktiv)
                ->whereIn('ansprechpartner_id', $projectPivotIds)
                ->delete();

            PartnerHasPartnerschaftstypen::whereIn('id', $projectPivotIds)->delete();

            foreach ($projectPersonIds as $personId) {
                if (!PartnerHasPartnerschaftstypen::where('ansprechpartner_id', $personId)->exists()) {
                    Personen::where('id', $personId)->delete();
                }
            }

            ProjektHasPartner::where('projekt_id', $userProjektAktiv)
                ->where('partner_id', $partner->id)
                ->delete();

            if (ProjektHasPartner::where('partner_id', $partner->id)->exists()) {
                return response()->json(['message' => 'Partner erfolgreich aus diesem Projekt entfernt!'], 200);
            }

            $partner->delete();

            return response()->json(['message' => 'Partner erfolgreich geloescht!'], 200);

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
