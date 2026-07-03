<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Gruppe;
use App\Models\Dokumente;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\Settings as WordSettings;
use App\Models\ProjektHasPersonen;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;
use ZipArchive;

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

        $projektHasPersonen = ProjektHasPersonen::where('personen_id', $teilnehmer->id)
            ->where('projekt_id', $projekt->id)
            ->with('standort.adresse')
            ->first();

        $standortAdresse = null;
        if ($projektHasPersonen && $projektHasPersonen->standort && $projektHasPersonen->standort->adresse && $projektHasPersonen->standort->adresse->isNotEmpty()) {
            $standortAdresse = $projektHasPersonen->standort->adresse->first();
        }

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
        $templateProcessor->setValue('von', $letzterZeitraum?->starttermin->format('d.m.Y'));
        $templateProcessor->setValue('bis', $letzterZeitraum?->endtermin->format('d.m.Y'));
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

    public function gruppeSerienbrief(Request $request, Gruppe $gruppe, Dokumente $dokument)
    {
        abort_unless($this->canUseGroup(auth()->user(), $gruppe), 403);

        $projekt = $gruppe->projekt()->with(['dokumente', 'dokumentKategorien.dokumente'])->firstOrFail();
        $isAssigned = $this->isAssignedForGroupExport($projekt, $dokument);

        $dokument->loadMissing('bereiche');
        if (!$this->documentVisibleForGroup($dokument, $gruppe)) {
            return back()->with('error', 'Diese Vorlage ist fuer diese Gruppe nicht freigegeben.');
        }

        if (!$isAssigned) {
            return back()->with('error', 'Diese Vorlage ist fuer Gruppen-Exporte nicht freigegeben.');
        }

        if (!$dokument->dateipfad) {
            return back()->with('error', 'Diese Vorlage hat keinen Dateipfad.');
        }

        $format = $this->requestedExportFormat($request, $dokument);
        if (!$this->formatAllowed($dokument, $format)) {
            return back()->with('error', 'Dieses Ausgabeformat ist fuer die Vorlage nicht freigegeben.');
        }

        $templateFile = $this->storageTemplatePath($dokument->dateipfad);
        if (!file_exists($templateFile)) {
            return back()->with('error', 'Die Vorlage wurde nicht gefunden: ' . $dokument->dateipfad);
        }

        if ($dokument->typ === 'pdf') {
            return response()->download($templateFile, $this->safeFileName($dokument->name) . '.pdf');
        }

        $teilnehmer = $gruppe->teilnehmer()
            ->with(['adresses', 'kontaktes.kontakttyp', 'sozialedaten'])
            ->get()
            ->unique('id')
            ->values();

        if ($teilnehmer->isEmpty()) {
            return back()->with('error', 'Die Gruppe hat keine Teilnehmer fuer den Export.');
        }

        if ($dokument->typ === 'excel') {
            return $this->downloadExcelGruppenExport($templateFile, $gruppe, $projekt, $dokument, $teilnehmer, $format);
        }

        if ($dokument->typ !== 'word') {
            return back()->with('error', 'Dieser Vorlagentyp wird fuer Gruppen-Exporte noch nicht unterstuetzt.');
        }

        return $format === 'pdf'
            ? $this->downloadWordPdfZip($templateFile, $gruppe, $projekt, $dokument, $teilnehmer)
            : $this->downloadWordDocxZip($templateFile, $gruppe, $projekt, $dokument, $teilnehmer);
    }

    private function downloadWordDocxZip(string $templateFile, Gruppe $gruppe, Projekt $projekt, Dokumente $dokument, $teilnehmer)
    {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $zipPath = tempnam($tempDir, 'gruppe_serienbrief_');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Die ZIP-Datei konnte nicht erstellt werden.');
        }

        foreach ($teilnehmer as $index => $person) {
            $processor = new TemplateProcessor($templateFile);
            $this->fillSerienbriefTemplate($processor, $gruppe, $projekt, $person, $index + 1);

            $docName = $this->safeFileName(($index + 1) . '_' . $person->nachname . '_' . $person->vorname . '_' . $dokument->name) . '.docx';
            $docPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('serienbrief_', true) . '.docx';
            $processor->saveAs($docPath);

            $zip->addFile($docPath, $docName);
            register_shutdown_function(static function () use ($docPath) {
                if (file_exists($docPath)) {
                    @unlink($docPath);
                }
            });
        }

        $zip->close();

        $filename = $this->safeFileName('Serienbrief_' . $projekt->name . '_' . ($gruppe->bereich?->name ?? 'Gruppe') . '_' . $dokument->name) . '.zip';

        return response()->download($zipPath, $filename)->deleteFileAfterSend(true);
    }

    private function downloadWordPdfZip(string $templateFile, Gruppe $gruppe, Projekt $projekt, Dokumente $dokument, $teilnehmer)
    {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $zipPath = tempnam($tempDir, 'gruppe_pdf_');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Die ZIP-Datei konnte nicht erstellt werden.');
        }

        try {
            WordSettings::setPdfRendererName(WordSettings::PDF_RENDERER_DOMPDF);
            WordSettings::setPdfRendererPath(base_path('vendor/dompdf/dompdf'));

            foreach ($teilnehmer as $index => $person) {
                $processor = new TemplateProcessor($templateFile);
                $this->fillSerienbriefTemplate($processor, $gruppe, $projekt, $person, $index + 1);

                $docPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('serienbrief_pdf_', true) . '.docx';
                $pdfPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('serienbrief_pdf_', true) . '.pdf';
                $processor->saveAs($docPath);

                $phpWord = WordIOFactory::load($docPath);
                WordIOFactory::createWriter($phpWord, 'PDF')->save($pdfPath);

                $pdfName = $this->safeFileName(($index + 1) . '_' . $person->nachname . '_' . $person->vorname . '_' . $dokument->name) . '.pdf';
                $zip->addFile($pdfPath, $pdfName);

                register_shutdown_function(static function () use ($docPath, $pdfPath) {
                    foreach ([$docPath, $pdfPath] as $path) {
                        if (file_exists($path)) {
                            @unlink($path);
                        }
                    }
                });
            }
        } catch (Throwable $exception) {
            $zip->close();

            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }

            return back()->with('error', 'PDF-Export konnte nicht erstellt werden: ' . $exception->getMessage());
        }

        $zip->close();

        $filename = $this->safeFileName('PDF_Export_' . $projekt->name . '_' . ($gruppe->bereich?->name ?? 'Gruppe') . '_' . $dokument->name) . '.zip';

        return response()->download($zipPath, $filename)->deleteFileAfterSend(true);
    }

    private function downloadExcelGruppenExport(string $templateFile, Gruppe $gruppe, Projekt $projekt, Dokumente $dokument, $teilnehmer, string $format)
    {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        try {
            $spreadsheet = SpreadsheetIOFactory::load($templateFile);
            $this->fillSpreadsheetTemplate($spreadsheet, $gruppe, $projekt, $teilnehmer);

            $extension = $format === 'pdf' ? 'pdf' : 'xlsx';
            $outputPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('gruppe_excel_', true) . '.' . $extension;

            if ($format === 'pdf') {
                SpreadsheetIOFactory::createWriter($spreadsheet, 'Dompdf')->save($outputPath);
            } else {
                (new Xlsx($spreadsheet))->save($outputPath);
            }
        } catch (Throwable $exception) {
            return back()->with('error', 'Excel-Export konnte nicht erstellt werden: ' . $exception->getMessage());
        }

        $filename = $this->safeFileName('Export_' . $projekt->name . '_' . ($gruppe->bereich?->name ?? 'Gruppe') . '_' . $dokument->name) . '.' . $extension;

        return response()->download($outputPath, $filename)->deleteFileAfterSend(true);
    }

    private function fillSerienbriefTemplate(TemplateProcessor $processor, Gruppe $gruppe, Projekt $projekt, Personen $person, int $nummer): void
    {
        $values = $this->placeholderValues($gruppe, $projekt, $person, $nummer);

        foreach ($processor->getVariables() as $variable) {
            $processor->setValue($variable, $values[$variable] ?? $values[strtolower($variable)] ?? '');
        }
    }

    private function placeholderValues(Gruppe $gruppe, Projekt $projekt, ?Personen $person = null, int $nummer = 1): array
    {
        $adresse = $person?->adresses?->last();
        $betreuer = $gruppe->betreuer;
        $raum = $gruppe->raum;
        $email = $person?->kontaktes?->first(fn ($kontakt) => strtolower($kontakt->kontakttyp?->name ?? '') === 'email');
        $telefon = $person?->kontaktes?->first(fn ($kontakt) => in_array(strtolower($kontakt->kontakttyp?->name ?? ''), ['telefon', 'mobile', 'mobil'], true));

        return [
            'nr' => $nummer,
            'nummer' => $nummer,
            'datum' => now()->format('d.m.Y'),
            'heute' => now()->format('d.m.Y'),
            'vorname' => $person?->vorname,
            'nachname' => $person?->nachname,
            'name' => trim(($person?->nachname ?? '') . ', ' . ($person?->vorname ?? '')),
            'voller_name' => trim(($person?->vorname ?? '') . ' ' . ($person?->nachname ?? '')),
            'teilnehmer' => trim(($person?->vorname ?? '') . ' ' . ($person?->nachname ?? '')),
            'geburtsdatum' => $this->formatDate($person?->geburtsdatum),
            'geschlecht' => $person?->geschlecht,
            'anrede' => $person?->geschlecht === 'w' ? 'Frau' : ($person?->geschlecht === 'm' ? 'Herr' : ''),
            'strasse' => $adresse?->strasse,
            'hausnummer' => $adresse?->hausnummer,
            'plz' => $adresse?->plz,
            'stadt' => $adresse?->stadt,
            'ort' => $adresse?->stadt,
            'adresse' => trim(($adresse?->strasse ?? '') . ' ' . ($adresse?->hausnummer ?? '')),
            'email' => $email?->wert,
            'telefon' => $telefon?->wert,
            'kundennummer' => $person->sozialedaten?->kundennummer,
            'projekt' => $projekt->name,
            'projekt_name' => $projekt->name,
            'gruppe' => $gruppe->bereich?->name ?? ('Gruppe ' . $gruppe->id),
            'gruppe_id' => $gruppe->id,
            'bereich' => $gruppe->bereich?->name,
            'raum' => $raum?->name ?? $gruppe->externer_ort,
            'ort_typ' => $gruppe->ort_typ,
            'startdatum' => $this->formatDate($gruppe->anfangsdatum),
            'enddatum' => $this->formatDate($gruppe->enddatum),
            'von' => $this->formatDate($gruppe->anfangsdatum),
            'bis' => $this->formatDate($gruppe->enddatum),
            'startzeit' => substr((string) $gruppe->startzeit, 0, 5),
            'endzeit' => substr((string) $gruppe->endzeit, 0, 5),
            'betreuer' => trim(($betreuer?->vorname ?? '') . ' ' . ($betreuer?->nachname ?? '')),
            'betreuer_vorname' => $betreuer?->vorname,
            'betreuer_nachname' => $betreuer?->nachname,
        ];
    }

    private function fillSpreadsheetTemplate($spreadsheet, Gruppe $gruppe, Projekt $projekt, $teilnehmer): void
    {
        $gruppeValues = $this->placeholderValues($gruppe, $projekt);

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                $cell = $sheet->getCell($coordinate);
                $value = $cell->getValue();

                if (!is_string($value)) {
                    continue;
                }

                if ($this->containsParticipantTableMarker($value)) {
                    [$column, $row] = Coordinate::coordinateFromString($coordinate);
                    $columnIndex = Coordinate::columnIndexFromString($column);
                    $this->writeParticipantTable($sheet, $columnIndex, (int) $row, $gruppe, $projekt, $teilnehmer);
                    continue;
                }

                $cell->setValue($this->replacePlaceholderText($value, $gruppeValues));
            }
        }
    }

    private function containsParticipantTableMarker(string $value): bool
    {
        return str_contains($value, '${teilnehmer_tabelle}')
            || str_contains($value, '{{teilnehmer_tabelle}}');
    }

    private function writeParticipantTable($sheet, int $startColumn, int $startRow, Gruppe $gruppe, Projekt $projekt, $teilnehmer): void
    {
        $headers = ['Nr.', 'Vorname', 'Nachname', 'Geburtsdatum', 'Adresse', 'Telefon', 'E-Mail'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$startColumn + $index, $startRow], $header);
        }

        foreach ($teilnehmer as $index => $person) {
            $values = $this->placeholderValues($gruppe, $projekt, $person, $index + 1);
            $row = $startRow + $index + 1;
            $sheet->setCellValue([$startColumn, $row], $index + 1);
            $sheet->setCellValue([$startColumn + 1, $row], $values['vorname']);
            $sheet->setCellValue([$startColumn + 2, $row], $values['nachname']);
            $sheet->setCellValue([$startColumn + 3, $row], $values['geburtsdatum']);
            $sheet->setCellValue([$startColumn + 4, $row], trim(($values['adresse'] ?? '') . ', ' . ($values['plz'] ?? '') . ' ' . ($values['stadt'] ?? '')));
            $sheet->setCellValue([$startColumn + 5, $row], $values['telefon']);
            $sheet->setCellValue([$startColumn + 6, $row], $values['email']);
        }

        for ($column = $startColumn; $column <= $startColumn + count($headers) - 1; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
    }

    private function replacePlaceholderText(string $text, array $values): string
    {
        foreach ($values as $key => $value) {
            $text = str_replace(['${' . $key . '}', '{{' . $key . '}}'], (string) ($value ?? ''), $text);
        }

        return $text;
    }

    private function isAssignedForGroupExport(Projekt $projekt, Dokumente $dokument): bool
    {
        $direct = DB::table('projekt_has_dokumentes')
            ->where('projekt_id', $projekt->id)
            ->where('dokument_id', $dokument->id)
            ->where('gruppen_export', true)
            ->where('serienbrief', true)
            ->exists();

        if ($direct) {
            return true;
        }

        return DB::table('projekt_has_dokument_kategories')
            ->join('dokument_has_kategories', 'projekt_has_dokument_kategories.dokument_kategorie_id', '=', 'dokument_has_kategories.dokument_kategorie_id')
            ->where('projekt_has_dokument_kategories.projekt_id', $projekt->id)
            ->where('dokument_has_kategories.dokument_id', $dokument->id)
            ->where('dokument_has_kategories.gruppen_export', true)
            ->where('dokument_has_kategories.serienbrief', true)
            ->exists();
    }

    private function requestedExportFormat(Request $request, Dokumente $dokument): string
    {
        $format = strtolower((string) $request->query('format', ''));

        if ($format !== '') {
            return $format;
        }

        return match ($dokument->typ) {
            'excel' => 'xlsx',
            'pdf' => 'pdf',
            default => 'docx',
        };
    }

    private function formatAllowed(Dokumente $dokument, string $format): bool
    {
        $defaults = match ($dokument->typ) {
            'word' => ['docx', 'pdf'],
            'excel' => ['xlsx', 'pdf'],
            'pdf' => ['pdf'],
            default => [],
        };

        $allowed = $dokument->ausgabeformate ?: $defaults;

        return in_array($format, $allowed, true);
    }

    private function documentVisibleForGroup(Dokumente $dokument, Gruppe $gruppe): bool
    {
        if ($dokument->aktiv === false) {
            return false;
        }

        if (($dokument->einsatzbereich ?? 'gruppe') !== 'gruppe') {
            return false;
        }

        $bereichIds = $dokument->bereiche?->pluck('id') ?? collect();

        return $bereichIds->isEmpty() || $bereichIds->contains((int) $gruppe->bereich_id);
    }

    private function storageTemplatePath(string $path): string
    {
        return storage_path(ltrim($path, '/\\'));
    }

    private function formatDate($value): string
    {
        return $value ? date('d.m.Y', strtotime($value)) : '';
    }

    private function safeFileName(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9._ -]+/', '')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->replace(' ', '_')
            ->limit(160, '')
            ->toString();
    }

    private function canUseGroup($user, ?Gruppe $gruppe): bool
    {
        if (!$user || !$gruppe) {
            return false;
        }

        if ($user->can('gruppe.view.all') || $user->can('projekt.mitarbeiter.view.all')) {
            return true;
        }

        $personId = $user?->person_id ?? $user?->person?->id;

        return (int) $gruppe->personen_id === (int) $personId;
    }




}
