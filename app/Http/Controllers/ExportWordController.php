<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Projekt;
use App\Models\Personen;
use App\Models\Gruppe;
use App\Models\Dokumente;
use App\Models\EinteilungSetting;
use App\Models\PaAttendanceListDraft;
use App\Models\PersonenIstSchueler;
use App\Models\Partner;
use App\Models\BopRun;
use App\Services\Documents\OfficeToPdfConverter;
use App\Services\Projects\ActiveProjectContext;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\ProjektHasPersonen;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;
use ZipArchive;

class ExportWordController extends Controller
{
    private array $schoolPlaceholderCache = [];

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
        abort_unless($this->canExportDocument(auth()->user(), $dokument), 403);

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
        $gruppe->setRelation('teilnehmer', $teilnehmer);

        if ($teilnehmer->isEmpty()) {
            return back()->with('error', 'Die Gruppe hat keine Teilnehmer fuer den Export.');
        }

        if ($dokument->typ === 'excel') {
            return $this->downloadExcelGruppenExport($templateFile, $gruppe, $projekt, $dokument, $teilnehmer, $format);
        }

        if ($dokument->typ !== 'word') {
            return back()->with('error', 'Dieser Vorlagentyp wird fuer Gruppen-Exporte noch nicht unterstuetzt.');
        }

        $groupExportMode = $dokument->gruppen_export_modus
            ?: ((($dokument->kontext ?? null) === 'gruppe' || $this->wordTemplateSupportsSingleGroupDocument($templateFile)) ? 'eine_datei' : 'einzelne_dateien');

        if ($groupExportMode === 'kopf') {
            return $this->downloadWordGroupDocument($templateFile, $gruppe, $projekt, $dokument, collect(), $format, false);
        }

        if ($groupExportMode === 'eine_datei') {
            return $this->downloadWordGroupDocument($templateFile, $gruppe, $projekt, $dokument, $teilnehmer, $format, true);
        }

