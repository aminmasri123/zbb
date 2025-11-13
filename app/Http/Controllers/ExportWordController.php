<?php

namespace App\Http\Controllers;

use App\Models\Projekt;
use App\Models\Personen;
use Illuminate\Http\Request;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;

class ExportWordController extends Controller
{
        //dd($templateProcessor->getVariables());

    public function info_teilnehmende(Request $request, $id)
    {
        $pfad = $request->query('pfad'); // aus der Query
        $pfad = urldecode($pfad);
        $projekt = Projekt::findOrFail(auth()->user()->current_team_id);

        if (!file_exists(storage_path($pfad))) {
            abort(404, 'Die gewünschte Datei wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }
        $teilnehmer = Personen::teilnehmer()->findOrFail($id);

        $templateProcessor = new TemplateProcessor(storage_path($pfad));
        //dd($templateProcessor->getVariables());

        $templateProcessor->setValue('vorname', $teilnehmer->vorname);
        $templateProcessor->setValue('nachname', $teilnehmer->nachname);
        $templateProcessor->setValue('datum', now()->format('d.m.Y'));
        $templateProcessor->setValue('projekt', $projekt->name);

        $outputPath = storage_path("app/temp/info_teilnehmende_{$id}.docx");
        $templateProcessor->saveAs($outputPath);

        return response()->download($outputPath)->deleteFileAfterSend(true);
    }

    public function bildungsvertrag_inteqra(Request $request, $id)
    {

        $pfad = $request->query('pfad'); // aus der Query
        $pfad = urldecode($pfad);
        //$projekt = Projekt::findOrFail(auth()->user()->current_team_id);

        if (!file_exists(storage_path($pfad))) {
            abort(404, 'Die gewünschte Datei wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }
        $teilnehmer = Personen::teilnehmer()->findOrFail($id);
                //dd($teilnehmer->adresses?->last()->strasse);

        $templateProcessor = new TemplateProcessor(storage_path($pfad));

        $templateProcessor->setValue('vorname', $teilnehmer->vorname);
        $templateProcessor->setValue('nachname', $teilnehmer->nachname);
        $templateProcessor->setValue('strasse', $teilnehmer->adresses->last()->strasse);
        $templateProcessor->setValue('hausnummer', $teilnehmer->adresses->last()->hausnummer);
        $templateProcessor->setValue('plz', $teilnehmer->adresses->last()->plz);
        $templateProcessor->setValue('stadt', $teilnehmer->adresses->last()->stadt);

        $templateProcessor->setValue('datum', now()->format('d.m.Y'));
        //$templateProcessor->setValue('projekt', $projekt->name);

        $outputPath = storage_path("app/temp/info_teilnehmende_{$id}.docx");
        $templateProcessor->saveAs($outputPath);

        return response()->download($outputPath)->deleteFileAfterSend(true);
    }

    public function datenschutzhinweis_art13(Request $request, $id)
    {
        $pfad = $request->query('pfad'); // aus der Query
        $pfad = urldecode($pfad);
        $projekt = Projekt::findOrFail(auth()->user()->current_team_id);

        if (!file_exists(storage_path($pfad))) {
            abort(404, 'Die gewünschte Datei wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }
        $teilnehmer = Personen::teilnehmer()->with('adresses')->findOrFail($id);

        $templateProcessor = new TemplateProcessor(storage_path($pfad));
        $templateProcessor->setValue('vorname', $teilnehmer->vorname);
        $templateProcessor->setValue('nachname', $teilnehmer->nachname);
        $templateProcessor->setValue('datum', now()->format('d.m.Y'));
        $templateProcessor->setValue('projekt', $projekt->name);

        $outputPath = storage_path("app/temp/datenschutzhinweis_art13_{$id}.docx");
        $templateProcessor->saveAs($outputPath);

        return response()->download($outputPath)->deleteFileAfterSend(true);
    }
    public function einverstaendnis_datenschutz_esf(Request $request, $id)
    {
        $pfad = $request->query('pfad'); // aus der Query
        $pfad = urldecode($pfad);
        $projekt = Projekt::findOrFail(auth()->user()->current_team_id);

        if (!file_exists(storage_path($pfad))) {
            abort(404, 'Die gewünschte Datei wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }
        $teilnehmer = Personen::teilnehmer()->with('adresses', 'projekte')->findOrFail($id);

         $teilnehmer->projekte->each(function ($projekt) {
            $projekt->pivotModel->load('zeitraume');
        });
        $proj = $teilnehmer->projekte->where('id', $projekt->id)->first();

        $letzterZeitraum = $proj->pivotModel
            ->zeitraume
            ->sortByDesc('antragsdatum')
            ->first();


        $templateProcessor = new TemplateProcessor(storage_path($pfad));
        $templateProcessor->setValue('vorname', $teilnehmer->vorname);
        $templateProcessor->setValue('nachname', $teilnehmer->nachname);
         $templateProcessor->setValue('strasse', $teilnehmer->adresses->last()->strasse);
        $templateProcessor->setValue('hausnummer', $teilnehmer->adresses->last()->hausnummer);
        $templateProcessor->setValue('plz', $teilnehmer->adresses->last()->plz);
        $templateProcessor->setValue('stadt', $teilnehmer->adresses->last()->stadt);
        $templateProcessor->setValue('projekt', $projekt->name);
        $templateProcessor->setValue('von', $letzterZeitraum->starttermin->format('d.m.Y'));
        $templateProcessor->setValue('bis', $letzterZeitraum->endtermin->format('d.m.Y'));
        $templateProcessor->setValue('datum', now()->format('d.m.Y'));

        $outputPath = storage_path("app/temp/einverstaendnis_datenschutz_esf_{$id}.docx");
        $templateProcessor->saveAs($outputPath);

        return response()->download($outputPath)->deleteFileAfterSend(true);
    }
}
