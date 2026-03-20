<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;

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

}
