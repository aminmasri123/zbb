<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Projekt;
use App\Models\Personen;

use Illuminate\Http\Request;
use PhpOffice\PhpWord\PhpWord;
use App\Models\ProjektHasPersonen;
use PhpOffice\PhpWord\TemplateProcessor;

class ExportWordController extends Controller
{
        //dd($templateProcessor->getVariables());

    public function info_teilnehmende(Request $request, $id)
    {
        $pfad = urldecode($request->query('pfad'));

        // 🔹 Projekt inkl. Sozialpädagogen mit passenden Kontakten laden
        $projekt = Projekt::where('id', auth()->user()->current_team_id)
            ->with(['mitarbeiter' => function ($query) {
                $query->whereHas('user.roles', function ($q) {
                    $q->where('name', 'Sozialpädagoge');
                })
                ->with([
                    'kontaktes' => function ($q) {
                        $q->whereHas('kontakttyp', function ($t) {
                            $t->whereIn('name', ['Telefon']);
                        });
                    },
                    'user' => function ($q) {
                        $q->whereHas('roles', function ($r) {
                            $r->where('name', 'Sozialpädagoge');
                        })
                        ->with('person');
                    }
                ]);
            }])
            ->firstOrFail();
        if( !$projekt){
            abort(404, 'Das Projekt wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }

        // 🔹 Datei prüfen
        if (!file_exists(storage_path($pfad))) {
            abort(404, 'Die gewünschte Datei wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }

        // 🔹 Teilnehmer laden
        $teilnehmer = Personen::teilnehmer()->findOrFail($id);
        if( !$teilnehmer){
            abort(404, 'Der Teilnehmer wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }

        $standortAdresse = ProjektHasPersonen::where('personen_id', $teilnehmer->id)
            ->where('projekt_id', $projekt->id)
            ->first()->with('standort.adresse')
            ->first()->standort->adresse->first();

        if( !$standortAdresse ){
            return redirect()->back()->with('error', 'Bitte geben Sie das Projekt eine Adresse ein, bevor Sie den Export durchführen.');
        }else{
            $standortadresse = $standortAdresse->strasse . ' ' .
                $standortAdresse->hausnummer . ', ' .
                $standortAdresse->plz . ' ' .
                $standortAdresse->stadt;
        }


        // 🔹 Template laden
        $templateProcessor = new TemplateProcessor(storage_path($pfad));

        // 🔹 Nur Mitarbeiter mit User und Rolle "Sozialpädagoge"
        $sozialpaedagogen = $projekt->mitarbeiter
            ->filter(fn($m) => $m->user && $m->user->hasRole('Sozialpädagoge'))
            ->values();

        // 🔹 Sozialpädagogen ins Template einsetzen
        if ($sozialpaedagogen->isNotEmpty()) {
            foreach ($sozialpaedagogen as $index => $m) {
                $user = $m->user;
                $nr = $index + 1;

                $person = $user->person ?? null;
                $kontakt = $m->kontaktes->last(); // letzter (Mobile > Telefon)

                $templateProcessor->setValue("sozPadGeschlecht{$nr}", $person && $person->geschlecht === 'w' ? 'Frau' : 'Herr');
                $templateProcessor->setValue("sozPadNachname{$nr}", $person->nachname . ':' ?? '');
                $templateProcessor->setValue("sozPadTel{$nr}", $kontakt ? 'Tel.: ' . $kontakt->wert . ',' : '');
                $templateProcessor->setValue("sozPadEmail{$nr}", $user?->email ? 'Email: ' . $user->email : '');

            }

            // 🔸 übrige Platzhalter leeren (z. B. bis 5)
            for ($i = $sozialpaedagogen->count() + 1; $i <= 5; $i++) {
                $templateProcessor->setValue("sozPadGeschlecht{$i}", '');
                $templateProcessor->setValue("sozPadNachname{$i}", '');
                $templateProcessor->setValue("sozPadVorname{$i}", '');
                $templateProcessor->setValue("sozPadTel{$i}", '');
                $templateProcessor->setValue("sozPadEmail{$i}", '');
            }
        } else {
            // 🔸 Keine Sozialpädagogen vorhanden
            for ($i = 1; $i <= 5; $i++) {
                $templateProcessor->setValue("sozPadGeschlecht{$i}", '');
                $templateProcessor->setValue("sozPadNachname{$i}", '');
                $templateProcessor->setValue("sozPadVorname{$i}", '');
                $templateProcessor->setValue("sozPadTel{$i}", '');
                $templateProcessor->setValue("sozPadEmail{$i}", '');
            }
        }

        // 🔹 Teilnehmer- & Projektdaten
        $templateProcessor->setValue('vorname', $teilnehmer->vorname);
        $templateProcessor->setValue('nachname', $teilnehmer->nachname);
        $templateProcessor->setValue('datum', now()->format('d.m.Y'));
        $templateProcessor->setValue('projekt', $projekt->name ?? '');
        $templateProcessor->setValue('standortadresse', $standortadresse ?? '');



        // 🔹 Ausgabe & Download
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
        if( !$teilnehmer){
            abort(404, 'Der Teilnehmer wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }
        if( !$teilnehmer->adresses || $teilnehmer->adresses->isEmpty()){
            return redirect()->back()->with('error', 'Bitte geben Sie die Adresse des Teilnehmenden ein, bevor Sie den Export durchführen.');
        }

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
        if( !$projekt){
            abort(404, 'Das Projekt wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }
        if (!file_exists(storage_path($pfad))) {
            abort(404, 'Die gewünschte Datei wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }
        $teilnehmer = Personen::teilnehmer()->with('adresses')->findOrFail($id);
        if( !$teilnehmer){
            abort(404, 'Der Teilnehmer wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }

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
        if( !$teilnehmer){
            abort(404, 'Der Teilnehmer wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }

        if( !$teilnehmer->projekte || $teilnehmer->projekte->isEmpty()){
            return redirect()->back()->with('error', 'Dem Teilnehmer ist kein Projekt zugeordnet. Bitte weisen Sie dem Teilnehmer ein Projekt zu, bevor Sie den Export durchführen.');
        }
         $teilnehmer->projekte->each(function ($projekt) {
            $projekt->pivotModel->load('zeitraume');
        });
        if( !$teilnehmer->adresses || $teilnehmer->adresses->isEmpty()){
            return redirect()->back()->with('error', 'Bitte geben Sie die Adresse des Teilnehmenden ein, bevor Sie den Export durchführen.');
        }
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

    public function fehlzeitenkonzept(Request $request, $id)
    {
        $pfad = $request->query('pfad'); // aus der Query
        $pfad = urldecode($pfad);
        $projekt = Projekt::findOrFail(auth()->user()->current_team_id);
        if( !$projekt){
            abort(404, 'Das Projekt wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }
        if (!file_exists(storage_path($pfad))) {
            abort(404, 'Die gewünschte Datei wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }




        $templateProcessor = new TemplateProcessor(storage_path($pfad));


        $outputPath = storage_path("app/temp/fehlzeitenkonzept_{$id}.docx");
        $templateProcessor->saveAs($outputPath);

        return response()->download($outputPath)->deleteFileAfterSend(true);
    }

    public function einverstaendnis_foto(Request $request, $id)
    {

        $pfad = $request->query('pfad'); // aus der Query
        $pfad = urldecode($pfad);
        //$projekt = Projekt::findOrFail(auth()->user()->current_team_id);

        if (!file_exists(storage_path($pfad))) {
            abort(404, 'Die gewünschte Datei wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }

        $teilnehmer = Personen::teilnehmer()->findOrFail($id);
        if( !$teilnehmer){
            abort(404, 'Der Teilnehmer wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }
        if( !$teilnehmer->adresses || $teilnehmer->adresses->isEmpty()){
            return redirect()->back()->with('error', 'Bitte geben Sie die Adresse des Teilnehmenden ein, bevor Sie den Export durchführen.');
        }

        $templateProcessor = new TemplateProcessor(storage_path($pfad));

        $templateProcessor->setValue('vorname', $teilnehmer->vorname);
        $templateProcessor->setValue('nachname', $teilnehmer->nachname);
        $templateProcessor->setValue('strasse', $teilnehmer->adresses->last()->strasse . ' ' . $teilnehmer->adresses->last()->hausnummer) ;
        $templateProcessor->setValue('plz', $teilnehmer->adresses->last()->plz);
        $templateProcessor->setValue('ort', $teilnehmer->adresses->last()->stadt);



        $outputPath = storage_path("app/temp/einverstaendnis_foto_{$id}.docx");
        $templateProcessor->saveAs($outputPath);

        return response()->download($outputPath)->deleteFileAfterSend(true);
    }

    public function einverstaendnis_elternarbeit(Request $request, $id)
    {

        $pfad = $request->query('pfad'); // aus der Query
        $pfad = urldecode($pfad);
        //$projekt = Projekt::findOrFail(auth()->user()->current_team_id);

        if (!file_exists(storage_path($pfad))) {
            abort(404, 'Die gewünschte Datei wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }

        $teilnehmer = Personen::teilnehmer()->findOrFail($id);
        if( !$teilnehmer){
            abort(404, 'Der Teilnehmer wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }

        if( !$teilnehmer->adresses || $teilnehmer->adresses->isEmpty()){
            return redirect()->back()->with('error', 'Bitte geben Sie die Adresse des Teilnehmenden ein, bevor Sie den Export durchführen.');
        }
                //dd($teilnehmer->adresses?->last()->strasse);

        $templateProcessor = new TemplateProcessor(storage_path($pfad));

        $templateProcessor->setValue('vorname', $teilnehmer->vorname);
        $templateProcessor->setValue('nachname', $teilnehmer->nachname);
        $templateProcessor->setValue('strasse', $teilnehmer->adresses->last()->strasse . ' ' . $teilnehmer->adresses->last()->hausnummer) ;
        $templateProcessor->setValue('plz', $teilnehmer->adresses->last()->plz);
        $templateProcessor->setValue('ort', $teilnehmer->adresses->last()->stadt);



        $outputPath = storage_path("app/temp/einverstaendnis_elternarbeit_{$id}.docx");
        $templateProcessor->saveAs($outputPath);

        return response()->download($outputPath)->deleteFileAfterSend(true);
    }

    public function edv_nutzungsvereinbarung(Request $request, $id)
    {
        $pfad = $request->query('pfad'); // aus der Query
        $pfad = urldecode($pfad);
        //$projekt = Projekt::findOrFail(auth()->user()->current_team_id);

        if (!file_exists(storage_path($pfad))) {
            abort(404, 'Die gewünschte Datei wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }

        $teilnehmer = Personen::teilnehmer()->findOrFail($id);
        if( !$teilnehmer){
            abort(404, 'Der Teilnehmer wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }

        $templateProcessor = new TemplateProcessor(storage_path($pfad));

        $templateProcessor->setValue('vorname', $teilnehmer->vorname);
        $templateProcessor->setValue('nachname', $teilnehmer->nachname);

       $templateProcessor->setValue('datum', now()->format('d.m.Y'));


        $outputPath = storage_path("app/temp/edv_nutzungsvereinbarung{$id}.docx");
        $templateProcessor->saveAs($outputPath);

        return response()->download($outputPath)->deleteFileAfterSend(true);
    }
    public function hausordnung_v1(Request $request, $id)
    {
        $pfad = $request->query('pfad'); // aus der Query
        $pfad = urldecode($pfad);
        if (!file_exists(storage_path($pfad))) {
            abort(404, 'Die gewünschte Datei wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }

        $projekt = Projekt::findOrFail(auth()->user()->current_team_id);
        if( !$projekt){
            abort(404, 'Das Projekt wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }

        $teilnehmer = Personen::teilnehmer()->findOrFail($id);
        if( !$teilnehmer){
            abort(404, 'Der Teilnehmer wurde nicht gefunden. Bitte wenden Sie sich bei technischen Problemen an das Support-Team.');
        }

        $templateProcessor = new TemplateProcessor(storage_path($pfad));

        $templateProcessor->setValue('vorname', $teilnehmer->vorname);
        $templateProcessor->setValue('nachname', $teilnehmer->nachname);
        $templateProcessor->setValue('projekt', $projekt->name);

       $templateProcessor->setValue('datum', now()->format('d.m.Y'));


        $outputPath = storage_path("app/temp/hausordnung_v1_0{$id}.docx");
        $templateProcessor->saveAs($outputPath);

        return response()->download($outputPath)->deleteFileAfterSend(true);
    }
}
