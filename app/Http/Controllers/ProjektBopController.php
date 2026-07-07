<?php

namespace App\Http\Controllers;

use App\Models\Bereich;
use App\Models\Bereichsauswahl;
use App\Models\BereichsauswahlSetting;
use App\Models\EinteilungBereiche;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Services\MyDatum;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

class ProjektBopController extends Controller
{
    private const ACCESS_CODE_PARTS = [
        'BA', 'BE', 'BI', 'BO',
        'DA', 'DE', 'DI', 'DO',
        'FA', 'FE', 'FI', 'FO',
        'KA', 'KE', 'KI', 'KO',
        'LA', 'LE', 'LI', 'LO',
        'MA', 'ME', 'MI', 'MO',
        'NA', 'NE', 'NI', 'NO',
        'PA', 'PE', 'PI', 'PO',
        'RA', 'RE', 'RI', 'RO',
        'SA', 'SE', 'SI', 'SO',
        'TA', 'TE', 'TI', 'TO',
    ];

    private function normalizeAuswahlAnzahl(int $value): int
    {
        return min(4, max(2, $value));
    }

    private function defaultAuswahlAnzahl(?Projekt $projekt): int
    {
        $bereicheCount = $projekt?->bereiche?->count() ?? 4;

        return $this->normalizeAuswahlAnzahl($bereicheCount > 0 ? $bereicheCount : 4);
    }

    private function publicToken(): string
    {
        do {
            $token = Str::random(40);
        } while (BereichsauswahlSetting::where('public_token', $token)->exists());

        return $token;
    }

    private function settingFor(int $projektId, int $partnerId, string $schuljahr, string $teil, ?Projekt $projekt = null): BereichsauswahlSetting
    {
        $setting = BereichsauswahlSetting::firstOrCreate(
            [
                'projekt_id' => $projektId,
                'partner_id' => $partnerId,
                'schuljahr' => $schuljahr,
                'teil' => $teil,
            ],
            [
                'auswahl_anzahl' => $this->defaultAuswahlAnzahl($projekt),
                'public_token' => $this->publicToken(),
                'zugang_aktiv' => true,
                'user_create' => auth()->id(),
            ]
        );

        if (!$setting->public_token) {
            $setting->update(['public_token' => $this->publicToken()]);
        }

        return $setting;
    }

    private function accessCode(): string
    {
        do {
            $parts = self::ACCESS_CODE_PARTS;
            $code = $parts[random_int(0, count($parts) - 1)]
                . '-' . $parts[random_int(0, count($parts) - 1)]
                . '-' . random_int(20, 98)
                . '-' . $parts[random_int(0, count($parts) - 1)];
        } while (Bereichsauswahl::where('access_code', $code)->exists());

        return $code;
    }

    private function normalizeAccessCodeInput(string $value): string
    {
        $normalized = Str::upper(preg_replace('/\s+/', '', trim($value)));
        $plain = str_replace('-', '', $normalized);

        if (preg_match('/^[A-Z]{4}[0-9]{2}[A-Z]{2}$/', $plain)) {
            return substr($plain, 0, 2)
                . '-' . substr($plain, 2, 2)
                . '-' . substr($plain, 4, 2)
                . '-' . substr($plain, 6, 2);
        }

        return $normalized;
    }

    private function ensureAccessCodes(Collection $teilnehmer, ?int $userId): void
    {
        foreach ($teilnehmer as $schueler) {
            $wahl = $schueler->bereichsauswahl;

            if (!$wahl) {
                $wahl = Bereichsauswahl::create([
                    'teilnehmer_id' => $schueler->id,
                    'access_code' => $this->accessCode(),
                    'user_create' => $userId,
                ]);

                $schueler->setRelation('bereichsauswahl', $wahl);
                continue;
            }

            if (!$wahl->access_code) {
                $wahl->update(['access_code' => $this->accessCode()]);
            }
        }
    }

