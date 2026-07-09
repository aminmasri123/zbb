<?php

namespace App\Http\Controllers;

use App\Models\Abschluesse;
use App\Models\Anwesenheitsstatuten;
use App\Models\Bereich;
use App\Models\BereichHasPersonen;
use App\Models\Brief;
use App\Models\Fahrtarten;
use App\Models\Gruppe;
use App\Models\Kontakttypen;
use App\Models\Leistungsbezuege;
use App\Models\Notizvarianten;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenHasSozialedaten;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\SozialeDaten;
use App\Models\Standort;
use App\Models\Teilnehmer;
use App\Models\User;
use App\Models\RoleDataAccessSetting;
use App\Notifications\ConfiguredEventNotification;
use App\Services\NotificationRecipientService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class TeilnehmerController extends Controller
{
    public function index(Request $request)
    {
        $suchbegriff = $request->input('search');
        $sortierung  = $request->input('sort', 'id');
        $richtung    = strtolower($request->input('direction', 'desc'));


        $benutzer = auth()->user();
        $projekte = $benutzer->projekte;
        $defaultProjekt = $benutzer->current_team_id;
        $standortId = $request->integer('standort') ?: null;
        $standorte = $defaultProjekt
            ? Standort::whereIn('id', ProjektHasPersonen::query()
                ->where('projekt_id', $defaultProjekt)
                ->whereNotNull('standort_id')
                ->select('standort_id')
            )->orderBy('name')->get()
            : collect();
        $gruppen = Gruppe::query()
            ->with('bereich')
            ->where('projekt_id', $benutzer->current_team_id)
            ->when(
                !$benutzer->can('gruppe.view.all') && !$benutzer->can('projekt.mitarbeiter.view.all'),
                fn ($query) => $query->where('personen_id', $this->userPersonId($benutzer))
            )
            ->orderBy('anfangsdatum')
            ->orderBy('startzeit')
            ->get();

        // Mögliche Sortierfelder
        $sortierbareSpalten = [
            'id'         => 'id',
            'vorname'    => 'vorname',
            'nachname'   => 'nachname',
            'geschlecht' => 'geschlecht',
        ];

        $sortierspalte = $sortierbareSpalten[$sortierung] ?? 'id';
        $richtung = in_array($richtung, ['asc', 'desc']) ? $richtung : 'desc';

        // Basis-Query (kein ->get() !!)
        $abfrage = Personen::query()
            ->teilnehmer()
            ->aktiv()
            ->visibleForUser($benutzer)   // <-- dein globaler Berechtigungsscope
            ->with(['projekte.abteilung', 'standorte']);

        if ($defaultProjekt) {
            $abfrage->whereHas('projekte', function ($query) use ($defaultProjekt, $standortId) {
                $query->where('projekt_has_personens.projekt_id', $defaultProjekt)
                    ->when($standortId, function ($query) use ($standortId) {
                        $query->where('projekt_has_personens.standort_id', $standortId);
                    });
            });
        } else {
            $abfrage->whereRaw('1 = 0');
        }

        // 🔍 Suche
        if ($suchbegriff) {
            $abfrage->where(function ($q) use ($suchbegriff) {
                $q->where(DB::raw("CONCAT(vorname, ' ', nachname)"), 'like', "%{$suchbegriff}%")
                ->orWhere(DB::raw("CONCAT(nachname, ' ', vorname)"), 'like', "%{$suchbegriff}%")
                ->orWhere('vorname', 'like', "%{$suchbegriff}%")
                ->orWhere('nachname', 'like', "%{$suchbegriff}%");
            });
        }


        // Sortieren
        $abfrage->orderBy($sortierspalte, $richtung);
        return Inertia::render('Teilnehmer/Index', [
            'teilnehmers' => $abfrage->paginate(50)->withQueryString(),
            'projekte' => $projekte,
            'standorte' => $standorte,
            'gruppen' => $gruppen,
            'defaultProjekt' => $defaultProjekt,
            'filters' => [
                'search'    => $suchbegriff,
                'standort'  => $standortId,
                'sort'      => $sortierung,
                'direction' => $richtung,
            ],
        ]);
    }


    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        try {

            // Daten validieren
            $validatedData = $request->validate([
                'vorname'   => ['required', 'max:50'],
                'nachname'  => ['required', 'max:50'],
                'geschlecht'=> ['required', 'in:m,w,d'],
                'projekt'   => ['required', 'integer', 'exists:projekts,id'],
                'standort'  => ['required', 'integer', 'exists:standorts,id'],
            ]);

            // Teilnehmer erstellen
            $teilnehmer = Personen::create([
                'vorname'   => $validatedData['vorname'],
                'nachname'  => $validatedData['nachname'],
                'geschlecht'=> $validatedData['geschlecht'],
                'typ'       => 'teilnehmer',
                'aktiv'=> 1,
            ]);

            // Sicherheitscheck
            if (!$teilnehmer) {
                return response()->json([
                    'message' => 'Fehler',
                    'errors'  => 'Teilnehmer konnte nicht erstellt werden'
                ], 422);
            }

            // Beziehung korrekt erstellen
           $teilnehmer->projekte()->attach(
                $validatedData['projekt'], // projekt_id
                [
                    'standort_id' => $validatedData['standort']
                ]
            );

            Notification::send(
                app(NotificationRecipientService::class)->forEvent('teilnehmer.created', [
                    'actor' => $request->user(),
                    'creator_user' => $request->user(),
                    'project_id' => $validatedData['projekt'],
                ]),
                new ConfiguredEventNotification([
                    'event_key' => 'teilnehmer.created',
                    'message' => 'Neuer Teilnehmer "' . $teilnehmer->vorname . ' ' . $teilnehmer->nachname . '" wurde erstellt.',
                    'link' => route('teilnehmer.edit', $teilnehmer->id),
                    'id' => $teilnehmer->id,
                    'typ' => 'Teilnehmer',
                ])
            );


            return response()->json([
                'message'    => 'Benutzer erfolgreich erstellt!',
                'teilnehmer' => $teilnehmer
            ], 201);

        } catch (ValidationException $e) {

            return response()->json([
                'message' => 'Validation Error',
                'errors'  => $e->errors(),
            ], 422);

        } catch (Exception $e) {

            return response()->json([
                'message' => 'Ein Fehler ist aufgetreten.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    public function show($id)
    {
        $user = auth()->user();

        $berechtigt = Personen::query()
            ->teilnehmer()
            ->visibleForUser($user)
            ->whereKey($id)
            ->exists();

        if(!$berechtigt) abort(403, "Sie sind nicht berechtigt, die Daten des Teilnehmers zu sehen.");

        $personen = personen::Teilnehmer()->with([
            'adresses',
            'anwesenheiten',
            'standorte',
            'gruppen',
            'gruppen.bereich',
            'kontaktes.kontakttyp',
            'praktika',
            'projekte',
            'baenke',
            'fahrtabrechnungen.fahrtarten',
            'fahrtabrechnungen.personal',
            'abschluesse',
            'sozialedaten',
            'notizen.notizkategorie',
            'notizen.notiztyp',
            'notizen.notizprioritaet',
            'notizen.user',
        ])->findOrFail($id);
        $personen->gruppen->each(function ($t) {
            $t->zeitgeplant = $t->pivot->zeitgeplant;
            $t->zeittatsaechlich = $t->pivot->zeittatsaechlich;
            $t->person = $t->pivot->person;
            $t->status = $t->pivot->status;
            $t->tag = $t->pivot->tag;
            $t->user = $t->pivot->user;
        });
        $personen->projekte->each(function ($projekt) {
            $projekt->pivotModel->load('zeitraume', 'meta', 'luv', 'standort');
        });
        //dd($personen);

        $arbeitsvermittler = Personen::arbeitsvermittler()->get();
        $bereiche = Bereich::all();
        $anwesenheitsstatuten =Anwesenheitsstatuten::all();
        $abschluesse = Abschluesse::all();


        //dd($personen);

        $notiztypen = Notizvarianten::where('typ', 'typ')->get();
        $notizkategorie = Notizvarianten::where('typ', 'kategorie')->get();
        $notizprioritaet = Notizvarianten::where('typ', 'prioritaet')->get();

        $fahrtarten = Fahrtarten::all();

        $leistungsbezuege = Leistungsbezuege::all();
        $erhalteneBriefe = auth()->user()->receivedFreigaben();

        $meineBriefe = auth()->user()->ownLetters();

        // Jetzt manuell umwandeln für Inertia:
         $teilnehmerData = $personen->toArray();

        //$betreuer = Personen::where('typ', 'mitarbeiter')->orderBy('nachname')->select('nachname', 'vorname')->get();
        $betreuer = Personen::mitarbeiter()
            ->whereHas('projekte', function($query) use ($personen) {
                $query->where('projekts.id', auth()->user()->current_team_id);
            })
            ->orderBy('nachname')
            ->select('nachname', 'vorname', 'id') // id hinzugefügt für Referenz
            ->get();

        $projekte = Projekt::orderBy('name')->get();
        $gruppen = Gruppe::where('projekt_id', Auth()->user()->current_team_id)->with('bereich', 'betreuer')->get();

        $aktuelleStandortIds = $personen->projekte
            ->pluck('pivotModel.standort_id')
            ->filter()
            ->unique()
            ->values();

        if (RoleDataAccessSetting::scopeForUser($user, 'participant') === 'all') {
            $standorte = Standort::orderBy('name')->get(['id', 'name']);
        } else {
            $userStandortIds = $user->standorte()->pluck('standorts.id');
            $standorte = Standort::whereIn(
                'id',
                $userStandortIds->merge($aktuelleStandortIds)->filter()->unique()->values()
            )
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $thisProjekt = Projekt::where('id', auth()->user()->current_team_id)->first();
        $dokumente = $thisProjekt?->dokumente;
        $kontakttypen = Kontakttypen::all();

        return Inertia::render('Teilnehmer/Edit', [
            'teilnehmer' => $personen->toArray(),
            'kontakttypen' => $kontakttypen,
            'projekte' => $projekte,
            'betreuer' => $betreuer,
            'erhalteneBriefe' => $erhalteneBriefe,
            'meineBriefe' => $meineBriefe,
            'anwesenheitsstatuten' => $anwesenheitsstatuten,
            'abschluesse' => $abschluesse,
            'leistungsbezuege' => $leistungsbezuege,
            'notiztypen' => $notiztypen,
            'notizkategorie' => $notizkategorie,
            'notizprioritaet' => $notizprioritaet,
            'fahrtarten' => $fahrtarten,
            'gruppen' => $gruppen,
            'standorte' => $standorte,
            'dokumente' => $dokumente,
            'bereiche' => $bereiche,
            'arbeitsvermittler' => $arbeitsvermittler,
            ],
        );
    }

    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        try {

            $validatedData = $request->validate([
                'vorname' => ['required', 'max:50'],
                'nachname' => ['required', 'max:50'],
                'geschlecht' => ['required', 'in:m,w,d'],
                'geburtsdatum' => ['nullable', 'date'],
                'bemerkungen' => ['nullable', 'string'],
            ]);
            $teilnehmer = Personen::findOrFail($id);
            $teilnehmer->update($validatedData);

            return back()->with('success', 'Teilnehmer wurde erfolgreich aktualisiert.');
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation Error', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Ein Fehler ist aufgetreten.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function updateSozialdaten(Request $request, $id)
    {
        // 1) Validierung
        $validated = $request->validate([
            'ist_drittstaatsangehoerig' => ['required', 'boolean'],
            'ist_gefluechtet'           => ['required', 'boolean'],
            'hat_migrationshintergrund' => ['required', 'boolean'],
            'hat_behinderung'           => ['required', 'boolean'],
            'leistungsbezug_id'         => ['nullable', 'exists:leistungsbezueges,id'],
            'ist_wohnsitz_stabil'       => ['required', 'boolean'],
            'teilnehmer_id'             => ['required', 'exists:personens,id'],
            'kundennummer'              => ['string', 'nullable'],
        ]);
        // 2) Speichern (create/update anhand person_id)
       PersonenHasSozialedaten::updateOrCreate(
            ['person_id' => $validated['teilnehmer_id']],
            [
            'wohnsitz_stabil' => $validated['ist_wohnsitz_stabil'],
            'leistungsbezug_id' => $validated['leistungsbezug_id'] ?? null,
            'behinderung' => $validated['hat_behinderung']  ,
            'migrationshintergrund' => $validated['hat_migrationshintergrund'],
            'gefluechtet' => $validated['ist_gefluechtet'],
            'drittstaatsangehoerig' => $validated['ist_drittstaatsangehoerig'],
            'kundennummer' =>  $validated['kundennummer'],
            ]
        );

        // ↙️ hier kommt die Swal-Nachricht rein
        return back()->with('swal', [
            'icon'  => 'success',
            'title' => 'Gespeichert',
            'text'  => 'Die Sozialdaten wurden erfolgreich gespeichert.',
            'timer' => 1600,
            'showConfirmButton' => false,
        ]);

    }

    public function destroy($id)
    {
        try {
            $teilnehmer = Personen::findOrFail($id); // Suche die Abteilung
            $teilnehmer->delete(); // Lösche die Abteilung

            return response()->json(['message' => 'die Daten von ' . $teilnehmer->vorname . ' ' . $teilnehmer->nachname . ' wurde  erfolgreich gelöscht!'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Die Daten konnte nicht gefunden werden.'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:personens,id'],
        ]);

        try {
            $deleted = Personen::whereIn('id', $validated['ids'])
                ->where('typ', 'teilnehmer')
                ->delete();

            return response()->json([
                'message' => $deleted . ' Teilnehmer wurden erfolgreich geloescht.',
                'deleted' => $deleted,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }
       public function import(Request $request)
    {

        try {

            // Überprüfen, ob eine Datei hochgeladen wurde
            if (!$request->hasFile('file')) {
                return response()->json(['error' => true, 'message' => 'Es wurde keine Datei hochgeladen.']);
            }

            $file = $request->file('file');
            if (!$file->isValid()) {
                return response()->json(['error' => true, 'message' => 'Fehler beim Hochladen der Datei.']);
            }

            try {
                $spreadsheet = IOFactory::load($file->getRealPath());
            } catch (Exception $e) {
                Log::error("Excel konnte nicht geladen werden: " . $e->getMessage());
                return response()->json(['error' => true, 'message' => 'Die Datei konnte nicht gelesen werden.']);
            }

            $worksheet = $spreadsheet->getActiveSheet();

            $importTyp = strtolower((string) $this->cleanImportValue($worksheet->getCell('B2')->getCalculatedValue()));
            $isBopImport = in_array($importTyp, ['bop', 'berufsorientierungsprogramm'], true);

            $data = [];
            $headerFound = false;
            $emptyRowCount = 0; // Zähler für aufeinanderfolgende leere Zeilen

            foreach ($worksheet->getRowIterator() as $row) {
                // Zellen einlesen
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }

                if (!$headerFound) {
                    $firstColumn = strtolower((string) $this->cleanImportValue($rowData[0] ?? null));
                    $secondColumn = strtolower((string) $this->cleanImportValue($rowData[1] ?? null));

                    if ($firstColumn === 'vorname' && $secondColumn === 'nachname') {
                        $headerFound = true;
                    }

                    continue;
                }
                // Prüfen, ob die Zeile komplett leer ist
                if (count(array_filter($rowData)) === 0) {
                    $emptyRowCount++;
                    if ($emptyRowCount >= 3) {
                        Log::info("Import beendet nach " . $emptyRowCount . " aufeinanderfolgenden leeren Zeilen.");
                        break; // Import abbrechen
                    }
                    continue; // Leere Zeile überspringen
                } else {
                    $emptyRowCount = 0; // Reset, sobald wieder eine gefüllte Zeile gefunden wurde
                }

                $data[] = [
                    'row_number' => $row->getRowIndex(),
                    'values' => $rowData,
                ];
            }
           // Log::info('Importierte Zeilen:', $data);
            if (!$headerFound) {
                return response()->json([
                    'error' => true,
                    'message' => 'Die Kopfzeile wurde nicht gefunden. Erwartet wird eine Zeile mit Vorname und Nachname.',
                ]);
            }

            $createdCount = 0;
            $errors = [];
            $validRows = [];

            foreach ($data as $index => $entry) {
                try {
                    $row = $entry['values'];
                    $rowNumber = $entry['row_number'];
                    $schuleId = $this->cleanImportValue($row[6] ?? null);
                    $schuljahr = $this->cleanImportValue($row[7] ?? null);
                    $teil = $this->cleanImportValue($row[8] ?? null);
                    $klasse = $this->cleanImportValue($row[9] ?? null);
                    $projektId = $this->cleanImportValue($row[4] ?? null);
                    $standortId = $this->cleanImportValue($row[5] ?? null);

                    if (!$isBopImport) {
                        $schuleId = null;
                        $schuljahr = null;
                        $teil = null;
                        $klasse = null;
                    }

                    if ($isBopImport && (empty($schuleId) || empty($schuljahr) || empty($teil) || empty($klasse))) {
                        $errors[] = "Zeile " . $rowNumber . " ist BOP, aber Schule_ID, Schuljahr, Teil oder Klasse fehlt.";
                        continue;
                    }

                    if ($projektId && !Projekt::whereKey($projektId)->exists()) {
                        $errors[] = "Zeile " . $rowNumber . ": Projekt_ID " . $projektId . " existiert nicht.";
                        continue;
                    }

                    if ($standortId && !Standort::whereKey($standortId)->exists()) {
                        $errors[] = "Zeile " . $rowNumber . ": Standort_ID " . $standortId . " existiert nicht.";
                        continue;
                    }

                    if ($isBopImport && !Partner::whereKey($schuleId)->exists()) {
                        $errors[] = "Zeile " . $rowNumber . ": Schule_id " . $schuleId . " existiert nicht.";
                        continue;
                    }

                    $teilnehmerData = [
                        'vorname' => $row[0] ?? null,
                        'nachname' => $row[1] ?? null,
                        'geschlecht' => match (strtolower(trim((string) ($row[2] ?? '')))) {
                            'männlich', 'maennlich', 'mannlich', 'm' => 'm',
                            'weiblich', 'w' => 'w',
                            'divers', 'd' => 'd',
                            default => null,
                        },
                        'geburtsdatum' => $this->parseImportDate($row[3] ?? null),
                        'aktiv' => 1,
                        'typ' => 'teilnehmer',
                    ];

                    if (empty($teilnehmerData['vorname']) || empty($teilnehmerData['nachname'])) {
                        $errors[] = "Zeile " . $rowNumber . " fehlt Vorname oder Nachname.";
                        continue;
                    }

                    $validRows[] = [
                        'row' => $row,
                        'teilnehmerData' => $teilnehmerData,
                        'projektId' => $projektId,
                        'standortId' => $standortId,
                        'schuleId' => $schuleId,
                        'schuljahr' => $schuljahr,
                        'teil' => $teil,
                        'klasse' => $klasse,
                    ];

                    continue;

                    /* if (count($row) < 8) {
                        $errors[] = "Zeile " . ($index + 2) . " hat zu wenige Spalten.";
                        continue;
                    } */

                    $teilnehmerData = [
                        'vorname'        => $row[0] ?? null,
                        'nachname'       => $row[1] ?? null,
                        'geschlecht'     => match (strtolower(trim($row[2] ?? ''))) {
                            'männlich'  => 'm',
                            'weiblich'  => 'w',
                            'divers'    => 'd',
                            'm'  => 'm',
                            'w'  => 'w',
                            'd'    => 'd',
                            default     => null,
                        }, 
                        'geburtsdatum'   => !empty($row[3]) ? Date::excelToDateTimeObject($row[3])->format('Y-m-d') : null,
                        'aktiv'         => 1,
                        'typ'           => 'teilnehmer',

                        
                        /* 'klasse'         => $row[3] ?? null,
                        'schule_id'      => $row[4] ?? null,
                        'foerderschueler'=> match (strtolower(trim($row[5] ?? ''))) {
                            'ja' => 1,
                            'nein' => 0,
                            '1' => 1,
                            '0' => 0,
                            default => 0,
                        },
                        'schuljahr'      => $row[7] ?? date('Y'),
                        'adresse'        => $row[8] ?? null,
                        'teil'           => $row[9] ?? '1', */
                    ];

                    if (empty($teilnehmerData['vorname']) || empty($teilnehmerData['nachname'])) {
                        $errors[] = "Zeile " . ($index + 2) . " fehlt Vorname oder Nachname.";
                        continue;
                    }

                    $teilnehmer = Personen::create($teilnehmerData);


                    if ($teilnehmer) {
                        // Projekt zuordnen
                        if (!empty($row[4])) {

                            $teilnehmer->projekte()->attach(
                                $row[4],
                                [
                                    'standort_id' => $row[5] ?? null
                                ]
                            );
                        }

                        $createdCount++;
                    } else {
                        $errors[] = "Zeile " . ($index + 2) . " konnte nicht gespeichert werden.";
                    }

                } catch (Exception $e) {
                    $errors[] = "Fehler in Zeile " . ($rowNumber ?? ($index + 2)) . ": " . $e->getMessage();
                    Log::error("Import Fehler Zeile " . ($rowNumber ?? ($index + 2)) . ": " . $e->getMessage());
                }
            }

            if (!empty($errors)) {
                Log::info('Import abgebrochen wegen Validierungsfehlern:', $errors);

                return response()->json([
                    'error' => true,
                    'message' => 'Import abgebrochen. Bitte korrigiere die Fehler in der Excel-Datei.',
                    'errors' => $errors,
                ], 422);
            }

            $createdCount = DB::transaction(function () use ($validRows) {
                $createdCount = 0;

                foreach ($validRows as $validRow) {
                    $teilnehmer = Personen::create($validRow['teilnehmerData']);

                    if ($validRow['projektId']) {
                        $teilnehmer->projekte()->attach(
                            $validRow['projektId'],
                            [
                                'standort_id' => $validRow['standortId']
                            ]
                        );
                    }

                    if ($validRow['schuleId']) {
                        PersonenIstSchueler::create([
                            'person_id' => $teilnehmer->id,
                            'klasse' => $validRow['klasse'],
                            'schule_id' => $validRow['schuleId'],
                            'foerderschueler' => $this->parseImportBoolean($validRow['row'][10] ?? null),
                            'eee' => $this->parseImportBoolean($validRow['row'][11] ?? null),
                            'schuljahr' => $validRow['schuljahr'],
                            'teil' => $validRow['teil'],
                        ]);
                    }

                    $createdCount++;
                }

                return $createdCount;
            });

             Log::info('Importierte Zeilen:', $errors);
            if ($createdCount > 0) {
               /*  $rollen = Role::whereIn('name', ['Administrator', 'Abteilungsleiter', 'Anleiter'])->get();
                foreach ($rollen as $role) {
                    foreach ($role->users as $user) {
                        $user->notify(new ImportTeilnehmerNotification($createdCount));
                    }
                } */

                return response()->json([
                    'success' => true,
                    'message' => "Import erfolgreich: $createdCount Teilnehmer angelegt.",
                    'errors'  => $errors,
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Kein Teilnehmer konnte importiert werden.',
                    'errors' => $errors,
                ]);
            }

        } catch (Exception $e) {
            Log::error("Allgemeiner Importfehler: " . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Ein unerwarteter Fehler ist aufgetreten.']);
        }
    }

    private function cleanImportValue($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function parseImportBoolean($value): bool
    {
        $value = strtolower(trim((string) ($value ?? '')));

        return match ($value) {
            '1', 'ja', 'j', 'yes', 'true', 'wahr' => true,
            default => false,
        };
    }

    private function parseImportDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $value = trim((string) $value);
        $formats = ['d.m.Y', 'Y-m-d', 'd/m/Y'];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);

            if ($date instanceof \DateTime) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function userPersonId($user): ?int
    {
        return $user?->person_id ?? $user?->person?->id;
    }

}
