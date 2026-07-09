<?php

namespace App\Http\Controllers;

use App\Models\Geraet;
use App\Notifications\ConfiguredEventNotification;
use App\Services\NotificationRecipientService;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GeraetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
{
    $geraete = Geraet::latest()->get();

    return Inertia::render('Geraet/Index', [
        'geraete' => $geraete
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
        $validated = $request->validate([
            'sn' => 'required|unique:geraets,sn',
            'produkt_id' => 'required',
            'zustand'=> 'required',
            'geraet'=> 'required',
            'hersteller'=> 'nullable',
            'modell'=> 'nullable',
            'imLager'=> 'nullable',
            'baujahr'=> 'nullable',
            'garantiefrist'=> 'nullable'
        ]);

        // Garantieprüfung
        if ($request->baujahr && $request->garantiefrist && $request->baujahr > $request->garantiefrist) {
            return back()->withErrors([
                'garantiefrist' => 'Die Garantiefrist kann nicht vor dem Baujahr liegen.'
            ]);
        }

        $geraet = new Geraet();

        $geraet->sn = $request->sn;
        $geraet->productID = $request->produkt_id;
        $geraet->zustand = $request->zustand;
        $geraet->geraet = $request->geraet;
        $geraet->imLager = $request->imLager;
        $geraet->hersteller = $request->hersteller;
        $geraet->modell = $request->modell;

       if ($request->baujahr) {
             $geraet->baujahr = Carbon::parse($request->baujahr)->format('Y-m-d');
        }

        if ($request->garantiefrist) {
            $geraet->garantiefrist = Carbon::parse($request->garantiefrist)->format('Y-m-d');
        }
        $geraet->save();

        Notification::send(
            app(NotificationRecipientService::class)->forEvent('geraet.created', [
                'actor' => $request->user(),
                'creator_user' => $request->user(),
            ]),
            new ConfiguredEventNotification([
                'event_key' => 'geraet.created',
                'message' => 'Neues Gerät mit der SN "' . $geraet->sn . '" wurde angelegt.',
                'link' => route('geraet.index'),
                'id' => $geraet->id,
                'typ' => 'Gerät',
            ])
        );

        /*
        |-----------------------------
        | Notifications
        |-----------------------------
        */
        /*
        $roles = Role::whereIn('name', ['Administrator', 'IT-Administrator'])
            ->with('users')
            ->get();

        foreach ($roles as $role) {
            foreach ($role->users as $user) {
                $user->notify(new CreateGeraetNotification($geraet->sn));
            }
        } */

        return redirect()
            ->route('geraet.index')
            ->with('success', 'Das Gerät mit der SN ' . $geraet->sn . ' wurde angelegt.');
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
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

            $data = [];
            $skipFirstRow = true;
            $emptyRowCount = 0; // Zähler für aufeinanderfolgende leere Zeilen

            foreach ($worksheet->getRowIterator() as $row) {
                if ($skipFirstRow) {
                    $skipFirstRow = false;
                    continue;
                }

                // Zellen einlesen
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
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

                $data[] = $rowData;
            }
           // Log::info('Importierte Zeilen:', $data);
            $createdCount = 0;
            $errors = [];

            foreach ($data as $index => $row) {
                try {
                    /* if (count($row) < 8) {
                        $errors[] = "Zeile " . ($index + 2) . " hat zu wenige Spalten.";
                        continue;
                    } */

                    $geraetData = [
                        'sn'        => $row[1] ?? null,
                        'productID'       => $row[1] ?? null,
                        'zustand'       => $row[2] ?? null,
                        'geraet'       => $row[3] ?? null,
                        'hersteller'       => $row[4] ?? null,
                        'modell'       => $row[5] ?? null,
                    ];

                    if (empty($geraetData['sn']) || empty($geraetData['productID']) || empty($geraetData['geraet'])) {
                        $errors[] = "Zeile " . ($index + 2) . " fehlt Seriennummer, typ des Gerätes oder Product ID.";
                        continue;
                    }

                    $geraet = Geraet::create($geraetData);


                } catch (Exception $e) {
                    $errors[] = "Fehler in Zeile " . ($index + 2) . ": " . $e->getMessage();
                    Log::error("Import Fehler Zeile " . ($index + 2) . ": " . $e->getMessage());
                }
            }
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
}