        return $format === 'pdf'
            ? $this->downloadWordPdfZip($templateFile, $gruppe, $projekt, $dokument, $teilnehmer)
            : $this->downloadWordDocxZip($templateFile, $gruppe, $projekt, $dokument, $teilnehmer);
    }

    public function teilnehmerDokument(
        Request $request,
        Personen $personen,
        Dokumente $dokument,
        ActiveProjectContext $activeProjectContext
    )
    {
        $user = $request->user();
        $projekt = $user ? $activeProjectContext->currentAvailableFor($user) : null;

        abort_unless($projekt, 409, 'Bitte wählen Sie zuerst ein aktives Projekt aus.');
        abort_unless(
            Personen::query()
                ->teilnehmer()
                ->visibleForUser($user)
                ->whereKey($personen->id)
                ->whereHas('projekte', fn ($query) => $query->whereKey($projekt->id))
                ->exists(),
            403
        );
        abort_unless($dokument->aktiv !== false, 404);
        abort_unless(($dokument->einsatzbereich ?? null) === 'teilnehmer', 404);
        abort_unless(($dokument->kontext ?? null) === 'teilnehmer', 404);
        abort_unless($this->isAssignedToProject($projekt, $dokument), 404);
        abort_unless($this->canExportDocument($user, $dokument), 403);

        if (!$dokument->dateipfad) {
            return back()->with('error', 'Diese Vorlage hat keinen Dateipfad.');
        }

        $format = $this->requestedExportFormat($request, $dokument);
        if (!$this->formatAllowed($dokument, $format)) {
            return back()->with('error', 'Dieses Ausgabeformat ist für die Vorlage nicht freigegeben.');
        }

        $templateFile = $this->storageTemplatePath($dokument->dateipfad);
        if (!file_exists($templateFile)) {
            return back()->with('error', 'Die Vorlage wurde nicht gefunden: ' . $dokument->dateipfad);
        }

        $personen->loadMissing(['adresses', 'kontaktes.kontakttyp', 'sozialedaten']);

        $teilnahme = ProjektHasPersonen::query()
            ->with(['meta.betreuer', 'zeitraume'])
            ->where('projekt_id', $projekt->id)
            ->where('personen_id', $personen->id)
            ->firstOrFail();

        if ($dokument->typ === 'pdf') {
            return response()->download(
                $templateFile,
                $this->safeFileName($dokument->name . '_' . $personen->vorname . '_' . $personen->nachname) . '.pdf'
            );
        }

        $gruppe = $this->participantContextGroup($projekt, $personen);

        return $this->downloadParticipantTemplate(
            $templateFile,
            $gruppe,
            $projekt,
            $personen,
            $dokument,
            $format,
            $teilnahme
        );
    }

    public function partnerDokument(
        Request $request,
        Partner $partner,
        Dokumente $dokument,
        ActiveProjectContext $activeProjectContext
    ) {
        $context = $request->validate([
            'schuljahr' => ['required', 'string', 'max:20'],
            'teil' => ['required', 'string', 'max:20'],
            'format' => ['nullable', 'string', 'in:docx,xlsx,pdf'],
        ]);

        $user = $request->user();
        $projekt = $user ? $activeProjectContext->currentAvailableFor($user) : null;

        abort_unless($projekt, 409, 'Bitte wählen Sie zuerst ein aktives Projekt aus.');
        abort_unless($projekt->partners()->whereKey($partner->id)->exists(), 404);
        abort_unless($dokument->aktiv !== false, 404);
        abort_unless(($dokument->einsatzbereich ?? 'gruppe') === 'partner', 404);
        abort_unless(($dokument->kontext ?? null) === 'partner', 404);
        abort_unless($this->isAssignedToProject($projekt, $dokument), 404);
        abort_unless($this->canExportPartnerDocument($user, $dokument), 403);

        if (!$dokument->dateipfad) {
            return back()->with('error', 'Diese Vorlage hat keinen Dateipfad.');
        }

        $format = $this->requestedExportFormat($request, $dokument);
        if (!$this->formatAllowed($dokument, $format)) {
            return back()->with('error', 'Dieses Ausgabeformat ist für die Vorlage nicht freigegeben.');
        }

        $templateFile = $this->storageTemplatePath($dokument->dateipfad);
        if (!file_exists($templateFile)) {
            return back()->with('error', 'Die Vorlage wurde nicht gefunden: ' . $dokument->dateipfad);
        }

        if ($dokument->typ === 'pdf') {
            return response()->download(
                $templateFile,
                $this->safeFileName($dokument->name . '_' . $partner->name) . '.pdf'
            );
        }

        $studentRows = PersonenIstSchueler::query()
            ->where('schule_id', $partner->id)
            ->forSchuljahr($context['schuljahr'])
            ->where('teil', $context['teil'])
            ->with(['person.adresses', 'person.kontaktes.kontakttyp', 'person.sozialedaten'])
            ->get();

        $teilnehmer = $studentRows->pluck('person')->filter()->unique('id')->values();
        $partner->loadMissing(['adresses', 'kontaktes.kontakttyp']);

        $gruppe = new Gruppe([
            'projekt_id' => $projekt->id,
            'partner_id' => $partner->id,
        ]);
        $gruppe->setAttribute('export_schuljahr', $context['schuljahr']);
        $gruppe->setAttribute('export_teil', $context['teil']);
        $gruppe->setRelation('partner', $partner);
        $gruppe->setRelation('partners', collect([$partner]));
        $gruppe->setRelation('teilnehmer', $teilnehmer);
        $gruppe->setRelation('betreuer', null);
        $gruppe->setRelation('raum', null);
        $gruppe->setRelation('bereich', null);

        return $this->downloadPartnerTemplate($templateFile, $gruppe, $projekt, $partner, $dokument, $format);
    }

    private function downloadPartnerTemplate(
        string $templateFile,
        Gruppe $gruppe,
        Projekt $projekt,
        Partner $partner,
        Dokumente $dokument,
        string $format
    ) {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        try {
            if ($dokument->typ === 'word') {
                $docPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('partner_word_', true) . '.docx';
                $processor = new TemplateProcessor($templateFile);
                if ($error = $this->groupPlaceholderValidationError(
                    $processor->getVariables(),
                    $gruppe,
                    $projekt,
                    collect(),
                    false,
                    false
                )) {
                    return back()->with('error', $error);
                }
                $this->fillGroupTemplate($processor, $gruppe, $projekt, collect(), false);
                $processor->saveAs($docPath);

                if ($format === 'pdf') {
                    $outputPath = app(OfficeToPdfConverter::class)->convert($docPath, $tempDir);
                    @unlink($docPath);
                } else {
                    $outputPath = $docPath;
                }
            } elseif ($dokument->typ === 'excel') {
                $spreadsheet = SpreadsheetIOFactory::load($templateFile);
                if ($error = $this->groupPlaceholderValidationError(
                    $this->spreadsheetPlaceholderVariables($spreadsheet),
                    $gruppe,
                    $projekt,
                    collect(),
                    false,
                    true
                )) {
                    return back()->with('error', $error);
                }
                $this->fillSpreadsheetTemplate($spreadsheet, $gruppe, $projekt, collect());
                $extension = $format === 'pdf' ? 'pdf' : 'xlsx';
                $outputPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('partner_excel_', true) . '.' . $extension;
                SpreadsheetIOFactory::createWriter($spreadsheet, $format === 'pdf' ? 'Dompdf' : 'Xlsx')->save($outputPath);
            } else {
                return back()->with('error', 'Dieser Vorlagentyp wird für Partner-Exporte nicht unterstützt.');
            }
        } catch (Throwable $exception) {
            if (isset($docPath) && file_exists($docPath)) {
                @unlink($docPath);
            }
            if (isset($outputPath) && file_exists($outputPath)) {
                @unlink($outputPath);
            }

            return back()->with('error', 'Partnerdokument konnte nicht erstellt werden: ' . $exception->getMessage());
        }

        $extension = pathinfo($outputPath, PATHINFO_EXTENSION);
        $filename = $this->safeFileName($dokument->name . '_' . $partner->name) . '.' . $extension;

        return response()->download($outputPath, $filename)->deleteFileAfterSend(true);
    }

    private function downloadParticipantTemplate(
        string $templateFile,
        Gruppe $gruppe,
        Projekt $projekt,
        Personen $personen,
        Dokumente $dokument,
        string $format,
        ProjektHasPersonen $teilnahme
    ) {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $outputPath = null;
        $docPath = null;

        try {
            if ($dokument->typ === 'word') {
                $docPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('teilnehmer_word_', true) . '.docx';
                $processor = new TemplateProcessor($templateFile);
                $values = $this->placeholderValues($gruppe, $projekt, $personen, 1, $teilnahme);
                if ($error = $this->singleParticipantPlaceholderValidationError($processor->getVariables(), $values, $personen)) {
                    return back()->with('error', $error);
                }
                $this->fillSerienbriefTemplate($processor, $gruppe, $projekt, $personen, 1, $teilnahme);
                $processor->saveAs($docPath);

                if ($format === 'pdf') {
                    $outputPath = app(OfficeToPdfConverter::class)->convert($docPath, $tempDir);
                    @unlink($docPath);
                } else {
                    $outputPath = $docPath;
                }
            } elseif ($dokument->typ === 'excel') {
                $spreadsheet = SpreadsheetIOFactory::load($templateFile);
                $values = $this->placeholderValues($gruppe, $projekt, $personen, 1, $teilnahme);
                if ($error = $this->singleParticipantPlaceholderValidationError(
                    $this->spreadsheetPlaceholderVariables($spreadsheet),
                    $values,
                    $personen
                )) {
                    return back()->with('error', $error);
                }
                $this->fillParticipantSpreadsheetTemplate($spreadsheet, $gruppe, $projekt, $personen, $teilnahme);
                $extension = $format === 'pdf' ? 'pdf' : 'xlsx';
                $outputPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('teilnehmer_excel_', true) . '.' . $extension;
                SpreadsheetIOFactory::createWriter($spreadsheet, $format === 'pdf' ? 'Dompdf' : 'Xlsx')->save($outputPath);
            } else {
                return back()->with('error', 'Dieser Vorlagentyp wird für Teilnehmerexporte nicht unterstützt.');
            }
        } catch (Throwable $exception) {
            foreach (array_unique(array_filter([$docPath, $outputPath])) as $path) {
                if (file_exists($path)) {
                    @unlink($path);
                }
            }

            return back()->with('error', 'Teilnehmerdokument konnte nicht erstellt werden: ' . $exception->getMessage());
        }

        $extension = pathinfo($outputPath, PATHINFO_EXTENSION);
        $filename = $this->safeFileName(
            $dokument->name . '_' . $personen->vorname . '_' . $personen->nachname
        ) . '.' . $extension;

        return response()->download($outputPath, $filename)->deleteFileAfterSend(true);
    }

    private function participantContextGroup(Projekt $projekt, Personen $personen): Gruppe
    {
        $gruppe = Gruppe::query()
            ->with([
                'bereich',
                'raum',
                'betreuer',
                'partner.adresses',
                'partner.kontaktes.kontakttyp',
                'partners.adresses',
                'partners.kontaktes.kontakttyp',
            ])
            ->where('projekt_id', $projekt->id)
            ->whereHas('teilnehmer', fn ($query) => $query->whereKey($personen->id))
            ->orderByDesc('enddatum')
            ->orderByDesc('id')
            ->first();

        if ($gruppe) {
            return $gruppe;
        }

        $gruppe = new Gruppe(['projekt_id' => $projekt->id]);
        $gruppe->setRelation('bereich', null);
        $gruppe->setRelation('raum', null);
        $gruppe->setRelation('betreuer', null);
        $gruppe->setRelation('partner', null);
        $gruppe->setRelation('partners', collect());

        return $gruppe;
    }

    private function fillParticipantSpreadsheetTemplate(
        $spreadsheet,
        Gruppe $gruppe,
        Projekt $projekt,
        Personen $personen,
        ProjektHasPersonen $teilnahme
    ): void {
        $values = $this->placeholderValues($gruppe, $projekt, $personen, 1, $teilnahme);

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                $cell = $sheet->getCell($coordinate);
                $value = $cell->getValue();

                if (!is_string($value)) {
                    continue;
                }

                $replacedValue = $this->replacePlaceholderText($value, $values);
                if ($replacedValue !== $value) {
                    $cell->setValueExplicit($replacedValue, DataType::TYPE_STRING);
                }
            }
        }
    }

    private function wordTemplateSupportsSingleGroupDocument(string $templateFile): bool
    {
        try {
            $processor = new TemplateProcessor($templateFile);
            $variables = collect($processor->getVariables())->map(fn ($value) => strtolower((string) $value));
        } catch (Throwable) {
            return false;
        }

        return $variables->contains('teilnehmer_tabelle')
            || $variables->contains(fn ($variable) => preg_match('/^(nr|nummer|vorname|nachname|name|voller_name|geburtsdatum|geschlecht|anrede|kundennummer|strasse|hausnummer|plz|stadt|ort|adresse|email|telefon)\d+$/', $variable) === 1);
    }

    private function downloadWordGroupDocument(string $templateFile, Gruppe $gruppe, Projekt $projekt, Dokumente $dokument, $teilnehmer, string $format, bool $fillParticipants = true)
    {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $extension = $format === 'pdf' ? 'pdf' : 'docx';
        $docPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('gruppe_word_', true) . '.docx';
        $outputPath = $docPath;

        try {
            $processor = new TemplateProcessor($templateFile);
            if ($error = $this->groupPlaceholderValidationError(
                $processor->getVariables(),
                $gruppe,
                $projekt,
                $teilnehmer,
                $fillParticipants,
                false
            )) {
                return back()->with('error', $error);
            }
            $this->fillGroupTemplate($processor, $gruppe, $projekt, $teilnehmer, $fillParticipants);
            $processor->saveAs($docPath);

            if ($format === 'pdf') {
                $outputPath = app(OfficeToPdfConverter::class)->convert($docPath, $tempDir);

                register_shutdown_function(static function () use ($docPath) {
                    if (file_exists($docPath)) {
                        @unlink($docPath);
                    }
                });
            }
        } catch (Throwable $exception) {
            foreach ([$docPath, $outputPath] as $path) {
                if ($path && file_exists($path)) {
                    @unlink($path);
                }
            }

            return back()->with('error', 'Gruppenexport konnte nicht erstellt werden: ' . $exception->getMessage());
        }

        $filename = $this->safeFileName('Export_' . $projekt->name . '_' . ($gruppe->bereich?->name ?? 'Gruppe') . '_' . $this->formatDate($gruppe->anfangsdatum) . '_bis_' . $this->formatDate($gruppe->enddatum) . '_' . $dokument->name) . '.' . $extension;

        return response()->download($outputPath, $filename)->deleteFileAfterSend(true);
    }

    private function downloadWordDocxZip(string $templateFile, Gruppe $gruppe, Projekt $projekt, Dokumente $dokument, $teilnehmer)
    {
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $variables = (new TemplateProcessor($templateFile))->getVariables();
        if ($error = $this->groupPlaceholderValidationError(
            $variables,
            $gruppe,
            $projekt,
            $teilnehmer,
            true,
            false
        )) {
            return back()->with('error', $error);
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

        $variables = (new TemplateProcessor($templateFile))->getVariables();
        if ($error = $this->groupPlaceholderValidationError(
            $variables,
            $gruppe,
            $projekt,
            $teilnehmer,
            true,
            false
        )) {
            return back()->with('error', $error);
        }

        $zipPath = tempnam($tempDir, 'gruppe_pdf_');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Die ZIP-Datei konnte nicht erstellt werden.');
        }

        try {
            foreach ($teilnehmer as $index => $person) {
                $processor = new TemplateProcessor($templateFile);
                $this->fillSerienbriefTemplate($processor, $gruppe, $projekt, $person, $index + 1);

                $docPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('serienbrief_pdf_', true) . '.docx';
                $processor->saveAs($docPath);

                $pdfPath = app(OfficeToPdfConverter::class)->convert($docPath, $tempDir);

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
            if ($error = $this->groupPlaceholderValidationError(
                $this->spreadsheetPlaceholderVariables($spreadsheet),
                $gruppe,
                $projekt,
                $teilnehmer,
                true,
                true
            )) {
                return back()->with('error', $error);
            }
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

    private function fillSerienbriefTemplate(
        TemplateProcessor $processor,
        Gruppe $gruppe,
        Projekt $projekt,
        Personen $person,
        int $nummer,
        ?ProjektHasPersonen $teilnahme = null
    ): void
    {
        $values = $this->placeholderValues($gruppe, $projekt, $person, $nummer, $teilnahme);

        foreach ($processor->getVariables() as $variable) {
            $processor->setValue($variable, $values[$variable] ?? $values[strtolower($variable)] ?? '');
        }
    }

    private function fillGroupTemplate(TemplateProcessor $processor, Gruppe $gruppe, Projekt $projekt, $teilnehmer, bool $fillParticipants = true): void
    {
        if ($fillParticipants) {
            $this->cloneWordParticipantRows($processor, $gruppe, $projekt, $teilnehmer);
        }

        $groupValues = $this->placeholderValues($gruppe, $projekt);
        $indexedVariables = $this->indexedParticipantVariables($processor->getVariables());
        $maxIndex = max($indexedVariables->keys()->all() ?: [0]);

        foreach ($processor->getVariables() as $variable) {
            $lowerVariable = strtolower((string) $variable);

            if (str_contains($lowerVariable, '#')) {
                continue;
            }

            if ($lowerVariable === 'teilnehmer_tabelle') {
                $processor->setValue($variable, '');
                continue;
            }

            if (preg_match('/^([a-z_]+)(\d+)$/', $lowerVariable, $matches)) {
                $index = (int) $matches[2];
                $person = $fillParticipants ? $teilnehmer->get($index - 1) : null;
                $values = $person ? $this->placeholderValues($gruppe, $projekt, $person, $index) : [];
                $processor->setValue($variable, $values[$matches[1]] ?? '');
                continue;
            }

            $processor->setValue($variable, $groupValues[$lowerVariable] ?? '');
        }

        if ($maxIndex > 0) {
            for ($index = $teilnehmer->count() + 1; $index <= $maxIndex; $index++) {
                foreach ($indexedVariables->get($index, []) as $variable) {
                    $processor->setValue($variable, '');
                }
            }
        }
    }

    private function cloneWordParticipantRows(TemplateProcessor $processor, Gruppe $gruppe, Projekt $projekt, $teilnehmer): void
    {
        $rowKeys = ['nr', 'nummer', 'vorname', 'nachname', 'name', 'voller_name', 'geburtsdatum', 'geschlecht', 'anrede', 'kundennummer', 'strasse', 'hausnummer', 'plz', 'stadt', 'ort', 'adresse', 'email', 'telefon'];
        $variables = collect($processor->getVariables())->map(fn ($value) => strtolower((string) $value));
        $cloneKey = collect($rowKeys)->first(fn ($key) => $variables->contains($key));

        if (!$cloneKey) {
            return;
        }

        try {
            $processor->cloneRow($cloneKey, max(1, $teilnehmer->count()));
        } catch (Throwable) {
            return;
        }

        foreach ($teilnehmer as $index => $person) {
            $number = $index + 1;
            $values = $this->placeholderValues($gruppe, $projekt, $person, $number);

            foreach ($rowKeys as $key) {
                $processor->setValue($key . '#' . $number, $values[$key] ?? '');
            }
        }
    }

    private function indexedParticipantVariables(array $variables)
    {
        return collect($variables)
            ->filter(fn ($variable) => preg_match('/^[a-z_]+\d+$/', strtolower((string) $variable)) === 1)
            ->groupBy(fn ($variable) => (int) preg_replace('/^\D+/', '', (string) $variable));
    }

    private function placeholderValues(
        Gruppe $gruppe,
        Projekt $projekt,
        ?Personen $person = null,
        int $nummer = 1,
        ?ProjektHasPersonen $teilnahme = null
    ): array
    {
        $adresse = $person?->adresses?->last();
        $betreuer = $teilnahme ? $teilnahme->meta?->betreuer : $gruppe->betreuer;
        $zeitraum = $teilnahme?->zeitraume?->sortByDesc('id')->first();
        $terminDatum = $this->formatDate($zeitraum?->starttermin);
        $terminUhrzeit = $this->formatPlaceholderTime($zeitraum?->startzeit);
        $betreuerAnrede = $betreuer?->geschlecht === 'w' ? 'Frau' : ($betreuer?->geschlecht === 'm' ? 'Herr' : '');
        $betreuerAnredeDativ = $betreuer?->geschlecht === 'w' ? 'Frau' : ($betreuer?->geschlecht === 'm' ? 'Herrn' : '');
        $raum = $gruppe->raum;
        $email = $person?->kontaktes?->first(fn ($kontakt) => strtolower($kontakt->kontakttyp?->name ?? '') === 'email');
        $telefon = $person?->kontaktes?->first(fn ($kontakt) => in_array(strtolower($kontakt->kontakttyp?->name ?? ''), ['telefon', 'mobile', 'mobil'], true));
        $partnerValues = $this->partnerPlaceholderValues($gruppe, $projekt);

        return array_merge([
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
            'kundennummer' => $person?->sozialedaten?->kundennummer,
            'projekt' => $projekt->name,
            'projekt_name' => $projekt->name,
            'gruppe' => $gruppe->bereich?->name ?? ($gruppe->exists ? 'Gruppe ' . $gruppe->id : ''),
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
            'betreuer_name' => trim(($betreuer?->vorname ?? '') . ' ' . ($betreuer?->nachname ?? '')),
            'betreuer_anrede' => $betreuerAnrede,
            'betreuer_anrede_dativ' => $betreuerAnredeDativ,
            'betreuer_vorname' => $betreuer?->vorname,
            'betreuer_nachname' => $betreuer?->nachname,
            'termin_datum' => $terminDatum,
            'termin_uhrzeit' => $terminUhrzeit,
            'termin' => $terminDatum && $terminUhrzeit ? $terminDatum . ' um ' . $terminUhrzeit . ' Uhr' : '',
            'erstgespraech_datum' => $terminDatum,
            'erstgespraech_uhrzeit' => $terminUhrzeit,
        ], $partnerValues);
    }

    private function partnerPlaceholderValues(Gruppe $gruppe, Projekt $projekt): array
    {
        $gruppe->loadMissing([
            'partner.adresses',
            'partner.kontaktes.kontakttyp',
            'partners.adresses',
            'partners.kontaktes.kontakttyp',
        ]);

        $partners = $gruppe->partners;
        $hauptpartner = $gruppe->partner;

        if ($hauptpartner && !$partners->contains('id', $hauptpartner->id)) {
            $partners = $partners->prepend($hauptpartner);
        }

        $partners = $partners
            ->unique('id')
            ->sortBy(fn ($partner) => mb_strtolower((string) $partner->name))
            ->values();

        $hauptpartner ??= $partners->first();
        $adresse = $hauptpartner?->adresses?->last();
        $email = $hauptpartner?->kontaktes?->first(
            fn ($kontakt) => in_array(mb_strtolower(trim((string) ($kontakt->kontakttyp?->name ?? ''))), ['email', 'e-mail'], true)
        );
        $telefon = $hauptpartner?->kontaktes?->first(
            fn ($kontakt) => in_array(mb_strtolower(trim((string) ($kontakt->kontakttyp?->name ?? ''))), ['telefon', 'mobile', 'mobil'], true)
        );

        return array_merge([
            'partner' => $hauptpartner?->name,
            'partner_name' => $hauptpartner?->name,
            'partner_beschreibung' => $hauptpartner?->beschreibung,
            'partner_adresse' => trim(($adresse?->strasse ?? '') . ' ' . ($adresse?->hausnummer ?? '')),
            'partner_strasse' => $adresse?->strasse,
            'partner_hausnummer' => $adresse?->hausnummer,
            'partner_plz' => $adresse?->plz,
            'partner_stadt' => $adresse?->stadt,
            'partner_email' => $email?->wert,
            'partner_telefon' => $telefon?->wert,
            'partner_liste' => $partners->pluck('name')->filter()->implode(', '),
        ], $this->schoolPlaceholderValues($gruppe, $projekt, $hauptpartner));
    }

    private function schoolPlaceholderValues(Gruppe $gruppe, Projekt $projekt, $hauptpartner): array
    {
        $emptyValues = [
            'schulform' => '',
            'schuljahr' => '',
            'teil' => '',
            'klassen' => '',
            'klassen_liste' => '',
            'zeitraum' => '',
            'zeitraum_von' => '',
            'zeitraum_bis' => '',
            'vorbereitung_pa_datum' => '',
            'pa_datum' => '',
            'pa_daten' => '',
            'feedbackgespraech_pa_datum' => '',
            'rolltag_datum' => '',
            'werkstatttage_daten' => '',
            'werkstatttage_gesamt_daten' => '',
            'wt_daten' => '',
            'feedbackgespraech_wt_datum' => '',
            'feedbackgespraech_datum' => '',
            'auswertungsgespraech_datum' => '',
        ];

        if (!$hauptpartner?->getKey() || !$projekt->getKey()) {
            return $emptyValues;
        }

        $requestedSchuljahr = trim((string) $gruppe->getAttribute('export_schuljahr'));
        $requestedTeil = trim((string) $gruppe->getAttribute('export_teil'));

        $cacheKey = implode(':', [
            (string) $projekt->getKey(),
            (string) ($gruppe->getKey() ?? spl_object_id($gruppe)),
            (string) $hauptpartner->getKey(),
            $requestedSchuljahr,
            $requestedTeil,
        ]);

        if (array_key_exists($cacheKey, $this->schoolPlaceholderCache)) {
            return $this->schoolPlaceholderCache[$cacheKey];
        }

        $personIds = $gruppe->relationLoaded('teilnehmer')
            ? $gruppe->teilnehmer->pluck('id')->filter()->unique()->values()
            : ($gruppe->exists
                ? $gruppe->teilnehmer()->pluck('personens.id')->filter()->unique()->values()
                : collect());

        $contextRows = PersonenIstSchueler::query()
            ->where('schule_id', $hauptpartner->getKey())
            ->when($personIds->isNotEmpty(), fn ($query) => $query->whereIn('person_id', $personIds))
            ->when($requestedSchuljahr !== '', fn ($query) => $query->forSchuljahr($requestedSchuljahr))
            ->when($requestedTeil !== '', fn ($query) => $query->where('teil', $requestedTeil))
            ->orderByDesc('id')
            ->get();

        if ($contextRows->isEmpty()) {
            $dateValues = ($requestedSchuljahr !== '' && $requestedTeil !== '')
                ? $this->schoolDatePlaceholderValues(
                    $gruppe,
                    $projekt,
                    (int) $hauptpartner->getKey(),
                    $requestedSchuljahr,
                    $requestedTeil
                )
                : [];

            return $this->schoolPlaceholderCache[$cacheKey] = array_merge($emptyValues, [
                'schuljahr' => $requestedSchuljahr,
                'teil' => $requestedTeil,
            ], $dateValues);
        }

        $context = $contextRows
            ->groupBy(fn ($row) => (string) $row->schuljahr . "\0" . (string) $row->teil)
            ->sortByDesc(fn ($rows) => $rows->count())
            ->first()
            ?->first();

        if (!$context) {
            return $this->schoolPlaceholderCache[$cacheKey] = $emptyValues;
        }

        $schoolRows = PersonenIstSchueler::query()
            ->where('schule_id', $hauptpartner->getKey())
            ->forSchuljahr($context->schuljahr)
            ->where('teil', $context->teil)
            ->get();

        $foerderCount = $schoolRows->filter(
            fn ($row) => (bool) ($row->foerderschueler ?? $row->foederschueler ?? false)
        )->count();
        $schulform = $schoolRows->isNotEmpty() && ($foerderCount / $schoolRows->count()) > 0.5
            ? 'Förderschule'
            : 'Gemeinschaftsschule';

        $klassen = $schoolRows
            ->pluck('klasse')
            ->map(fn ($klasse) => trim((string) $klasse))
            ->filter()
            ->uniqueStrict(fn ($klasse) => mb_strtolower($klasse))
            ->sort(fn ($klasseA, $klasseB) => strnatcasecmp($klasseA, $klasseB))
            ->values()
            ->implode(' + ');

        $dateValues = $this->schoolDatePlaceholderValues(
            $gruppe,
            $projekt,
            (int) $hauptpartner->getKey(),
            (string) $context->schuljahr,
            (string) $context->teil
        );

        return $this->schoolPlaceholderCache[$cacheKey] = array_merge($emptyValues, [
            'schulform' => $schulform,
            'schuljahr' => $context->schuljahr,
            'teil' => $context->teil,
            'klassen' => $klassen,
            'klassen_liste' => $klassen,
        ], $dateValues);
    }

    private function schoolDatePlaceholderValues(
        Gruppe $gruppe,
        Projekt $projekt,
        int $partnerId,
        string $schuljahr,
        string $teil
    ): array {
        $bopAllDates = collect();
        $bopPreparationDates = collect();
        $bopFeedbackDates = collect();
        $bopPaDates = collect();
        $bopPaFeedbackDates = collect();
        $bopRollDayDates = collect();
        $bopWorkshopDates = collect();
        $bopWorkshopAllDates = collect();
        $bopWtFeedbackDates = collect();
        $allDates = collect();
        $preparationDates = collect();
        $feedbackDates = collect();

        $normalisePart = fn ($value) => trim((string) preg_replace('/^Teil\s*/i', '', (string) $value));
        $normalisedPart = $normalisePart($teil);
        $run = BopRun::query()
            ->where('projekt_id', $projekt->getKey())
            ->where('partner_id', $partnerId)
            ->forSchuljahr($schuljahr)
            ->whereIn('teil', array_values(array_unique([$teil, $normalisedPart, 'Teil ' . $normalisedPart, '_all'])))
            ->with('phases')
            ->orderByRaw('CASE WHEN teil = ? THEN 0 WHEN teil = ? THEN 1 WHEN teil = ? THEN 2 ELSE 3 END', [
                $teil,
                $normalisedPart,
                'Teil ' . $normalisedPart,
            ])
            ->first();

        if ($run) {
            $plannedClassesForPart = collect($run->planned_classes ?? [])
                ->filter(fn ($class) => $normalisePart($class['part'] ?? '1') === $normalisedPart)
                ->pluck('name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->values();

            foreach ($run->phases as $phase) {
                $phaseDates = collect($phase->dates ?? []);
                if ($phase->phase_type === 'workshop_days') {
                    $bopWorkshopAllDates->push(...$phaseDates
                        ->map(fn ($date) => $this->normalizePlaceholderDate($date))
                        ->filter());
                }
                $partAssignments = collect($phase->part_date_assignments ?? [])
                    ->mapWithKeys(fn ($dates, $part) => [$normalisePart($part) => $dates]);

                if ($partAssignments->has($normalisedPart)) {
                    $phaseDates = collect($partAssignments->get($normalisedPart, []));
                } elseif (!empty($phase->class_date_assignments) && $plannedClassesForPart->isNotEmpty()) {
                    $classAssignments = collect($phase->class_date_assignments ?? []);
                    $phaseDates = $plannedClassesForPart
                        ->flatMap(fn ($className) => $classAssignments->get($className, []));
                }

                $phaseDates = $phaseDates
                    ->map(fn ($date) => $this->normalizePlaceholderDate($date))
                    ->filter()
                    ->unique()
                    ->values();
                $bopAllDates->push(...$phaseDates);

                if ($phase->phase_type === 'pa_preparation') {
                    $bopPreparationDates->push(...$phaseDates);
                }
                if ($phase->phase_type === 'pa') {
                    $bopPaDates->push(...$phaseDates);
                }
                if ($phase->phase_type === 'pa_feedback') {
                    $bopPaFeedbackDates->push(...$phaseDates);
                }
                if ($phase->phase_type === 'roll_day') {
                    $bopRollDayDates->push(...$phaseDates);
                }
                if ($phase->phase_type === 'workshop_days') {
                    $bopWorkshopDates->push(...$phaseDates);
                }
                if ($phase->phase_type === 'wt_feedback') {
                    $bopWtFeedbackDates->push(...$phaseDates);
                }
                if (in_array($phase->phase_type, ['pa_feedback', 'wt_feedback'], true)) {
                    $bopFeedbackDates->push(...$phaseDates);
                }
            }

            if ($bopAllDates->isEmpty()) {
                $bopAllDates->push(
                    $this->normalizePlaceholderDate($run->first_visit_date),
                    $this->normalizePlaceholderDate($run->last_visit_date)
                );
            }
        }

        PaAttendanceListDraft::query()
            ->where('projekt_id', $projekt->getKey())
            ->where('partner_id', $partnerId)
            ->where('schuljahr', $schuljahr)
            ->where('teil', $teil)
            ->orderBy('id')
            ->get(['payload'])
            ->each(function ($draft) use ($allDates, $preparationDates, $feedbackDates): void {
                $payload = is_array($draft->payload) ? $draft->payload : [];
                $form = is_array($payload['form'] ?? null) ? $payload['form'] : [];
                $listType = (string) ($form['listType'] ?? '');

                foreach (($payload['days'] ?? []) as $day) {
                    if (!is_array($day) || ($day['selected'] ?? true) === false) {
                        continue;
                    }

                    $date = $this->normalizePlaceholderDate($day['date'] ?? null);
                    if (!$date) {
                        continue;
                    }

                    $allDates->push($date);
                    $type = mb_strtolower((string) ($day['type'] ?? ''));
                    $source = mb_strtolower((string) ($day['source'] ?? ''));
                    $note = mb_strtolower((string) ($day['note'] ?? ''));

                    if ($type === 'preparation' || str_contains($source, 'preparation') || str_contains($note, 'vorbereitung')) {
                        $preparationDates->push($date);
                    }

                    if ($type === 'feedback' || str_contains($source, 'feedback') || str_contains($note, 'feedback') || str_contains($note, 'auswertung')) {
                        $feedbackDates->push($date);
                    }
                }

                $startDate = $this->normalizePlaceholderDate($form['startDate'] ?? null);
                $endDate = $this->normalizePlaceholderDate($form['endDate'] ?? null);
                $feedbackDate = $this->normalizePlaceholderDate($form['feedbackDate'] ?? null);

                if ($startDate) {
                    $allDates->push($startDate);
                    if ($listType === 'pa_preparation') {
                        $preparationDates->push($startDate);
                    }
                }
                if ($endDate) {
                    $allDates->push($endDate);
                }
                if ($feedbackDate) {
                    $allDates->push($feedbackDate);
                    $feedbackDates->push($feedbackDate);
                }
            });

        $setting = EinteilungSetting::query()
            ->where('projekt_id', $projekt->getKey())
            ->where('partner_id', $partnerId)
            ->where('schuljahr', $schuljahr)
            ->where('teil', $teil)
            ->with('rundentermine')
            ->latest('id')
            ->first();

        $roundStart = $setting?->rundentermine
            ?->pluck('anfangsdatum')
            ->map(fn ($date) => $this->normalizePlaceholderDate($date))
            ->filter()
            ->min();
        $roundEnd = $setting?->rundentermine
            ?->pluck('enddatum')
            ->map(fn ($date) => $this->normalizePlaceholderDate($date))
            ->filter()
            ->max();

        $preparationDate = $bopPreparationDates->filter()->min()
            ?: $preparationDates->filter()->min();
        $feedbackDate = $bopFeedbackDates->filter()->max()
            ?: $feedbackDates->filter()->max();
        $paFeedbackDate = $bopPaFeedbackDates->filter()->max()
            ?: $feedbackDates->filter()->max();
        $wtFeedbackDate = $bopWtFeedbackDates->filter()->max();
        $firstDate = $bopAllDates->filter()->min()
            ?: $preparationDate
            ?: $allDates->filter()->min()
            ?: $roundStart
            ?: $this->normalizePlaceholderDate($gruppe->anfangsdatum);
        $lastDate = $bopAllDates->filter()->max()
            ?: $feedbackDate
            ?: $allDates->filter()->max()
            ?: $roundEnd
            ?: $this->normalizePlaceholderDate($gruppe->enddatum);

        $firstFormatted = $this->formatIsoPlaceholderDate($firstDate);
        $lastFormatted = $this->formatIsoPlaceholderDate($lastDate);

        return [
            'zeitraum' => $firstFormatted && $lastFormatted
                ? $firstFormatted . ' – ' . $lastFormatted
                : ($firstFormatted ?: $lastFormatted),
            'zeitraum_von' => $firstFormatted,
            'zeitraum_bis' => $lastFormatted,
            'vorbereitung_pa_datum' => $this->formatIsoPlaceholderDate($preparationDate),
            'pa_datum' => $this->formatPlaceholderDateList($bopPaDates),
            'pa_daten' => $this->formatPlaceholderDateList($bopPaDates),
            'feedbackgespraech_pa_datum' => $this->formatIsoPlaceholderDate($paFeedbackDate),
            'rolltag_datum' => $this->formatPlaceholderDateList($bopRollDayDates),
            'werkstatttage_daten' => $this->formatPlaceholderDateList($bopWorkshopDates),
            'werkstatttage_gesamt_daten' => $this->formatPlaceholderDateList($bopWorkshopAllDates),
            'wt_daten' => $this->formatPlaceholderDateList($bopWorkshopDates),
            'feedbackgespraech_wt_datum' => $this->formatIsoPlaceholderDate($wtFeedbackDate),
            'feedbackgespraech_datum' => $this->formatIsoPlaceholderDate($feedbackDate),
            'auswertungsgespraech_datum' => $this->formatIsoPlaceholderDate($feedbackDate),
        ];
    }

    private function normalizePlaceholderDate($value): ?string
    {
        if (!filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function formatIsoPlaceholderDate(?string $date): string
    {
        return $date ? Carbon::parse($date)->format('d.m.Y') : '';
    }

    private function formatPlaceholderDateList($dates): string
    {
        $dates = collect($dates)
            ->map(fn ($date) => $this->normalizePlaceholderDate($date))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return '';
        }

        $ranges = [];
        $rangeStart = $dates->first();
        $previous = $rangeStart;

        foreach ($dates->slice(1) as $date) {
            if (Carbon::parse($previous)->addDay()->toDateString() === $date) {
                $previous = $date;
                continue;
            }

            $ranges[] = [$rangeStart, $previous];
            $rangeStart = $previous = $date;
        }
        $ranges[] = [$rangeStart, $previous];

        return collect($ranges)->map(function (array $range): string {
            [$from, $to] = $range;
            if ($from === $to) {
                return Carbon::parse($from)->format('d.m.Y');
            }

            $fromDate = Carbon::parse($from);
            $toDate = Carbon::parse($to);
            $fromFormatted = $fromDate->year === $toDate->year
                ? $fromDate->format('d.m.')
                : $fromDate->format('d.m.Y');

            return $fromFormatted . '–' . $toDate->format('d.m.Y');
        })->implode(', ');
    }

    private function fillSpreadsheetTemplate($spreadsheet, Gruppe $gruppe, Projekt $projekt, $teilnehmer): void
    {
        $gruppeValues = $this->placeholderValues($gruppe, $projekt);

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $paMarkers = collect($sheet->getCellCollection()->getCoordinates())
                ->filter(fn ($coordinate) => is_string($sheet->getCell($coordinate)->getValue())
                    && $this->containsPaClassTableMarker($sheet->getCell($coordinate)->getValue()))
                ->sortByDesc(fn ($coordinate) => Coordinate::coordinateFromString($coordinate)[1]);

            foreach ($paMarkers as $coordinate) {
                [$column, $row] = Coordinate::coordinateFromString($coordinate);
                $this->writePaClassTable(
                    $sheet,
                    Coordinate::columnIndexFromString($column),
                    (int) $row,
                    $gruppe,
                    $projekt
                );
            }

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

                $replacedValue = $this->replacePlaceholderText($value, $gruppeValues);
                if ($replacedValue !== $value) {
                    $cell->setValueExplicit($replacedValue, DataType::TYPE_STRING);
                }
            }
        }
    }

    private function containsPaClassTableMarker(string $value): bool
    {
        return str_contains($value, '${pa_klassen_tabelle}')
            || str_contains($value, '{{pa_klassen_tabelle}}');
    }

    private function writePaClassTable($sheet, int $markerColumn, int $markerRow, Gruppe $gruppe, Projekt $projekt): void
    {
        $entries = $this->paClassSchedule($gruppe, $projekt);
        if ($entries->isEmpty()) {
            $entries = collect([['class' => '', 'dates' => '']]);
        }

        $blockStart = max(1, $markerRow - 1);
        $blockHeight = $markerRow - $blockStart + 1;
        $blockEnd = $markerRow;
        $lastColumn = max(
            $markerColumn + 1,
            Coordinate::columnIndexFromString($sheet->getHighestDataColumn())
        );
        $sourceMerges = collect($sheet->getMergeCells())->filter(function (string $range) use ($blockStart, $blockEnd): bool {
            $boundaries = Coordinate::rangeBoundaries($range);
            $startRow = (int) $boundaries[0][1];
            $endRow = (int) $boundaries[1][1];

            return $startRow >= $blockStart && $endRow <= $blockEnd;
        })->values();

        $additionalRows = ($entries->count() - 1) * $blockHeight;
        if ($additionalRows > 0) {
            $sheet->insertNewRowBefore($blockEnd + 1, $additionalRows);
        }

        for ($index = 1; $index < $entries->count(); $index++) {
            $targetStart = $blockStart + ($index * $blockHeight);
            for ($offset = 0; $offset < $blockHeight; $offset++) {
                $sourceRow = $blockStart + $offset;
                $targetRow = $targetStart + $offset;
                $sourceDimension = $sheet->getRowDimension($sourceRow);
                $targetDimension = $sheet->getRowDimension($targetRow);
                $targetDimension->setRowHeight($sourceDimension->getRowHeight());
                $targetDimension->setVisible($sourceDimension->getVisible());
                $targetDimension->setOutlineLevel($sourceDimension->getOutlineLevel());
                $targetDimension->setCollapsed($sourceDimension->getCollapsed());

                for ($column = 1; $column <= $lastColumn; $column++) {
                    $sourceCoordinate = Coordinate::stringFromColumnIndex($column) . $sourceRow;
                    $targetCoordinate = Coordinate::stringFromColumnIndex($column) . $targetRow;
                    $sourceCell = $sheet->getCell($sourceCoordinate);
                    $sheet->getCell($targetCoordinate)->setValueExplicit($sourceCell->getValue(), $sourceCell->getDataType());
                    $sheet->duplicateStyle($sheet->getStyle($sourceCoordinate), $targetCoordinate);
                }
            }

            foreach ($sourceMerges as $range) {
                $boundaries = Coordinate::rangeBoundaries($range);
                $rowOffset = $index * $blockHeight;
                $sheet->mergeCells(
                    Coordinate::stringFromColumnIndex($boundaries[0][0]) . ($boundaries[0][1] + $rowOffset)
                    . ':' .
                    Coordinate::stringFromColumnIndex($boundaries[1][0]) . ($boundaries[1][1] + $rowOffset)
                );
            }
        }

        foreach ($entries->values() as $index => $entry) {
            $targetStart = $blockStart + ($index * $blockHeight);
            $targetMarkerRow = $markerRow + ($index * $blockHeight);
            $classMarkerFound = false;

            for ($row = $targetStart; $row < $targetStart + $blockHeight; $row++) {
                for ($column = 1; $column <= $lastColumn; $column++) {
                    $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($column) . $row);
                    $value = $cell->getValue();
                    if (!is_string($value)) {
                        continue;
                    }

                    if ($this->containsPaClassTableMarker($value)) {
                        $cell->setValueExplicit(str_replace(
                            ['${pa_klassen_tabelle}', '{{pa_klassen_tabelle}}'],
                            $entry['dates'],
                            $value
                        ), DataType::TYPE_STRING);
                    }
                    if (str_contains($value, '${pa_klasse}') || str_contains($value, '{{pa_klasse}}')) {
                        $classMarkerFound = true;
                        $cell->setValueExplicit(str_replace(
                            ['${pa_klasse}', '{{pa_klasse}}'],
                            $entry['class'],
                            $value
                        ), DataType::TYPE_STRING);
                    }
                }
            }

            if (!$classMarkerFound && $markerColumn < $lastColumn) {
                $sheet->getCell([$markerColumn + 1, $targetMarkerRow])
                    ->setValueExplicit($entry['class'], DataType::TYPE_STRING);
            }
        }
    }

    private function paClassSchedule(Gruppe $gruppe, Projekt $projekt)
    {
        $partner = $gruppe->relationLoaded('partner') ? $gruppe->partner : $gruppe->partner()->first();
        if (!$partner?->getKey()) {
            return collect();
        }

        $schuljahr = trim((string) $gruppe->getAttribute('export_schuljahr'));
        $teil = trim((string) $gruppe->getAttribute('export_teil'));
        if ($schuljahr === '' || $teil === '') {
            $personIds = $gruppe->relationLoaded('teilnehmer')
                ? $gruppe->teilnehmer->pluck('id')->filter()->unique()
                : collect();
            $context = PersonenIstSchueler::query()
                ->where('schule_id', $partner->getKey())
                ->when($personIds->isNotEmpty(), fn ($query) => $query->whereIn('person_id', $personIds))
                ->latest('id')
                ->first(['schuljahr', 'teil']);
            $schuljahr = $schuljahr ?: trim((string) $context?->schuljahr);
            $teil = $teil ?: trim((string) $context?->teil);
        }
        if ($schuljahr === '' || $teil === '') {
            return collect();
        }

        $normalisePart = fn ($value) => trim((string) preg_replace('/^Teil\s*/i', '', (string) $value));
        $normalisedPart = $normalisePart($teil);
        $run = BopRun::query()
            ->where('projekt_id', $projekt->getKey())
            ->where('partner_id', $partner->getKey())
            ->forSchuljahr($schuljahr)
            ->whereIn('teil', array_values(array_unique([$teil, $normalisedPart, 'Teil ' . $normalisedPart, '_all'])))
            ->with('phases')
            ->first();
        $phase = $run?->phases?->firstWhere('phase_type', 'pa');
        if (!$run || !$phase) {
            return collect();
        }

        $classes = collect($run->planned_classes ?? [])
            ->filter(fn ($class) => $normalisePart($class['part'] ?? '1') === $normalisedPart)
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter();
        if ($classes->isEmpty()) {
            $classes = collect($phase->selected_classes ?? [])->map(fn ($name) => trim((string) $name))->filter();
        }

        $assignments = collect($phase->class_date_assignments ?? []);
        $defaultDates = collect($phase->dates ?? []);

        return $classes->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->map(fn ($className) => [
            'class' => $className,
            'dates' => $this->formatPlaceholderDateList($assignments->has($className)
                ? $assignments->get($className, [])
                : $defaultDates),
        ]);
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

    private function isAssignedToProject(Projekt $projekt, Dokumente $dokument): bool
    {
        if (DB::table('projekt_has_dokumentes')
            ->where('projekt_id', $projekt->id)
            ->where('dokument_id', $dokument->id)
            ->exists()) {
            return true;
        }

        return DB::table('projekt_has_dokument_kategories')
            ->join('dokument_has_kategories', 'projekt_has_dokument_kategories.dokument_kategorie_id', '=', 'dokument_has_kategories.dokument_kategorie_id')
            ->where('projekt_has_dokument_kategories.projekt_id', $projekt->id)
            ->where('dokument_has_kategories.dokument_id', $dokument->id)
            ->exists();
    }

    private function canExportPartnerDocument(?User $user, Dokumente $dokument): bool
    {
        if (! $user) {
            return false;
        }

        if ($dokument->export_permission) {
            return $user->can($dokument->export_permission);
        }

        return $user->can('dokumente.schule.export');
    }

    private function canExportDocument(?User $user, Dokumente $dokument): bool
    {
        if (! $user) {
            return false;
        }

        if ($dokument->export_permission) {
            return $user->can($dokument->export_permission);
        }

        return $user->can('gruppe.export.serienbrief');
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

    private function spreadsheetPlaceholderVariables($spreadsheet): array
    {
        $variables = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                $value = $sheet->getCell($coordinate)->getValue();
                if (!is_string($value)) {
                    continue;
                }

                preg_match_all('/\$\{([^}]+)\}|\{\{([^}]+)\}\}/', $value, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $variables[] = $match[1] !== '' ? $match[1] : ($match[2] ?? '');
                }
            }
        }

        return array_values(array_unique(array_filter($variables)));
    }

    private function singleParticipantPlaceholderValidationError(
        array $variables,
        array $values,
        Personen $participant
    ): ?string {
        $result = $this->missingPlaceholderData($variables, $values);

        if ($result['missing']->isEmpty() && $result['unknown']->isEmpty()) {
            return null;
        }

        return $this->placeholderValidationMessage(
            $result,
            'Teilnehmer ' . trim($participant->vorname . ' ' . $participant->nachname)
        );
    }

    private function groupPlaceholderValidationError(
        array $variables,
        Gruppe $gruppe,
        Projekt $projekt,
        $participants,
        bool $fillParticipants,
        bool $spreadsheet
    ): ?string {
        $variables = collect($variables)
            ->map(fn ($variable) => mb_strtolower(trim((string) $variable)))
            ->filter()
            ->unique()
            ->values();
        $participants = collect($participants)->values();
        $participantKeys = collect([
            'nr', 'nummer', 'vorname', 'nachname', 'name', 'voller_name', 'teilnehmer',
            'geburtsdatum', 'geschlecht', 'anrede', 'kundennummer', 'strasse',
            'hausnummer', 'plz', 'stadt', 'ort', 'adresse', 'email', 'telefon',
        ]);
        $structuralVariables = collect();
        if ($spreadsheet) {
            $structuralVariables->push('teilnehmer_tabelle');
        }
        if ($spreadsheet && $variables->contains('pa_klassen_tabelle')) {
            $structuralVariables->push('pa_klassen_tabelle', 'pa_klasse');
        }

        $indexedVariables = $variables
            ->map(function (string $variable) use ($participantKeys) {
                if (preg_match('/^([a-z_]+)(\d+)$/', $variable, $matches) !== 1
                    || ! $participantKeys->contains($matches[1])) {
                    return null;
                }

                return ['original' => $variable, 'key' => $matches[1], 'index' => (int) $matches[2]];
            })
            ->filter()
            ->values();

        $participantVariables = $fillParticipants
            ? $variables->filter(fn ($variable) => $participantKeys->contains($variable))->values()
            : collect();

        if ($fillParticipants && $spreadsheet && $variables->contains('teilnehmer_tabelle')) {
            $participantVariables = $participantVariables->merge([
                'vorname', 'nachname', 'geburtsdatum', 'strasse', 'hausnummer',
                'plz', 'stadt', 'telefon', 'email',
            ])->unique()->values();
        }

        $groupVariables = $variables
            ->reject(fn ($variable) => $structuralVariables->contains($variable))
            ->reject(fn ($variable) => $fillParticipants && $participantKeys->contains($variable))
            ->reject(fn ($variable) => $indexedVariables->contains(fn ($indexed) => $indexed['original'] === $variable))
            ->values();
        $groupResult = $this->missingPlaceholderData(
            $groupVariables->all(),
            $this->placeholderValues($gruppe, $projekt)
        );

        $participantFailures = collect();
        if ($fillParticipants) {
            foreach ($participants as $position => $participant) {
                $requestedForParticipant = $participantVariables->merge(
                    $indexedVariables
                        ->where('index', $position + 1)
                        ->pluck('key')
                )->unique()->values();

                if ($requestedForParticipant->isEmpty()) {
                    continue;
                }

                $result = $this->missingPlaceholderData(
                    $requestedForParticipant->all(),
                    $this->placeholderValues($gruppe, $projekt, $participant, $position + 1)
                );
                if ($result['missing']->isNotEmpty() || $result['unknown']->isNotEmpty()) {
                    $participantFailures->push([
                        'name' => trim($participant->vorname . ' ' . $participant->nachname),
                        'result' => $result,
                    ]);
                }
            }
        }

        $dynamicMissing = collect();
        if ($spreadsheet && $variables->contains('pa_klassen_tabelle')) {
            $schedule = $this->paClassSchedule($gruppe, $projekt);
            if ($schedule->isEmpty()) {
                $dynamicMissing->push('PA-Klassen und PA-Termine');
            } else {
                if ($schedule->contains(fn ($entry) => trim((string) ($entry['class'] ?? '')) === '')) {
                    $dynamicMissing->push('PA-Klasse');
                }
                if ($schedule->contains(fn ($entry) => trim((string) ($entry['dates'] ?? '')) === '')) {
                    $dynamicMissing->push('PA-Termine je Klasse');
                }
            }
        }

        if ($groupResult['missing']->isEmpty()
            && $groupResult['unknown']->isEmpty()
            && $participantFailures->isEmpty()
            && $dynamicMissing->isEmpty()) {
            return null;
        }

        $messages = ['Der Export kann nicht durchgeführt werden, weil angeforderte Daten fehlen.'];
        if ($groupResult['missing']->isNotEmpty()) {
            $messages[] = 'Projekt/Gruppe/Partner: ' . $groupResult['missing']->implode(', ') . '.';
        }
        if ($dynamicMissing->isNotEmpty()) {
            $messages[] = 'Dynamische Exportdaten: ' . $dynamicMissing->unique()->implode(', ') . '.';
        }
        foreach ($participantFailures as $failure) {
            $details = $failure['result']['missing']->merge(
                $failure['result']['unknown']->map(fn ($key) => '${' . $key . '} (unbekannt)')
            )->unique()->implode(', ');
            $messages[] = 'Teilnehmer ' . $failure['name'] . ': ' . $details . '.';
        }
        if ($groupResult['unknown']->isNotEmpty()) {
            $messages[] = 'Unbekannte Platzhalter in der Vorlage: '
                . $groupResult['unknown']->map(fn ($key) => '${' . $key . '}')->implode(', ') . '.';
        }

        return implode(' ', $messages);
    }

    /** @return array{missing:\Illuminate\Support\Collection, unknown:\Illuminate\Support\Collection} */
    private function missingPlaceholderData(array $variables, array $values): array
    {
        $variables = collect($variables)
            ->map(fn ($variable) => mb_strtolower(trim((string) $variable)))
            ->filter()
            ->unique()
            ->values();
        $labels = $this->placeholderLabels();
        $missing = collect();
        $unknown = collect();
        $participantAddressVariables = collect(['strasse', 'hausnummer', 'plz', 'stadt', 'ort', 'adresse']);
        $partnerAddressVariables = collect([
            'partner_adresse', 'partner_strasse', 'partner_hausnummer', 'partner_plz', 'partner_stadt',
        ]);

        if ($variables->contains(fn ($variable) => $participantAddressVariables->contains($variable))) {
            foreach (['strasse' => 'Straße', 'hausnummer' => 'Hausnummer', 'plz' => 'PLZ', 'stadt' => 'Stadt'] as $field => $label) {
                if ($this->placeholderValueMissing($values[$field] ?? null)) {
                    $missing->push('Adresse des Teilnehmers – ' . $label);
                }
            }
        }

        if ($variables->contains(fn ($variable) => $partnerAddressVariables->contains($variable))) {
            foreach ([
                'partner_strasse' => 'Straße',
                'partner_hausnummer' => 'Hausnummer',
                'partner_plz' => 'PLZ',
                'partner_stadt' => 'Stadt',
            ] as $field => $label) {
                if ($this->placeholderValueMissing($values[$field] ?? null)) {
                    $missing->push('Adresse des Partners – ' . $label);
                }
            }
        }

        foreach ($variables as $variable) {
            if ($participantAddressVariables->contains($variable)
                || $partnerAddressVariables->contains($variable)) {
                continue;
            }

            if (! array_key_exists($variable, $values)) {
                $unknown->push($variable);
                continue;
            }

            if ($this->placeholderValueMissing($values[$variable])) {
                $missing->push($labels[$variable] ?? Str::headline($variable));
            }
        }

        return [
            'missing' => $missing->filter()->unique()->values(),
            'unknown' => $unknown->filter()->unique()->values(),
        ];
    }

    private function placeholderValidationMessage(array $result, string $subject): string
    {
        $messages = ['Der Export kann nicht durchgeführt werden, weil angeforderte Daten fehlen.'];
        if ($result['missing']->isNotEmpty()) {
            $messages[] = $subject . ': ' . $result['missing']->implode(', ') . '.';
        }
        if ($result['unknown']->isNotEmpty()) {
            $messages[] = 'Unbekannte Platzhalter in der Vorlage: '
                . $result['unknown']->map(fn ($key) => '${' . $key . '}')->implode(', ') . '.';
        }

        return implode(' ', $messages);
    }

    private function placeholderLabels(): array
    {
        $labels = [];
        foreach (DokumenteController::platzhalterDefinitionen() as $group) {
            foreach ($group['werte'] as $placeholder) {
                $labels[$placeholder['key']] ??= $placeholder['label'];
            }
        }

        return array_merge($labels, [
            'teilnehmer' => 'Teilnehmername',
            'termin_datum' => 'Termin-Datum',
            'erstgespraech_datum' => 'Termin-Datum',
            'termin_uhrzeit' => 'Termin-Uhrzeit',
            'erstgespraech_uhrzeit' => 'Termin-Uhrzeit',
            'termin' => 'Termin-Datum und Termin-Uhrzeit',
            'betreuer' => 'Betreuer',
            'betreuer_name' => 'Betreuer',
            'betreuer_vorname' => 'Betreuer-Vorname',
            'betreuer_nachname' => 'Betreuer-Nachname',
            'betreuer_anrede' => 'Anrede/Geschlecht des Betreuers',
            'betreuer_anrede_dativ' => 'Anrede/Geschlecht des Betreuers',
        ]);
    }

    private function placeholderValueMissing(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function storageTemplatePath(string $path): string
    {
        return storage_path(ltrim($path, '/\\'));
    }

    private function formatDate($value): string
    {
        return $value ? date('d.m.Y', strtotime($value)) : '';
    }

    private function formatPlaceholderTime($value): string
    {
        if (!$value) {
            return '';
        }

        return substr((string) $value, 0, 5);
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
