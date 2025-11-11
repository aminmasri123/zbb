<?php

namespace App\Http\Controllers;

use App\Models\Personen;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\PhpWord;

use PhpOffice\PhpWord\TemplateProcessor;

class ExportWordController extends Controller
{
    public function info_teilnehmende(Request $request, $id){

    $pfad = $request->query('pfad'); // aus der Query
    $pfad = urldecode($pfad);

    if (!file_exists(storage_path($pfad))) {
        abort(404, 'Die gewünschte Datei wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
    }
    $teilnehmer = Personen::teilnehmer()->findOrFail($id);

    $templateProcessor = new TemplateProcessor(storage_path($pfad));
    //dd($templateProcessor->getVariables());

    $templateProcessor->setValue('vorname', $teilnehmer->vorname);
    $templateProcessor->setValue('nachname', $teilnehmer->nachname);
    $templateProcessor->setValue('datum', now()->format('d.m.Y'));

    $outputPath = storage_path("app/temp/info_teilnehmende_{$id}.docx");
    $templateProcessor->saveAs($outputPath);

    return response()->download($outputPath)->deleteFileAfterSend(true);


    }
}
