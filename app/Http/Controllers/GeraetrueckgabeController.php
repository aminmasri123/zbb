<?php

namespace App\Http\Controllers;

use App\Models\Geraet;
use App\Models\Geraetausgabe;
use App\Models\GeraetHasRueckgabe;
use App\Models\Geraetrueckgabe;
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

class GeraetrueckgabeController extends Controller
{
    public function __construct()
    {
        $this->middleware('module:it_management');
    }

    /**
     * Display a listing of the resource.
     */
   public function index()
    {
       /*  $id=1;
         $geraete = DB::table('geraets')
            ->join('geraet_has_ausgabes', 'geraets.id', '=', 'geraet_has_ausgabes.geraet_id')

            ->where('geraet_has_ausgabes.ausgabe_id', $id)

            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('geraet_has_rueckgabes')
                    ->whereColumn('geraet_has_rueckgabes.geraet_id', 'geraets.id');
            })

            ->select('geraets.*')
            ->get();



            dd($geraete);
 */
















        $rueckgaben = Geraetrueckgabe::with([
            'ausgabe.ausleiher',
            'ausgabe.projekte',
            'geraete'
        ])->get();

        $rueckgeber = Personen::where('typ', 'mitarbeiter')->select('id', 'nachname', 'vorname')->get();

        $ausgaben = Geraetausgabe::with([
            'geraete',
            'ausleiher',
            'projekte'
        ])->get();

        $geraete = Geraet::all();

        $ablageorte = Geraet::distinct()
            ->whereNotNull('imLager')
            ->pluck('imLager');
/*
        $ausgegebeneGeraete = DB::table('geraets')
            ->leftJoin('geraet_has_ausgabes', 'geraets.id', '=', 'geraet_has_ausgabes.geraet_id')
            ->whereNotNull('geraet_has_ausgabes.geraet_id')
            ->select('geraets.*')
            ->get(); */
        return Inertia::render('Geraet/Rueckgabe/Index', [
            'rueckgaben' => $rueckgaben,
            'rueckgeber' => $rueckgeber,
            'ausgaben' => $ausgaben,
            /**
             * Zeigt die Geräte, die ausgegeben wurden, aber noch nicht zurückgegeben wurden.
             */
           
            'geraete' => $geraete,
            'ablageorte' => $ablageorte,
            //'ausgegebeneGeraete' => $ausgegebeneGeraete
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
            'ausgabeschein_nr' => 'required|exists:geraetausgabes,id',
            'ausleiher' => 'required|exists:personens,id',
            'rueckgabescheinNr' => 'required|unique:geraetrueckgabes,rueckgabescheinNr',
            'sn' => 'required|array',
            'sn.*' => 'exists:geraets,id',
            'rueckgabedatum' => 'required|date',
            'ablageort' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {

            $success = [];
            $errors = [];

            $rueckgabedatum = Carbon::parse($validated['rueckgabedatum'])->format('Y-m-d');

            $rueckgabe = Geraetrueckgabe::create([
                'ausgabe_id' => $validated['ausgabeschein_nr'],
                'ausleiher_id' => $validated['ausleiher'],
                'rueckgabescheinNr' => $validated['rueckgabescheinNr'],
                'rueckgabe' => $rueckgabedatum
            ]);

            foreach ($validated['sn'] as $geraetId) {

                $geraet = Geraet::find($geraetId);

                if (!$geraet) {
                    $errors[] = "Gerät nicht gefunden: {$geraetId}";
                    continue;
                }
            
                GeraetHasRueckgabe::create([
                    'geraet_id' => $geraet->id,
                    'rueckgabe_id' => $rueckgabe->id
                ]);

                $geraet->update([
                    'verfuegbarkeit' => true,
                    'imLager' => $validated['ablageort']
                ]);

                $success[] = $geraet->sn;
            }

            DB::commit();

            return redirect()
                ->route('geraet.rueckgabe.index')
                ->with('success', 'Rückgabe erfolgreich erstellt.');

        } catch (Exception $e) {

            DB::rollBack();

            return redirect()
                ->back()
                ->withErrors([
                    'error' => 'Fehler beim Speichern der Rückgabe: ' . $e->getMessage()
                ]);
        }
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
        try {
            $geraetrueckgabe = Geraetrueckgabe::findOrFail($id);

            $geraetrueckgabe->delete();

            return response()->json(['message' => 'Rückgabe erfolgreich gelöscht!'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Rückgabe nicht gefunden.'], 404);
        } catch (Exception $e) {
            return response()->json(['message' => 'Ein Fehler ist aufgetreten: ' . $e->getMessage()], 500);
        }
    }

    public function geraete($id)
    {
        
        $ausgabe = DB::table('geraets')
            ->join('geraet_has_ausgabes', 'geraets.id', '=', 'geraet_has_ausgabes.geraet_id')

            ->where('geraet_has_ausgabes.ausgabe_id', $id)

            ->whereNotExists(function ($query) use ($id) {
                $query->select(DB::raw(1))
                    ->from('geraet_has_rueckgabes')
                    ->join('geraetrueckgabes', 'geraetrueckgabes.id', '=', 'geraet_has_rueckgabes.rueckgabe_id')
                    ->whereColumn('geraet_has_rueckgabes.geraet_id', 'geraets.id')
                    ->where('geraetrueckgabes.ausgabe_id', $id);
            })

            ->select('geraets.*')
            ->get();

        return response()->json($ausgabe);
    }

     public function view($id)
    {
        return Inertia::render('Geraet/Rueckgabe/View', [
            'rueckgabe' => Geraetrueckgabe::where('id', $id)->with(['ausgabe', 'ausleiher', 'ausgabe.projekte', 'ausgabe.projekte.kostenstellen', 'geraete'])->first(),
            'alle_kontakte' => Personen::mitarbeiter()->get(),
            'alle_projekte' => Projekt::All(),
            'nichtAusgegebeneGeraete' => Geraet::where('verfuegbarkeit', '=', 1)->get()
        ]);
    }

     public function exportExcel($id)
    {
        $rueckgabe = Geraetrueckgabe::where('id', $id)->first();
        $geraeteDistinct = $rueckgabe->geraete()
            ->select('geraet', 'modell')
            ->distinct()
            ->get();

        $geraeteArray = $geraeteDistinct->map(function($item) {
            return $item->geraet . ' ' . $item->modell;
        })->toArray();

        // Verbinden des Arrays zu einem String
        $geraeteString = implode(', ', $geraeteArray);

        //Pfad zur vorhandenen Excel-Datei
        $existingFile = storage_path('vorlage/projekte/ITabteilung/devicemanagement/Rueckgabeschein.xlsx');
        if(!file_exists($existingFile)){
            return redirect()->back()->with('error', 'Die Datei für den Export konnte nicht gefunden werden.');
        }

        // Excel-Datei öffnen
        $spreadsheet = IOFactory::load($existingFile);
        $sheet = $spreadsheet->getActiveSheet();

        // Daten in den Zellen einfügen

        $sheet->setCellValue('C7', $rueckgabe->ausgabe->projekte->name);
        $sheet->setCellValue('C8', $rueckgabe->ausgabe->projekte->kostenstelle);


        $sheet->setCellValue('F7', 'Rückgabeschein Nr.: ' . $rueckgabe->ruegabescheinNr );
        $sheet->setCellValue('F8', 'Zu Ausgabeschein Nr.: ' . $rueckgabe->ausgabe->ausgabescheinNr );

        $sheet->setCellValue('C12', $geraeteString);

        $loop = 1;
        $row = 15; // Startzeile für Daten
        $sheet->setCellValue('B31', $rueckgabe->geraete->count());
        $rueckgabedatum = $rueckgabe->rueckgabe;
        $sheet->setCellValueExplicit('D33', date('d.m.Y', strtotime($rueckgabedatum)), DataType::TYPE_STRING);
        $sheet->setCellValue('A39', date('d.m.Y'));

        $sheet->setCellValue('C36', $rueckgabe->ausleiher->nachname );
        $sheet->setCellValue('E36', $rueckgabe->ausleiher->vorname );

        if($rueckgabe->geraete !=""){
            foreach ($rueckgabe->geraete as $geraet) {
                $sheet->setCellValue('A'.$row, $loop);
                $sheet->setCellValue('B'.$row, $geraet->productID);
                $sheet->setCellValue('C'.$row, $geraet->sn);
                $row++;
                $loop++;
            }
        }else{
            return redirect()->back()->with('error', 'Die Ausgabe enthält kein Gerät.');
        }

        // Excel-Datei speichern
        $writer = new Xlsx($spreadsheet);

       $updatedFile = 'Rückgabeschein Nr. ' .  $rueckgabe->rueckgabescheinNr . '-'  . date('Ymd_His') . '.xlsx';
       $writer->save($updatedFile);

       // Aktualisierte Excel-Datei herunterladen
       return response()->download($updatedFile)->deleteFileAfterSend(true);
    }


     
}
