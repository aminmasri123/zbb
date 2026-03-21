<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Services\MyDatum;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

class ProjektBopController extends Controller
{
    public function anwesenheitslistePOBOExportWordBIBB(Request $request)
    {
            if(!$request->exportFormat OR !$request->termin1 OR !$request->termin2 OR !$request->termin3 OR !$request->termin4 OR !$request->termin5 OR !$request->termin6 OR !$request->termin7 OR !$request->termin8 OR !$request->termin9 OR !$request->termin10 OR !$request->schuljahrInputBibb OR !$request->schuleIdInputBibb OR !$request->teilInputBibb)
            {
                return redirect()->back()->with('error', 'Fehlenden Daten.');
            }

            $schuljahr = $request->schuljahrInputBibb;
            $schulId = $request->schuleIdInputBibb;
            $teil = $request->teilInputBibb;
            $format = $request->exportFormat;

            if($format == "A4")
            {
                $templateFile = storage_path('vorlage/projekte/bop/word/bo/Teilnehmendenliste_zum_Nachweis_der_praktischen_Berufsorientierung_A4.docx');
            }elseif($format == "A3")
            {
                $templateFile = storage_path('vorlage/projekte/bop/word/bo/Teilnehmendenliste_zum_Nachweis_der_praktischen_Berufsorientierung_A3.docx');
            }
            if(!file_exists($templateFile)){
                return redirect()->back()->with('error', 'Die Datei für den Export konnte nicht gefunden werden.');
            }


            $alle_teilnehmer = PersonenIstSchueler::query()->filterSchueler( $schulId ?? null, $schuljahr ?? null, $teil ?? null)
            ->with('person')
            ->get();


            $klassen = $alle_teilnehmer->pluck('klasse')->unique()->toArray();

            $klassen = implode(', ', $klassen);
            $schule = Partner::findOrFail($schulId);


            $projekt_id = Auth()->user()->current_team_id;
            $projekt = Projekt::with('bereiche')->find($projekt_id);
            $bereiche = $projekt?->bereiche
                ->pluck('code')
                ->unique()
                ->implode('/ ');


            if(!$schule){
                return redirect()->back()->with('error', 'Die Schule konnte nicht gefunden werden.');
            }
            if($alle_teilnehmer->isEmpty()){
                return redirect()->back()->with('error', 'Die gewählte Schule weist zurzeit noch keine Schüler auf.');
            }

            $tag1 = Carbon::parse($request->termin1)->format('d.m.Y');
            $tag2 = Carbon::parse($request->termin2)->format('d.m.Y');
            $tag3 = Carbon::parse($request->termin3)->format('d.m.Y');
            $tag4 = Carbon::parse($request->termin4)->format('d.m.Y');
            $tag5 = Carbon::parse($request->termin5)->format('d.m.Y');
            $tag6 = Carbon::parse($request->termin6)->format('d.m.Y');
            $tag7 = Carbon::parse($request->termin7)->format('d.m.Y');
            $tag8 = Carbon::parse($request->termin8)->format('d.m.Y');
            $tag9 = Carbon::parse($request->termin9)->format('d.m.Y');
            $tag10 = Carbon::parse($request->termin10)->format('d.m.Y');


            if($request->termin11){
                $tag11 = Carbon::parse($request->termin11)->format('d.m.Y');

            }else{
                $tag11 = "";
            }
            $i = 1;
            $templateProcessor = new TemplateProcessor($templateFile);

            // Einfügen der Daten in die Textfelder


            $templateProcessor->setValue('schule', $schule->name);
            $schulform = PersonenIstSchueler::query()->schulform($alle_teilnehmer);
            $templateProcessor->setValue('bereiche', $bereiche);

            $templateProcessor->setValue('schulform', $schulform);
            $templateProcessor->setValue('klasse', $klassen);
            $templateProcessor->setValue('tag1', $tag1);
            $templateProcessor->setValue('tag2', $tag2);
            $templateProcessor->setValue('tag3', $tag3);
            $templateProcessor->setValue('tag4', $tag4);
            $templateProcessor->setValue('tag5', $tag5);
            $templateProcessor->setValue('tag6', $tag6);
            $templateProcessor->setValue('tag7', $tag7);
            $templateProcessor->setValue('tag8', $tag8);
            $templateProcessor->setValue('tag9', $tag9);
            $templateProcessor->setValue('tag10', $tag10);
            $templateProcessor->setValue('tag11', $tag11);

            $templateProcessor->setValue('anfangsdatum', $tag1);
            $templateProcessor->setValue('enddatum', $tag11);

            foreach ($alle_teilnehmer as $teilnehmer)
            {
                // Initialisieren Sie den TemplateProcessor für jede Schleifeniteration
                $templateProcessor->setValue('nachname' . $i, $teilnehmer->person->nachname);
                $templateProcessor->setValue('vorname' . $i, $teilnehmer->person->vorname);
                $templateProcessor->setValue('klasse' . $i, $teilnehmer->klasse);
                $i++;
            }
            while($i<=97){
                $templateProcessor->setValue('nachname' . $i, '');
                $templateProcessor->setValue('vorname' . $i, '');
                $templateProcessor->setValue('klasse' . $i, '');
                $i++;
            }
                $filename = 'Teilnehmendenliste_zum_Nachweis_der_praxisorientierten_Berufsorientierung_' . $schule->name . '_' . $schuljahr . '_' .  date('Ymd_His') . '.docx';

                $templateProcessor->saveAs(storage_path('exports/' . $filename));
                return response()->download(storage_path('exports/' . $filename ))->deleteFileAfterSend(true);
    }
    public function anwesenheitslistePAexportWord(Request $request)
    {

          $validated = $request->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date',
            'schuleId' => 'required|exists:partners,id',
            'schuljahr' => 'required|string',
            'teil' => 'required|string',
            'klasse' => 'required|string',
        ]);

        $templateFile = storage_path('vorlage/projekte/bop/word/pa/Anwesenheitsliste-PA.docx');

            if(!file_exists($templateFile)){
                return redirect()->back()->with('error', 'Die Datei für den Export konnte nicht gefunden werden.');
            }


            $alle_teilnehmer = PersonenIstSchueler::query()
            ->filterSchueler($validated['schuleId'], $validated['schuljahr'], $validated['teil'])
            ->where('klasse', $validated['klasse'])
            ->with('person')
            ->get()
            ->sortBy(fn($item) => $item->person->nachname);


            $schule = Partner::findOrFail($request->schuleId);


            if(!$schule){
                return redirect()->back()->with('error', 'Die Schule konnte nicht gefunden werden.');
            }
            if($alle_teilnehmer->isEmpty()){
                return redirect()->back()->with('error', 'Die Schule hat keine Teilnehmer.');
            }

            $tag1 = Carbon::parse($request->startDate)->format('d.m.Y');
            $tag2 = Carbon::parse($request->endDate)->format('d.m.Y');

            $i = 1;
            $templateProcessor = new TemplateProcessor($templateFile);

            // Einfügen der Daten in die Textfelder

            $templateProcessor->setValue('schule', $schule->name);

            $schulform = PersonenIstSchueler::query()->schulform($alle_teilnehmer);

            $templateProcessor->setValue('schulform', $schulform);

            $templateProcessor->setValue('klasse', $request->klasse);
            $templateProcessor->setValue('tag1', $tag1);
            $templateProcessor->setValue('tag2', $tag2);

            foreach ($alle_teilnehmer as $teilnehmer)
            {
                // Initialisieren Sie den TemplateProcessor für jede Schleifeniteration

                $templateProcessor->setValue('nachname' . $i, $teilnehmer->person->nachname);
                $templateProcessor->setValue('vorname' . $i, $teilnehmer->person->vorname);

                $i++;

                // Speichern der individuellen Briefe


            }
            while($i<=30){
                $templateProcessor->setValue('nachname' . $i, '');
                $templateProcessor->setValue('vorname' . $i, '');
                $i++;
            }
                $filename = 'Anwesenheitsliste_PA_' . $schule->name . '_' . $request->klasse . '_' . $tag1 . '_' . $tag2 . '_'  . date('Ymd_His') . '.docx';

                $templateProcessor->saveAs(storage_path('exports/' . $filename));
                return response()->download(storage_path('exports/' . $filename ))->deleteFileAfterSend(true);
    }

    public function anwesenheitslistePOBOTag1($partnerID, $schuljahr, $teil, $klasse = 'exportAlleKlassen', Request $request)
    {
        // Query Parameter
        $anzahlBereiche = request()->query('anzahlBereiche', 6);
        $anzahlRaeumlichkeiten = request()->query('anzahlRaeumlichkeiten', $anzahlBereiche);
        $kapazitaeten = request()->query('kapazitaeten', []);
        $termin = request()->query('termin', date('d-m-Y')) ;
        $raumNamen = $request->input('raumNamen', []);

        // Prüfen
        if (!$partnerID || !$schuljahr || !$teil || !$termin ){
            return redirect()->route('partner.index')->with('error', 'Fehlende Daten.');
        }

        $schule = Partner::findOrFail($partnerID);

        // Teilnehmer laden
        $alleTeilnehmer = PersonenIstSchueler::where('schule_id', $schule->id)
            ->where('schuljahr', $schuljahr)
            ->where('teil', $teil)
            ->when($klasse !== 'exportAlleKlassen' && $klasse !== 'exportAlleKlassenZip' , fn($q) => $q->where('klasse', $klasse))
            ->with('person')
            ->get()
            ->sortBy(fn($t) => $t->person->nachname);

        if ($alleTeilnehmer->isEmpty()) {
            return back()->with('error', 'Keine Teilnehmer gefunden.');
        }

        // Template
        $templateFile = storage_path('vorlage/projekte/bop/excel/bo/botag1/Anwesenheitsliste-BO-TAG1.xlsx');
        if (!file_exists($templateFile)) {
            return back()->with('error', 'Template fehlt.');
        }

        /*
        |--------------------------------------------------------------------------
        | 🟢 FALL 1: EINZELNE KLASSE
        |--------------------------------------------------------------------------
        */
        if ($klasse !== 'exportAlleKlassen' && $klasse !== 'exportAlleKlassenZip') {
          Log::info('fall 1');

            $templateFile = storage_path('vorlage/projekte/bop/excel/bo/botag1/Anwesenheitsliste-BO-TAG1-Klasse.xlsx');
            if (!file_exists($templateFile)) {
                return back()->with('error', 'Template fehlt.');
            }

            $spreadsheet = IOFactory::load($templateFile);
            $sheet = $spreadsheet->getActiveSheet();

            $terminDatum = DateTime::createFromFormat('Y-m-d', $termin)->format('d.m.Y');

            $sheet->setCellValue('H5', $terminDatum);
            $sheet->setCellValue('C2', "BO Tag 1 - Klasse $klasse - " . $schule->name);

            $row = 8;

            foreach ($alleTeilnehmer as $t) {
                $sheet->setCellValue('B' . $row, $t->person->nachname . ', ' . $t->person->vorname);
                $sheet->setCellValue('D' . $row, $t->klasse);
                $sheet->setCellValue('E' . $row, $t->geschlecht);
                $row++;
            }

            $filePath = storage_path('Anwesenheitsliste-BOTag1_' . $klasse . '.xlsx');
            (new Xlsx($spreadsheet))->save($filePath);

            return response()->download($filePath)->deleteFileAfterSend(true);
        }
        /*
        |--------------------------------------------------------------------------
        | 🔵 FALL 2: ALLE KLASSEN → ZIP mit Klassenlisten
        |--------------------------------------------------------------------------
        */
        if ($klasse === 'exportAlleKlassenZip') {
                      Log::info('fall 2');

            $templateFile = storage_path('vorlage/projekte/bop/excel/bo/botag1/Anwesenheitsliste-BO-TAG1-klasse.xlsx');
            if (!file_exists($templateFile)) {
                return back()->with('error', 'Template fehlt.');
            }

            // 👉 Teilnehmer nach Klassen gruppieren
            $gruppenNachKlassen = $alleTeilnehmer->groupBy('klasse');

            // 👉 Temp Ordner
            $tempDir = storage_path('temp_excel');
            if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);

            array_map('unlink', glob($tempDir . '/*'));

            $dateien = [];

            foreach ($gruppenNachKlassen as $klassenName => $teilnehmerListe) {

                $spreadsheet = IOFactory::load($templateFile);
                $sheet = $spreadsheet->getActiveSheet();

                $terminDatum = DateTime::createFromFormat('Y-m-d', $termin)->format('d.m.Y');

                // Kopf
                $sheet->setCellValue('H5', $terminDatum);
                $sheet->setCellValue('C2', "BO Tag 1 - Klasse $klassenName - " . $schule->name);

                // Teilnehmer eintragen
                $row = 8;

                foreach ($teilnehmerListe as $t) {
                    $sheet->setCellValue('B' . $row, $t->person->nachname . ', ' . $t->person->vorname);
                    $sheet->setCellValue('D' . $row, $t->klasse);
                    $sheet->setCellValue('E' . $row, $t->geschlecht);
                    $row++;
                }

                // Datei speichern
                $fileName = "Anwesenheitsliste_{$klassenName}.xlsx";
                $filePath = $tempDir . '/' . $fileName;

                (new Xlsx($spreadsheet))->save($filePath);

                $dateien[] = $filePath;
            }

            // 👉 ZIP erstellen
            $zipFileName = storage_path('Anwesenheitslisten_Klassen_' . date('Ymd_His') . '.zip');
            $zip = new ZipArchive();

            if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {

                foreach ($dateien as $file) {
                    $zip->addFile($file, basename($file));
                }

                $zip->close();

                // Temp löschen
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);

                return response()->download($zipFileName)->deleteFileAfterSend(true);
            }

            return back()->with('error', 'ZIP konnte nicht erstellt werden.');
        }

        /*
        |--------------------------------------------------------------------------
        | 🔵 FALL 3: Benutzerdefinierte Räume mit Namen und Kapazitäten
        |--------------------------------------------------------------------------
        */
        if ($request->has('anzahlRaeumlichkeiten') && $request->has('raumNamen')) {
            $anzahlRaeumlichkeiten = (int)$anzahlRaeumlichkeiten;

            // Validierung
            if ($anzahlRaeumlichkeiten < 1 || count($raumNamen) != $anzahlRaeumlichkeiten) {
                return back()->with('error', 'Anzahl der Räume und Raumnamen stimmen nicht überein.');
            }

            $anzahlTeilnehmer = $alleTeilnehmer->count();

            // Kapazitäten automatisch berechnen, falls leer
            if (!$kapazitaeten || count($kapazitaeten) != $anzahlRaeumlichkeiten) {
                $grundzahl = intdiv($anzahlTeilnehmer, $anzahlRaeumlichkeiten);
                $rest = $anzahlTeilnehmer % $anzahlRaeumlichkeiten;

                $kapazitaeten = [];
                for ($i = 0; $i < $anzahlRaeumlichkeiten; $i++) {
                    $kapazitaeten[$i] = $grundzahl + ($i < $rest ? 1 : 0);
                }
            }

            // Temp Ordner vorbereiten
            $tempDir = storage_path('temp_excel');
            if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
            array_map('unlink', glob($tempDir . '/*'));

            $startIndex = 0;

            for ($i = 0; $i < $anzahlRaeumlichkeiten; $i++) {
                $spreadsheet = IOFactory::load($templateFile);
                $sheet = $spreadsheet->getActiveSheet();

                // Raumname oder generisch
                $raumNameOriginal = $raumNamen[$i] ?? 'Raum ' . ($i + 1);
                $raumName = Str::slug($raumNameOriginal, '_');

                $anzahlAktuell = $kapazitaeten[$i];
                $terminDatum = DateTime::createFromFormat('d-m-Y', $termin)->format('d.m.Y');

                $sheet->setCellValue('H5', $terminDatum);
                $sheet->setCellValue('C2', "Gruppe " . ($i + 1) . " - $raumName - " . $schule->name);

                $row = 8;

                for ($j = $startIndex; $j < $startIndex + $anzahlAktuell && $j < $anzahlTeilnehmer; $j++) {
                    $t = $alleTeilnehmer[$j];

                    $sheet->setCellValue('B' . $row, $t->person->nachname . ', ' . $t->person->vorname);
                    $sheet->setCellValue('D' . $row, $t->klasse);
                    $sheet->setCellValue('E' . $row, $t->geschlecht);
                    $row++;
                }

                $startIndex += $anzahlAktuell;

                // Excel speichern
                (new Xlsx($spreadsheet))->save($tempDir . "/Liste_" . ($i + 1) . "_$raumName.xlsx");
            }

            // ZIP erstellen
            $zipPath = storage_path('Anwesenheitslisten_Fall4.zip');
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
                foreach (glob($tempDir . '/*.xlsx') as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();

                // Cleanup
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);

                return response()->download($zipPath)->deleteFileAfterSend(true);
            }

            return back()->with('error', 'ZIP konnte nicht erstellt werden.');
        }

         /*
        |--------------------------------------------------------------------------
        | 🔵 FALL 4: ALLE KLASSEN gemischt sortiert nach nachname für alle Bereiche
        |--------------------------------------------------------------------------
        */
            $anzahlTeilnehmer = $alleTeilnehmer->count();
            $alleTeilnehmer = $alleTeilnehmer
            ->sortBy(fn($t) => strtolower($t->person->nachname), SORT_NATURAL)
            ->values();
            // Temp Ordner
            $tempDir = storage_path('temp_excel');
            if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);
            array_map('unlink', glob($tempDir . '/*'));

            // Kapazitäten berechnen (wenn leer)
            if (!$kapazitaeten || count($kapazitaeten) != $anzahlRaeumlichkeiten) {
                $grundzahl = intdiv($anzahlTeilnehmer, $anzahlRaeumlichkeiten);
                $rest = $anzahlTeilnehmer % $anzahlRaeumlichkeiten;

                $kapazitaeten = [];
                for ($i = 0; $i < $anzahlRaeumlichkeiten; $i++) {
                    $kapazitaeten[$i] = $grundzahl + ($i < $rest ? 1 : 0);
                }
            }

            // Bereiche laden
            $projekt = Projekt::with('bereiche')
                ->where('id', auth()->user()->current_team_id)
                ->first();

            // Als Array von Bereichsnamen
            $bereicheListe = $projekt->bereiche->pluck('name')->toArray();
            $bereiche = array_slice($bereicheListe, 0, $anzahlBereiche);

            $startIndex = 0;
            $gruppenListe = [];
            for ($i = 0; $i < $anzahlRaeumlichkeiten; $i++) {

                $spreadsheet = IOFactory::load($templateFile);
                $sheet = $spreadsheet->getActiveSheet();

                $bereichNameOriginal = $bereiche[$i % count($bereiche)];
                $anzahlAktuell = $kapazitaeten[$i];

                $terminDatum = DateTime::createFromFormat('Y-m-d', $termin)->format('d.m.Y');

                // Dateisicheren Bereichsnamen erstellen
                $bereich = Str::slug($bereichNameOriginal, '_');

                $sheet->setCellValue('H5', $terminDatum);
                $sheet->setCellValue('C2', "Gruppe " . ($i + 1) . " - $bereich - " . $schule->name);

                $row = 8;

                for ($j = $startIndex; $j < $startIndex + $anzahlAktuell && $j < $anzahlTeilnehmer; $j++) {
                    $t = $alleTeilnehmer[$j];

                    $sheet->setCellValue('B' . $row, $t->person->nachname . ', ' . $t->person->vorname);
                    $sheet->setCellValue('D' . $row, $t->klasse);
                    $sheet->setCellValue('E' . $row, $t->geschlecht);
                    $row++;

                    $gruppenListe[] = [
                        'gruppe' => $i + 1,
                        'bereich' => $bereich,
                        'name' => $t->person->nachname . ', ' . $t->person->vorname
                    ];
                }

                $startIndex += $anzahlAktuell;

                // Excel speichern
                (new Xlsx($spreadsheet))->save($tempDir . "/Liste_" . ($i + 1) . ".xlsx");
            }

            // ZIP erstellen
            $zipPath = storage_path('Anwesenheitslisten.zip');
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {

                foreach (glob($tempDir . '/*.xlsx') as $file) {
                    $zip->addFile($file, basename($file));
                }

                $zip->close();

                // Cleanup
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);

                return response()->download($zipPath)->deleteFileAfterSend(true);
            }

            return back()->with('error', 'ZIP konnte nicht erstellt werden.');
    }


    public function hausordnungExportPdf($partnerID, $schuljahr, $teil, $sortBy, $termin)
    {
        $schule = Partner::findOrFail($partnerID);
            if (!$schule)
            {
                return redirect()->route('partner.index')->with('error', 'Die gewählte Schule konnte nicht gefunden werden.');
            }
            if($sortBy != 'nachname' && $sortBy != 'klasse' ){
                return redirect()->route('partner.index')->with('error', 'Bitte wählen Sie einen Sortierungstyp vor dem Export aus.');
            }
        // Daten aus der Tabelle abrufen
       if($sortBy == 'nachname'){
             $alle_teilnehmer = PersonenIstSchueler::where('schuljahr', $schuljahr)
            ->where('schule_id', $partnerID)
                ->where('teil', $teil)
                ->with('person')
                ->get()
                ->sortBy(fn($t) => strtolower($t->person->nachname), SORT_NATURAL);


       }elseif($sortBy == 'klasse'){
           $alle_teilnehmer = PersonenIstSchueler::where('schuljahr', $schuljahr)
            ->where('schule_id', $partnerID)
            ->where('teil', $teil)
            ->with('person')
            ->get()
            ->sort(function($a, $b) {
                // Zuerst Klasse, natürlich sortiert
                $klasseCompare = strnatcasecmp($a->klasse, $b->klasse);
                if ($klasseCompare !== 0) {
                    return $klasseCompare;
                }

                // Dann Nachname, natürlich sortiert
                return strnatcasecmp($a->person->nachname, $b->person->nachname);
            });
        }
        if($alle_teilnehmer->isEmpty()){
            return redirect()->back()->with('error', 'Die Schule verfügt derzeit keine Teilnehmer.');
        }

        $data = [
            'alle_teilnehmer' => $alle_teilnehmer,
            'datum' => $termin,
        ];

       $pdf = Pdf::loadView('pdf.hausordnung',  $data);
        return $pdf->stream('invoice.pdf');
    }
}
