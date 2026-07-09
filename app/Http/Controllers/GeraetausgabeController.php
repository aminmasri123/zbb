<?php

namespace App\Http\Controllers;

use App\Models\Geraet;
use App\Models\Geraetausgabe;
use App\Models\GeraetHasAusgabe;
use App\Models\Personen;
use App\Models\Projekt;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GeraetausgabeController extends Controller
{
    public function index()
    {
        return Inertia::render('Geraet/Ausgabe/Index', [
            'ausgaben' => Geraetausgabe::with(['ausleiher','projekte', 'projekte.kostenstellen' ,'geraete'])->get(),
            'ausleiher' => Personen::where('typ', 'mitarbeiter')->select('id', 'nachname', 'vorname')->get(),
            'projekte' => Projekt::all(),
            'geraete' => Geraet::where('verfuegbarkeit', true)->get(),
            //'geraete' => Geraet::whereNull('ausgabe_id')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function view($id)
    {
        return Inertia::render('Geraet/Ausgabe/View', [
            'ausgabe' => Geraetausgabe::where('id', $id)->with(['ausleiher','projekte', 'projekte.kostenstellen' ,'geraete'])->first(),
            'alle_kontakte' => Personen::mitarbeiter()->get(),
            'alle_projekte' => Projekt::All(),
            'nichtAusgegebeneGeraete' => Geraet::where('verfuegbarkeit', '=', 1)->get()
        ]);
    }



    public function store(Request $request)
    {
        $validated = $request->validate([
            'ausgabeschein_nr' => 'required|unique:geraetausgabes,ausgabescheinNr',
            'ausleiher' => 'required|exists:personens,id',
            'projekt' => 'required|exists:projekts,id',
            'sn' => 'required|array',
            'sn.*' => 'exists:geraets,sn',
            'ausleihdatum' => 'required|date'
        ]);
        DB::beginTransaction();
        try {
            $ausleihdatum = Carbon::parse($validated['ausleihdatum'])->format('Y-m-d');
            $ausgabe = Geraetausgabe::create([
                'ausgabescheinNr' => $validated['ausgabeschein_nr'],
                'ausleiher_id' => $validated['ausleiher'],
                'projekte_id' => $validated['projekt'],
                'ausgabe' => $ausleihdatum,
            ]);


            $success = [];
            $error = [];

        foreach ($validated['sn'] as $SN) {

                $geraet = Geraet::where('sn', $SN)->first();
                if (!$geraet) {
                    $error[] = $SN;
                    continue;
                }
                $geraet->update([
                    'verfuegbarkeit' => false,
                ]);

                GeraetHasAusgabe::create([
                    'geraet_id' => $geraet->id,
                    'ausgabe_id' => $ausgabe->id
                ]);

                $kontakt = Personen::find($validated['ausleiher']);

               /*  $geraet->update([
                    'verfuegbarkeit' => false,
                    'imLager' => $kontakt->vorname . ' ' . $kontakt->nachname
                ]); */

                $success[] = $SN;
            }


            DB::commit();

            return redirect()
                ->route('geraet.ausgabe.index')
                ->with('success', 'Ausgabe erfolgreich erstellt.');

        } /* catch (Exception $e) {

            DB::rollBack();

            return redirect()
                ->back()
                ->withErrors([
                    'error' => 'Fehler beim Speichern der Ausgabe.'
                ]);
        } */

        catch (Exception $e) {
            DB::rollBack();
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'error' => 'Fehler beim Speichern der Ausgabe.',
                ]);
        }
    }

    public function storeAdd(Request $request)
    {
        $request->validate([
            'ausgabeschein_nr' => 'required',
            'ausleiher' => 'required',
            'projekt' => 'required',
            'sn' => 'required|array',
            'sn.*' => 'exists:geraets,sn',
            'ausleihdatum' => 'required',
         ]);
         $selectedGeraetSN = $request->input('sn');
         $success = [];
         $error = [];


         foreach ($selectedGeraetSN as $SN)
         {
            $geraet = Geraet::where('sn', $SN)->first();
            $ausgabe = Geraetausgabe::where('ausgabescheinNr', $request->ausgabeschein_nr)->first();
            if (!$geraet OR !$ausgabe) {
                $error[] = "Das Gerät mit der SN: ". $SN ." konnte nicht gefunden werden.";
                continue;
            }
            if (!$ausgabe) {
                $error[] = "Die Ausgabe mit der Ausgabescheinnummer: ". $request->ausgabeschein_nr ." konnte nicht gefunden werden.";
                continue;
            }
            $relation = new GeraetHasAusgabe;
            $relation->geraet_id = $geraet->id;
            $relation->ausgabe_id = $ausgabe->id;
            $relation->save();
            $success[] = " ID: {$geraet->sn}";


            $geraet->verfuegbarkeit = FALSE;
            $geraet->update();

        }
       /*  $editorRoles = Role::whereIn('name', ['Administrator', 'IT-Administrator'])->get();

                foreach ($editorRoles as $role) {
                    foreach ($role->users as $user) {
                        $user->notify(new CreateAusgabaAddNotification($selectedGeraetSN));
                    }
                } */


            $successMessage = 'Die Ausgaben wurden erfolgreich angelegt.';
            if (!empty($success)) {
                $successMessage .= ' Erfolgreiche Geräte: ' . implode(', ', $success);
            }
            if (!empty($error)) {
                $successMessage .= ' Probleme mit den Geräten: ' . implode(', ', $error);
            }

            return redirect()->back()->with('success', $successMessage);
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




    //Löschung darf nicht erfolgen, wenn die Ausgabe mit einem Gerät verknüpft ist. In diesem Fall muss zuerst
    // die Verknüpfung aufgehoben und das Gerät als verfügbar markiert werden.
    public function destroy($id)
    {
         try {
            $geraetausgabe = Geraetausgabe::findOrFail($id);

            $geraetausgabe->delete();

            return response()->json(['message' => 'Ausgabe erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Ausgabe nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }


    public function exportExcel($id)
    {
        $ausgabe = Geraetausgabe::findOrFail($id);

        $geraeteDistinct = $ausgabe->geraete()
            ->select('geraet', 'modell')
            ->distinct()
            ->get();

        $geraeteArray = $geraeteDistinct->map(function($item) {
            return $item->geraet . ' ' . $item->modell;
        })->toArray();

        // Verbinden des Arrays zu einem String
        $geraeteString = implode(', ', $geraeteArray);

        //Pfad zur vorhandenen Excel-Datei
        $existingFile = storage_path('vorlage/projekte/ITabteilung/devicemanagement/Ausgabeschein.xlsx');
        if(!file_exists($existingFile)){
            return redirect()->back()->with('error', 'Die Datei für den Export konnte nicht gefunden werden.');
        }

        // Excel-Datei öffnen
        $spreadsheet = IOFactory::load($existingFile);
        $sheet = $spreadsheet->getActiveSheet();

        // Daten in den Zellen einfügen
        $sheet->setCellValue('C7', $ausgabe->projekte->name);
        $sheet->setCellValue('C8', $ausgabe->projekte->kostenstelle);
        $sheet->setCellValue('F7', 'Ausgabeschein Nr.: ' . $ausgabe->ausgabescheinNr );
        $sheet->setCellValue('C12', $geraeteString);


        $loop = 1;
        $row = 15; // Startzeile für Daten
        $sheet->setCellValue('B31', $ausgabe->geraete->count());
        $angabedatum = $ausgabe->ausgabe;
        $sheet->setCellValueExplicit('D33', date('d.m.Y', strtotime($angabedatum)), DataType::TYPE_STRING);
        $sheet->setCellValue('A39', date('d.m.Y'));
        $sheet->setCellValue('C36', $ausgabe->ausleiher->nachname);
        $sheet->setCellValue('E36', $ausgabe->ausleiher->vorname);

        if($ausgabe->geraete !=""){
            foreach ($ausgabe->geraete as $ausgabe) {
                $sheet->setCellValue('A'.$row, $loop);

                $sheet->setCellValue('B'.$row, $ausgabe->productID);


                $sheet->setCellValue('C'.$row, $ausgabe->sn);
                $row++;
                $loop++;
            }
        }else{
            return redirect()->back()->with('error', 'Die Ausgabe enthält kein Gerät.');
        }

        // Excel-Datei speichern

        $writer = new Xlsx($spreadsheet);

       $updatedFile = 'Ausgabeschein Nr. ' .  $ausgabe->ausgabescheinNr . '-'  . date('Ymd_His') . '.xlsx';
       $writer->save($updatedFile);

       // Aktualisierte Excel-Datei herunterladen
       return response()->download($updatedFile)->deleteFileAfterSend(true);
    }
}