    private function qrSvg(string $url): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(128),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($url);
    }

    private function allowedBereichIds(Projekt $projekt): Collection
    {
        return $projekt->bereiche->pluck('id')->map(fn ($id) => (int) $id)->values();
    }

    private function validatedChoices(Request $request, BereichsauswahlSetting $setting, Collection $allowedBereichIds): array
    {
        $data = $request->validate([
            'choices' => ['required', 'array', 'size:' . $setting->auswahl_anzahl],
            'choices.*' => ['required', 'integer'],
        ]);

        $choices = collect($data['choices'])->map(fn ($id) => (int) $id)->values();

        if ($choices->unique()->count() !== $choices->count()) {
            throw ValidationException::withMessages([
                'choices' => 'Jeder Bereich darf nur einmal gewaehlt werden.',
            ]);
        }

        if ($choices->diff($allowedBereichIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'choices' => 'Mindestens ein Bereich ist fuer dieses Projekt nicht erlaubt.',
            ]);
        }

        return $choices->all();
    }

    private function persistChoices(Bereichsauswahl $wahl, array $choices, ?int $userId = null, bool $submitted = false): void
    {
        $payload = [
            'bereich_id1' => $choices[0] ?? null,
            'bereich_id2' => $choices[1] ?? null,
            'bereich_id3' => $choices[2] ?? null,
            'bereich_id4' => $choices[3] ?? null,
        ];

        if ($userId) {
            $payload['user_update'] = $userId;
        }

        if ($submitted) {
            $payload['submitted_at'] = now();
        }

        $wahl->update($payload);
    }

    private function teilnehmerForCode(BereichsauswahlSetting $setting, string $code): ?PersonenIstSchueler
    {
        return PersonenIstSchueler::with(['person', 'bereichsauswahl'])
            ->where('schule_id', $setting->partner_id)
            ->where('schuljahr', $setting->schuljahr)
            ->where('teil', $setting->teil)
            ->whereHas('bereichsauswahl', fn ($query) => $query->where('access_code', $code))
            ->first();
    }

    private function formatSelfTeilnehmer(PersonenIstSchueler $teilnehmer, BereichsauswahlSetting $setting): array
    {
        $wahl = $teilnehmer->bereichsauswahl;

        return [
            'id' => $teilnehmer->id,
            'vorname' => $teilnehmer->person?->vorname,
            'nachname' => $teilnehmer->person?->nachname,
            'klasse' => $teilnehmer->klasse,
            'auswahl_anzahl' => $setting->auswahl_anzahl,
            'choices' => array_slice([
                $wahl?->bereich_id1,
                $wahl?->bereich_id2,
                $wahl?->bereich_id3,
                $wahl?->bereich_id4,
            ], 0, $setting->auswahl_anzahl),
            'submitted_at' => $wahl?->submitted_at?->toIso8601String(),
        ];
    }

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
                $exportPath = storage_path('exports/' . $filename);

                File::ensureDirectoryExists(dirname($exportPath));
                $templateProcessor->saveAs($exportPath);
                return response()->download($exportPath)->deleteFileAfterSend(true);
    }
    public function anwesenheitslistePAexportWord(Request $request)
    {

          $validated = $request->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date',
            'schuleId' => 'required|exists:partners,id',
            'schuljahr' => 'required|string',
            'teil' => 'required|string',
            'exportMode' => 'nullable|in:alle,klasse',
            'klasse' => 'nullable|required_if:exportMode,klasse|string',
        ]);

        $validated['exportMode'] = $validated['exportMode'] ?? (empty($validated['klasse']) ? 'alle' : 'klasse');

        $templateFile = storage_path('vorlage/projekte/bop/word/pa/Anwesenheitsliste-PA.docx');

            if(!file_exists($templateFile)){
                return redirect()->back()->with('error', 'Die Datei für den Export konnte nicht gefunden werden.');
            }


            $alle_teilnehmer = PersonenIstSchueler::query()
            ->filterSchueler($validated['schuleId'], $validated['schuljahr'], $validated['teil'])
            ->when(($validated['exportMode'] ?? 'klasse') === 'klasse', fn ($query) => $query->where('klasse', $validated['klasse']))
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

            $createDocument = function ($teilnehmerListe, string $klasseName, string $exportPath) use ($templateFile, $schule, $tag1, $tag2) {
                $i = 1;
                $templateProcessor = new TemplateProcessor($templateFile);

                $templateProcessor->setValue('schule', $schule->name);
                $templateProcessor->setValue('schulform', PersonenIstSchueler::query()->schulform($teilnehmerListe));
                $templateProcessor->setValue('klasse', $klasseName);
                $templateProcessor->setValue('tag1', $tag1);
                $templateProcessor->setValue('tag2', $tag2);

                foreach ($teilnehmerListe as $teilnehmer) {
                    $templateProcessor->setValue('nachname' . $i, $teilnehmer->person->nachname);
                    $templateProcessor->setValue('vorname' . $i, $teilnehmer->person->vorname);
                    $i++;
                }

                while($i<=30){
                    $templateProcessor->setValue('nachname' . $i, '');
                    $templateProcessor->setValue('vorname' . $i, '');
                    $i++;
                }

                File::ensureDirectoryExists(dirname($exportPath));
                $templateProcessor->saveAs($exportPath);
            };

            if (($validated['exportMode'] ?? 'klasse') === 'alle') {
                $exportDir = storage_path('exports/pa_' . Str::uuid());
                File::ensureDirectoryExists($exportDir);

                $zipPath = storage_path('exports/Anwesenheitslisten_PA_' . $schule->name . '_' . $tag1 . '_' . $tag2 . '_' . date('Ymd_His') . '.zip');
                File::ensureDirectoryExists(dirname($zipPath));
                $zip = new ZipArchive();

                if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    return response()->json(['message' => 'ZIP-Datei konnte nicht erstellt werden.'], 500);
                }

                foreach ($alle_teilnehmer->groupBy('klasse') as $klasseName => $teilnehmerListe) {
                    $klasseName = (string) ($klasseName ?: 'ohne_Klasse');
                    $filename = 'Anwesenheitsliste_PA_' . Str::slug($schule->name, '_') . '_' . Str::slug($klasseName, '_') . '_' . date('Ymd_His') . '.docx';
                    $exportPath = $exportDir . DIRECTORY_SEPARATOR . $filename;

                    $createDocument($teilnehmerListe->sortBy(fn($item) => $item->person->nachname), $klasseName, $exportPath);
                    $zip->addFile($exportPath, $filename);
                }

                $zip->close();
                File::deleteDirectory($exportDir);

                return response()->download($zipPath)->deleteFileAfterSend(true);
            }

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
                $exportPath = storage_path('exports/' . $filename);

                File::ensureDirectoryExists(dirname($exportPath));
                $templateProcessor->saveAs($exportPath);
                return response()->download($exportPath)->deleteFileAfterSend(true);
    }

    public function anwesenheitslistePOBOTag1($partnerID, $schuljahr, $teil, $klasse = 'exportAlleKlassen', Request $request)
    {
        // Query Parameter
        $anzahlBereiche = request()->query('anzahlBereiche', 6);
        $anzahlRaeumlichkeiten = request()->query('anzahlRaeumlichkeiten', $anzahlBereiche);
        $kapazitaeten = request()->query('kapazitaeten', []);
        $termin = request()->query('termin', date('Y-m-d')) ;
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
            $sheet->setCellValue('C2', "Rolltag - Klasse $klasse - " . $schule->name);

            $row = 8;

            foreach ($alleTeilnehmer as $t) {
                $sheet->setCellValue('B' . $row, $t->person->nachname . ', ' . $t->person->vorname);
                $sheet->setCellValue('D' . $row, $t->klasse);
                $sheet->setCellValue('E' . $row, $t->geschlecht);
                $row++;
            }

            $filePath = storage_path('Rolltag_' . $klasse . '.xlsx');
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
                $sheet->setCellValue('C2', "Rolltag - Klasse $klassenName - " . $schule->name);

                // Teilnehmer eintragen
                $row = 8;

                foreach ($teilnehmerListe as $t) {
                    $sheet->setCellValue('B' . $row, $t->person->nachname . ', ' . $t->person->vorname);
                    $sheet->setCellValue('D' . $row, $t->klasse);
                    $sheet->setCellValue('E' . $row, $t->geschlecht);
                    $row++;
                }

                // Datei speichern
                $fileName = "Rolltag_{$klassenName}.xlsx";
                $filePath = $tempDir . '/' . $fileName;

                (new Xlsx($spreadsheet))->save($filePath);

                $dateien[] = $filePath;
            }

            // 👉 ZIP erstellen
            $zipFileName = storage_path('Rolltag_Klassen_' . date('Ymd_His') . '.zip');
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
            $alleTeilnehmer = $alleTeilnehmer->values();
            $kapazitaeten = array_map(fn ($kapazitaet) => (int) $kapazitaet, $kapazitaeten ?? []);

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

            if (array_sum($kapazitaeten) < $anzahlTeilnehmer) {
                return response()->json([
                    'message' => 'Die eingegebenen Kapazitaeten reichen nicht fuer alle Schueler aus.',
                ], 422);
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
                $terminDatum = DateTime::createFromFormat('Y-m-d', $termin)->format('d.m.Y');

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
                (new Xlsx($spreadsheet))->save($tempDir . "/Rolltag_Raum_" . ($i + 1) . "_$raumName.xlsx");
            }

            // ZIP erstellen
            $zipPath = storage_path('Rolltag_Raeume_' . date('Ymd_His') . '.zip');
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


    public function bereichsauswahl($partnerId, $schuljahr, $teil)
    {
        $projekt = Projekt::with('bereiche', 'partners')->where('id', Auth()->user()->current_team_id)->firstOrFail();
        $partner = Partner::findOrFail($partnerId);
        $setting = $this->settingFor($projekt->id, (int) $partnerId, $schuljahr, $teil, $projekt);

        $alle_teilnehmer = PersonenIstSchueler::with(['bereichsauswahl', 'person'])
            ->filterSchueler($partnerId, $schuljahr, $teil)
            ->get()
            ->sort(function($a, $b) {
                $klasseCompare = strnatcasecmp($a->klasse, $b->klasse);
                if ($klasseCompare !== 0) return $klasseCompare;

                return strnatcasecmp($a->person->nachname ?? '', $b->person->nachname ?? '');
            })
            ->values();

        $this->ensureAccessCodes($alle_teilnehmer, auth()->id());
        $alle_teilnehmer->load('bereichsauswahl');

        $publicUrl = route('bereichsauswahl.self.show', $setting->public_token);

        return Inertia::render('Bereichsauswahl/Index', [
            'projekt' => $projekt,
            'alle_teilnehmer' => $alle_teilnehmer,
            'partner' => [
                'id' => $partner->id,
                'name' => $partner->name,
            ],
            'schuljahr' => $schuljahr,
            'teil' => $teil,
            'setting' => [
                'id' => $setting->id,
                'auswahl_anzahl' => $setting->auswahl_anzahl,
                'zugang_aktiv' => $setting->zugang_aktiv,
                'public_url' => $publicUrl,
                'qr_svg' => $this->qrSvg($publicUrl),
            ],
        ]);
    }

    public function bereichsauswahlSettingUpdate(Request $request)
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'schuljahr' => ['required', 'string'],
            'teil' => ['required', 'string'],
            'auswahl_anzahl' => ['required', 'integer', 'min:2', 'max:4'],
            'zugang_aktiv' => ['nullable', 'boolean'],
        ]);

        $projekt = Projekt::with('bereiche')->findOrFail(auth()->user()->current_team_id);
        $setting = $this->settingFor(
            $projekt->id,
            (int) $validated['partner_id'],
            $validated['schuljahr'],
            $validated['teil'],
            $projekt
        );

        $selectionCount = $this->normalizeAuswahlAnzahl((int) $validated['auswahl_anzahl']);

        $setting->update([
            'auswahl_anzahl' => $selectionCount,
            'zugang_aktiv' => $request->boolean('zugang_aktiv', true),
            'user_update' => auth()->id(),
        ]);

        $teilnehmerIds = PersonenIstSchueler::query()
            ->where('schule_id', $validated['partner_id'])
            ->where('schuljahr', $validated['schuljahr'])
            ->where('teil', $validated['teil'])
            ->pluck('id');

        $clearFields = collect([3, 4])
            ->filter(fn ($field) => $field > $selectionCount)
            ->mapWithKeys(fn ($field) => ['bereich_id' . $field => null])
            ->all();

        if ($clearFields) {
            Bereichsauswahl::whereIn('teilnehmer_id', $teilnehmerIds)->update($clearFields);
        }

        return response()->json([
            'success' => true,
            'setting' => [
                'id' => $setting->id,
                'auswahl_anzahl' => $setting->auswahl_anzahl,
                'zugang_aktiv' => $setting->zugang_aktiv,
            ],
        ]);
    }

    public function waehlen(Request $request)
    {
        $request->validate([
            'teilnehmer_id' => ['required', 'integer', 'exists:personen_ist_schuelers,id'],
        ]);

        $teilnehmer = PersonenIstSchueler::where('id', $request->teilnehmer_id)
            ->with(['bereichsauswahl', 'person'])
            ->firstOrFail();

        $projekt = Projekt::with('bereiche')->findOrFail(auth()->user()->current_team_id);
        $setting = $this->settingFor(
            $projekt->id,
            (int) $teilnehmer->schule_id,
            $teilnehmer->schuljahr,
            $teilnehmer->teil,
            $projekt
        );
        $choices = $this->validatedChoices($request, $setting, $this->allowedBereichIds($projekt));

        $wahl = $teilnehmer->bereichsauswahl;

        if (!$wahl) {
            $wahl = Bereichsauswahl::create([
                'teilnehmer_id' => $request->teilnehmer_id,
                'access_code' => $this->accessCode(),
                'user_create' => auth()->user()->id,
            ]);
        }

        $this->persistChoices($wahl, $choices, auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Bereichsauswahl aktualisiert.',
            'choices' => $choices,
            'access_code' => $wahl->access_code,
        ]);
    }

    public function bereichsauswahlSelfShow(string $token)
    {
        $setting = BereichsauswahlSetting::with(['partner', 'projekt.bereiche'])
            ->where('public_token', $token)
            ->where('zugang_aktiv', true)
            ->firstOrFail();

        return Inertia::render('Bereichsauswahl/Selbstwahl', [
            'context' => [
                'schule' => $setting->partner?->name,
                'schuljahr' => $setting->schuljahr,
                'teil' => $setting->teil,
                'auswahl_anzahl' => $setting->auswahl_anzahl,
            ],
            'bereiche' => $setting->projekt?->bereiche?->values() ?? [],
            'token' => $token,
        ]);
    }

    public function bereichsauswahlSelfThanks(string $token)
    {
        $setting = BereichsauswahlSetting::with('partner')
            ->where('public_token', $token)
            ->firstOrFail();

        return Inertia::render('Bereichsauswahl/Danke', [
            'context' => [
                'schule' => $setting->partner?->name,
                'schuljahr' => $setting->schuljahr,
                'teil' => $setting->teil,
            ],
        ]);
    }

    public function bereichsauswahlSelfVerify(Request $request, string $token)
    {
        $request->validate([
            'access_code' => ['required', 'string', 'max:20'],
        ]);

        $setting = BereichsauswahlSetting::where('public_token', $token)
            ->where('zugang_aktiv', true)
            ->firstOrFail();

        $code = $this->normalizeAccessCodeInput($request->input('access_code'));
        $teilnehmer = $this->teilnehmerForCode($setting, $code);

        if (!$teilnehmer) {
            throw ValidationException::withMessages([
                'access_code' => 'Der Code wurde nicht gefunden.',
            ]);
        }

        return response()->json([
            'success' => true,
            'teilnehmer' => $this->formatSelfTeilnehmer($teilnehmer, $setting),
        ]);
    }

    public function bereichsauswahlSelfStore(Request $request, string $token)
    {
        $request->validate([
            'access_code' => ['required', 'string', 'max:20'],
        ]);

        $setting = BereichsauswahlSetting::with('projekt.bereiche')
            ->where('public_token', $token)
            ->where('zugang_aktiv', true)
            ->firstOrFail();

        $code = $this->normalizeAccessCodeInput($request->input('access_code'));
        $teilnehmer = $this->teilnehmerForCode($setting, $code);

        if (!$teilnehmer || !$teilnehmer->bereichsauswahl) {
            throw ValidationException::withMessages([
                'access_code' => 'Der Code wurde nicht gefunden.',
            ]);
        }

        $choices = $this->validatedChoices(
            $request,
            $setting,
            $this->allowedBereichIds($setting->projekt)
        );

        $this->persistChoices($teilnehmer->bereichsauswahl, $choices, null, true);
        $teilnehmer->load('bereichsauswahl');

        return response()->json([
            'success' => true,
            'message' => 'Deine Bereichsauswahl wurde gespeichert.',
            'teilnehmer' => $this->formatSelfTeilnehmer($teilnehmer, $setting),
            'redirect_url' => route('bereichsauswahl.self.thanks', $token),
        ]);
    }


    public function generatePdfauswertungsbogenPASchule($partnerId, $schuljahr, $teil)
    {
            $schule = Partner::findOrFail($partnerId);
            if (!$schule)
            {
                return redirect()->route('schule.index')->with('error', 'Die gewählte Schule konnte nicht gefunden werden.');
            }

            // Daten aus der Tabelle abrufen
            $alle_teilnehmer = PersonenIstSchueler::with('person')
            ->filterSchueler($partnerId, $schuljahr, $teil)
            ->get()
            ->sort(function($a, $b) {
                $klasseCompare = strnatcasecmp($a->klasse, $b->klasse);
                if ($klasseCompare !== 0) return $klasseCompare;

                return strnatcasecmp($a->person->nachname ?? '', $b->person->nachname ?? '');
            })
            ->values();

            if ($alle_teilnehmer->isEmpty())
            {
                return redirect()->back()->with('error', 'Die Schule: ' . $schule->name . ' verfügt über keine Teilnehmer.');
            }

            $data = [
                'alle_teilnehmer' => $alle_teilnehmer,
                'schulname' => $schule->name,
            ];


            $pdf = Pdf::loadView('pdf.auswertungsbogenPA',  $data);
            $pdf->setPaper('A4', 'landscape');
            return $pdf->stream('Auswertungbogen_PA_' . $schule->name. '_' . $schuljahr  . '_Teil_' . $teil .'.pdf');
    }

    public function generatePdfAuswertungsbogenPaRolandSchule($partnerId, $schuljahr, $teil)
    {
        $schule = Partner::findOrFail($partnerId);

        $schueler = PersonenIstSchueler::with('person')
            ->filterSchueler($partnerId, $schuljahr, $teil)
            ->get()
            ->sort(function ($a, $b) {
                $klasseCompare = strnatcasecmp((string) $a->klasse, (string) $b->klasse);
                if ($klasseCompare !== 0) {
                    return $klasseCompare;
                }

                $nachnameCompare = strnatcasecmp((string) ($a->person?->nachname ?? ''), (string) ($b->person?->nachname ?? ''));
                if ($nachnameCompare !== 0) {
                    return $nachnameCompare;
                }

                return strnatcasecmp((string) ($a->person?->vorname ?? ''), (string) ($b->person?->vorname ?? ''));
            })
            ->values();

        if ($schueler->isEmpty()) {
            return redirect()->back()->with('error', 'Die Schule: ' . $schule->name . ' verfuegt ueber keine Teilnehmer.');
        }

        $klasseCounter = [];
        $teilnehmer = $schueler->map(function (PersonenIstSchueler $schueler) use (&$klasseCounter, $schule, $schuljahr, $teil) {
            $klasse = trim((string) $schueler->klasse) ?: 'ohne Klasse';
            $klasseCounter[$klasse] = ($klasseCounter[$klasse] ?? 0) + 1;

            return [
                'vorname' => $schueler->person?->vorname ?? '',
                'nachname' => $schueler->person?->nachname ?? '',
                'name' => trim(($schueler->person?->nachname ?? '') . ', ' . ($schueler->person?->vorname ?? '')),
                'geburtsdatum' => $schueler->person?->geburtsdatum ? Carbon::parse($schueler->person->geburtsdatum)->format('d.m.Y') : '',
                'geschlecht' => $schueler->person?->geschlecht ?? '',
                'schule' => $schule->name,
                'klasse' => $klasse,
                'schuljahr' => $schuljahr,
                'teil' => $teil,
                'footer_nummer' => $klasse . '-' . $klasseCounter[$klasse],
            ];
        })->values();

        $pdf = Pdf::loadView('pdf.auswertungsbogenPA-roland', [
            'teilnehmer' => $teilnehmer,
            'schulname' => $schule->name,
            'schuljahr' => $schuljahr,
            'teil' => $teil,
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download(
            'Auswertungsbogen_PA_neu_Roland_' . $this->exportFilePart($schule->name) . '_' . $this->exportFilePart($schuljahr) . '_Teil_' . $this->exportFilePart($teil) . '.pdf'
        );
    }



    public function exportElterneinverstaendniserklaerungSchule($partnerId, $schuljahr, $teil)
    {
        $alle_teilnehmer = PersonenIstSchueler::with('person')
            ->filterSchueler($partnerId, $schuljahr, $teil)
            ->where('eee', '0')
            ->get()
             ->sort(function($a, $b) {
                $klasseCompare = strnatcasecmp($a->klasse, $b->klasse);
                if ($klasseCompare !== 0) return $klasseCompare;

                return strnatcasecmp($a->person->nachname ?? '', $b->person->nachname ?? '');
            })
            ->values();
        $partner = Partner::findOrFail($partnerId);
        if(!$partner){
            return redirect()->back()->with('error', 'Die Schule konnte nicht gefunden werden.' );
        }
        if($alle_teilnehmer->isEmpty()){
            return redirect()->back()->with('success', 'Alle Elterneinverständniserklärung der Schule sind erfolgreich eingegangen.' );
        }

        // Pfad zur vorhandenen Excel-Datei
        $existingFile = storage_path('vorlage/projekte/bop/excel/Liste-Elterneinverstaendniserklaerung.xlsx');
        if(!file_exists($existingFile)){
            return redirect()->back()->with('error', 'Die Datei für den Export konnte nicht gefunden werden.');
        }
        // Excel-Datei öffnen
        $spreadsheet = IOFactory::load($existingFile);
        $sheet = $spreadsheet->getActiveSheet();


        $sheet->setCellValue('B2', 'Schule: ' . $partner->name);


        $row = 5; // Startzeile für Daten
        foreach ($alle_teilnehmer as $teilnehmer)
        {
                $sheet->setCellValue('B'.$row, $teilnehmer->person->vorname);
                $sheet->setCellValue('C'.$row, $teilnehmer->person->nachname);
                $sheet->setCellValue('D'.$row, $teilnehmer->geschlecht);
                $sheet->setCellValue('E'.$row, $teilnehmer->klasse);
                $row++;
        }
        $row++;

        $sheet->setCellValue('D'.$row, 'Anzahl:');
        $sheet->getStyle('D'.$row)->getAlignment()->setHorizontal('right');
        $sheet->getStyle('D' . $row)->getFont()->setBold(true);

        $sheet->setCellValue('E'.$row, $alle_teilnehmer->count());
        $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal('left');


        // Excel-Datei speichern

        $writer = new Xlsx($spreadsheet);
       $updatedFile = 'Ausstehende Elterneinverstaendniserklaerung-' . $partner->name .'-'. date('d-m-Y') . '.xlsx';
       $writer->save($updatedFile);

       // Aktualisierte Excel-Datei herunterladen
       return response()
        ->download($updatedFile)
        ->deleteFileAfterSend(true);

    }

    private function exportFilePart(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', trim($value));

        return trim($value, '_') ?: 'export';
    }




}
