<?php

namespace App\Http\Controllers;

use App\Models\GruppeHasPersonen;
use App\Models\Partner;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Services\Bop\AttendanceFooterService;
use App\Services\Bop\BopEvaluationExportService;
use App\Services\Bop\PotenzialanalyseReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

class BopLegacyFunctionController extends Controller
{
    public function __construct(
        private readonly AttendanceFooterService $attendanceFooter,
        private readonly BopEvaluationExportService $bopEvaluations,
    ) {}

    private function schueler(int $schuleId, string $schuljahr, string $teil)
    {
        return PersonenIstSchueler::with(['person', 'einteilungen'])
            ->where('schule_id', $schuleId)
            ->forSchuljahr($schuljahr)
            ->where('teil', $teil)
            ->get()
            ->sort(function (PersonenIstSchueler $left, PersonenIstSchueler $right) {
                $class = strnatcasecmp((string) $left->klasse, (string) $right->klasse);
                if ($class !== 0) {
                    return $class;
                }

                $lastName = strnatcasecmp((string) $left->person?->nachname, (string) $right->person?->nachname);
                if ($lastName !== 0) {
                    return $lastName;
                }

                $firstName = strnatcasecmp((string) $left->person?->vorname, (string) $right->person?->vorname);

                return $firstName !== 0 ? $firstName : ($left->person_id <=> $right->person_id);
            })
            ->values();
    }

    private function partner(int $schuleId): Partner
    {
        $projectId = auth()->user()?->current_team_id;
        abort_unless($projectId, 409, 'Bitte waehlen Sie zuerst ein aktives Projekt aus.');
        abort_unless(
            DB::table('projekt_has_partners')
                ->where('projekt_id', $projectId)
                ->where('partner_id', $schuleId)
                ->exists(),
            404
        );

        return Partner::findOrFail($schuleId);
    }

    private function safeName(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', trim($value));
    }

    private function safeFolderSegment(string $value): string
    {
        $value = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/u', '_', trim($value));

        return trim((string) $value, ". \t\n\r\0\x0B") ?: 'Unbekannt';
    }

    private function baseFolder(int $schuleId, string $schuljahr, string $teil): string
    {
        $partner = $this->partner($schuleId);
        $folder = storage_path('app/bop/'.$this->safeName($partner->name).'/'.$this->safeName($schuljahr).'/Teil_'.$this->safeName($teil));

        File::ensureDirectoryExists($folder);

        return $folder;
    }

    private function simpleSpreadsheet(string $title, int $schuleId, string $schuljahr, string $teil, array $extraColumns = [])
    {
        $partner = $this->partner($schuleId);
        $schueler = $this->schueler($schuleId, $schuljahr, $teil);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setTitle(Str::limit($title, 31, ''));
        $sheet->setCellValue('A1', $title);
        $sheet->setCellValue('A2', 'Schule');
        $sheet->setCellValue('B2', $partner->name);
        $sheet->setCellValue('A3', 'Schuljahr');
        $sheet->setCellValue('B3', $schuljahr);
        $sheet->setCellValue('A4', 'Teil');
        $sheet->setCellValue('B4', $teil);

        $headers = array_merge(['Nr.', 'Vorname', 'Nachname', 'Geschlecht', 'Geburtsdatum', 'Klasse'], $extraColumns);
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 6], $header);
        }

        foreach ($schueler as $index => $item) {
            $person = $item->person;
            $row = $index + 7;
            $sheet->setCellValue([1, $row], $index + 1);
            $sheet->setCellValue([2, $row], $person?->vorname);
            $sheet->setCellValue([3, $row], $person?->nachname);
            $sheet->setCellValue([4, $row], $person?->geschlecht);
            $sheet->setCellValue([5, $row], $person?->geburtsdatum ? Carbon::parse($person->geburtsdatum)->format('d.m.Y') : '');
            $sheet->setCellValue([6, $row], $item->klasse);

            foreach ($extraColumns as $extraIndex => $extraColumn) {
                $sheet->setCellValue([7 + $extraIndex, $row], '');
            }
        }

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename)
    {
        $path = storage_path('app/tmp/'.Str::uuid().'_'.$filename);
        File::ensureDirectoryExists(dirname($path));
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function anwesenheitsdaten(int $schulId, string $schuljahr, string $teil)
    {
        $partner = $this->partner($schulId);
        $schueler = $this->schueler($schulId, $schuljahr, $teil);
        $tage = $this->anwesenheitsdatenTage();
        $summenTage = $this->anwesenheitsdatenSummenTage();
        $gesamtAnwesenheitstage = $schueler->count() * count(array_filter(
            $summenTage,
            fn ($key) => $this->defaultAnwesenheitsdatenStatus($key) === 'present'
        ));
        $paAnzahl = $schueler->count();

        return Inertia::render('BOP/Anwesenheitsdaten', [
            'partner' => [
                'id' => $partner->id,
                'name' => $partner->name,
            ],
            'schueler' => $schueler->map(function ($item, $index) {
                return [
                    'id' => $item->id,
                    'nummer' => $index + 1,
                    'nachname' => $item->person?->nachname,
                    'vorname' => $item->person?->vorname,
                    'geschlecht' => $item->person?->geschlecht,
                    'klasse' => $item->klasse,
                ];
            })->values(),
            'schuljahr' => $schuljahr,
            'teil' => $teil,
            'tage' => $tage,
            'summenTage' => $summenTage,
            'gesamtAnwesenheitstage' => $gesamtAnwesenheitstage,
            'paAnzahl' => $paAnzahl,
        ]);
    }

    private function anwesenheitsdatenTage(): array
    {
        return [
            'vorb' => 'Vorb.',
            'pa1' => 'PA1',
            'pa2' => 'PA2',
            'rolltag' => 'Rolltag',
            'bo1' => 'BO-Tag1',
            'bo2' => 'BO-Tag2',
            'bo3' => 'BO-Tag3',
            'bo4' => 'BO-Tag4',
            'bo5' => 'BO-Tag5',
            'bo6' => 'BO-Tag6',
            'bo7' => 'BO-Tag7',
            'bo8' => 'BO-Tag8',
            'bo9' => 'BO-Tag9',
        ];
    }

    private function defaultAnwesenheitsdatenStatus(string $key): string
    {
        return $key === 'bo1' ? 'absent' : 'present';
    }

    private function anwesenheitsdatenSummenTage(): array
    {
        return ['rolltag', 'bo1', 'bo2', 'bo3', 'bo4', 'bo5', 'bo6', 'bo7', 'bo8', 'bo9'];
    }

    public function anwesenheitsdatenExport(Request $request, int $schulId, string $schuljahr, string $teil)
    {
        $partner = $this->partner($schulId);
        $schueler = $this->schueler($schulId, $schuljahr, $teil);
        $tage = $this->anwesenheitsdatenTage();
        $summenTage = $this->anwesenheitsdatenSummenTage();
        $statusPayload = json_decode((string) $request->input('status_payload', '{}'), true);
        $statusPayload = is_array($statusPayload) ? $statusPayload : [];
        $defaultTotal = $schueler->count() * count(array_filter(
            $summenTage,
            fn ($key) => $this->defaultAnwesenheitsdatenStatus($key) === 'present'
        ));
        $exportTotal = empty($statusPayload)
            ? $defaultTotal
            : collect($statusPayload)->sum(function ($studentStatus) use ($summenTage) {
                return collect($summenTage)->filter(fn ($key) => ($studentStatus[$key] ?? $this->defaultAnwesenheitsdatenStatus($key)) === 'present')->count();
            });

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Anwesenheitsdaten');

        $sheet->setCellValue('A1', 'Anwesenheitsdaten');
        $sheet->setCellValue('A2', 'Schule');
        $sheet->setCellValue('B2', $partner->name);
        $sheet->setCellValue('A3', 'Schuljahr');
        $sheet->setCellValue('B3', $schuljahr);
        $sheet->setCellValue('A4', 'Teil');
        $sheet->setCellValue('B4', $teil);
        $sheet->setCellValue('A5', 'Gesamtanzahl Anwesenheitstage');
        $sheet->setCellValue('B5', $exportTotal);
        $sheet->setCellValue('C5', 'Schueleranzahl PA');
        $sheet->setCellValue('D5', $schueler->count());

        $headers = array_merge(['ID', 'Nachname', 'Vorname', 'W/M', 'Klasse'], array_values($tage), ['Summe']);
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 7], $header);
        }

        foreach ($schueler as $index => $item) {
            $person = $item->person;
            $row = $index + 8;
            $sheet->setCellValue([1, $row], $index + 1);
            $sheet->setCellValue([2, $row], $person?->nachname);
            $sheet->setCellValue([3, $row], $person?->vorname);
            $sheet->setCellValue([4, $row], $person?->geschlecht);
            $sheet->setCellValue([5, $row], $item->klasse);

            $summe = 0;
            $column = 6;
            foreach ($tage as $key => $label) {
                $status = $statusPayload[$item->id][$key] ?? $this->defaultAnwesenheitsdatenStatus($key);
                $value = match ($status) {
                    'present' => 'x',
                    'absent' => '-',
                    default => '',
                };
                if (in_array($key, $summenTage, true) && $status === 'present') {
                    $summe++;
                }
                $sheet->setCellValue([$column, $row], $value);
                $column++;
            }
            $sheet->setCellValue([$column, $row], $summe);
        }

        $lastColumn = count($headers);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($lastColumn);
        $lastRow = max(8, $schueler->count() + 7);
        $sheet->getStyle('A7:'.$lastColumnLetter.'7')->getFont()->setBold(true);
        $sheet->getStyle('A7:'.$lastColumnLetter.'7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF3F7');
        $sheet->getStyle('A7:'.$lastColumnLetter.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9DEE5');
        $sheet->getStyle('A7:'.$lastColumnLetter.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('D8:'.$lastColumnLetter.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (range(1, $lastColumn) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet(
            $spreadsheet,
            'Anwesenheitsdaten_'.$schulId.'_'.$this->safeName($schuljahr).'_Teil_'.$this->safeName($teil).'.xlsx'
        );
    }

    public function teilnehmerliste(int $schuleId, string $schuljahr, string $teil)
    {
        return $this->downloadSpreadsheet(
            $this->simpleSpreadsheet('Teilnehmerliste', $schuleId, $schuljahr, $teil),
            'Teilnehmerliste_'.$schuleId.'_'.$this->safeName($schuljahr).'_Teil_'.$this->safeName($teil).'.xlsx'
        );
    }

    public function createFolderAll(int $idSchule, string $schuljahr, string $teil)
    {
        $folder = $this->baseFolder($idSchule, $schuljahr, $teil);
        foreach (['Anwesenheit', 'Teilnehmerliste', 'Zertifikate_POBO', 'Auswertung_POBO', 'Auswertung_PA'] as $subfolder) {
            File::ensureDirectoryExists($folder.DIRECTORY_SEPARATOR.$subfolder);
        }

        return back()->with('success', 'BOP-Ordner wurden angelegt: '.$folder);
    }

    public function anwesenheitslisteVorbereitung(Request $request, int $schuleId, string $schuljahr, string $teil)
    {
        $termin = $request->query('termin');
        $klasse = $this->cleanQueryValue($request->query('klasse'));
        $partner = $this->partner($schuleId);
        $schueler = $this->schueler($schuleId, $schuljahr, $teil);

        if ($schueler->isEmpty()) {
            return back()->with('error', 'Die gewaehlte Schule verfuegt ueber keine Teilnehmer.');
        }

        if (! $termin) {
            return back()->with('error', 'Bitte waehle einen Termin fuer die Anwesenheitsliste BO Vorbereitung.');
        }

        $template = storage_path('vorlage/projekte/bop/excel/Anwesenheitsliste-Vorbereitung-BO-Tage.xlsx');
        if (! file_exists($template)) {
            return back()->with('error', 'Die Vorlage fuer die Anwesenheitsliste BO Vorbereitung wurde nicht gefunden.');
        }

        $terminDatum = $this->formatTermin($termin);
        $klassen = $klasse
            ? collect([$klasse])
            : $schueler->pluck('klasse')->filter()->unique()->sort()->values();

        if ($klassen->isEmpty()) {
            return back()->with('error', 'Es wurden keine Klassen fuer diesen Export gefunden.');
        }

        if ($klasse && ! $schueler->contains(fn ($item) => (string) $item->klasse === (string) $klasse)) {
            return back()->with('error', 'Die gewaehlte Klasse wurde fuer diese Schule nicht gefunden.');
        }

        if ($klasse) {
            $spreadsheet = $this->buildAnwesenheitslisteVorbereitungSpreadsheet(
                $template,
                $partner,
                $schueler->filter(fn ($item) => (string) $item->klasse === (string) $klasse)->values(),
                $schuljahr,
                $teil,
                $klasse,
                $terminDatum
            );

            return $this->downloadSpreadsheet(
                $spreadsheet,
                'Anwesenheitsliste_Vorbereitung_BO_Tage_'.$this->safeName($partner->name).'_'.$this->safeName($klasse).'_'.$this->safeName($terminDatum).'.xlsx'
            );
        }

        $tempDir = storage_path('app/tmp/'.Str::uuid());
        File::ensureDirectoryExists($tempDir);

        foreach ($klassen as $klasseName) {
            $spreadsheet = $this->buildAnwesenheitslisteVorbereitungSpreadsheet(
                $template,
                $partner,
                $schueler->filter(fn ($item) => (string) $item->klasse === (string) $klasseName)->values(),
                $schuljahr,
                $teil,
                $klasseName,
                $terminDatum
            );

            (new Xlsx($spreadsheet))->save(
                $tempDir.DIRECTORY_SEPARATOR.'Anwesenheitsliste_Vorbereitung_BO_Tage_'.$this->safeName($partner->name).'_'.$this->safeName($klasseName).'_'.$this->safeName($terminDatum).'.xlsx'
            );
        }

        $zipName = 'Anwesenheitslisten_Vorbereitung_BO_Tage_'.$this->safeName($partner->name).'_'.$this->safeName($terminDatum).'.zip';
        $zipPath = storage_path('app/tmp/'.Str::uuid().'_'.$zipName);
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            File::deleteDirectory($tempDir);

            return back()->with('error', 'Das ZIP-Archiv konnte nicht erstellt werden.');
        }

        foreach (glob($tempDir.DIRECTORY_SEPARATOR.'*.xlsx') as $file) {
            $zip->addFile($file, basename($file));
        }

        $zip->close();
        File::deleteDirectory($tempDir);

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    private function buildAnwesenheitslisteVorbereitungSpreadsheet(
        string $template,
        Partner $partner,
        $schueler,
        string $schuljahr,
        string $teil,
        string $klasse,
        string $terminDatum
    ): Spreadsheet {
        $spreadsheet = IOFactory::load($template);
        $sheet = $spreadsheet->getActiveSheet();
        $schulform = $this->schulformFromSchueler($schueler->first());

        $sheet->setCellValue('B2', $partner->name);
        $sheet->setCellValue('B4', $schulform);
        $sheet->setCellValue('B5', $klasse);
        $sheet->setCellValue('E6', $terminDatum);

        $row = 8;
        foreach ($schueler->sortBy(fn ($item) => $item->person?->nachname)->values() as $index => $item) {
            $person = $item->person;

            $sheet->setCellValue('A'.$row, $index + 1);
            $sheet->setCellValue('B'.$row, $person?->nachname);
            $sheet->setCellValue('C'.$row, $person?->vorname);
            $sheet->setCellValue('D'.$row, $person?->geschlecht);

            $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);

            $row++;
        }

        $this->attendanceFooter->applyToSpreadsheet($spreadsheet);

        return $spreadsheet;
    }

    private function cleanQueryValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || $value === 'alle' ? null : $value;
    }

    private function formatTermin(string $termin): string
    {
        foreach (['Y-m-d', 'd.m.Y', 'd/m/Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $termin);

            if ($date instanceof \DateTime) {
                return $date->format('d.m.Y');
            }
        }

        return $termin;
    }

    private function schulformFromSchueler($schueler): string
    {
        if (! $schueler) {
            return '';
        }

        return ($schueler->foerderschueler ?? $schueler->foederschueler ?? false)
            ? 'Foerderschule'
            : 'Gemeinschaftsschule';
    }

    public function anwesenheitslisteRechnung(int $idSchule, string $schuljahr, string $teil)
    {
        $spreadsheet = $this->simpleSpreadsheet(
            'Anwesenheitsliste Rechnung',
            $idSchule,
            $schuljahr,
            $teil,
            ['Anwesend', 'Bemerkung']
        );
        $this->attendanceFooter->applyToSpreadsheet($spreadsheet);

        return $this->downloadSpreadsheet(
            $spreadsheet,
            'Anwesenheitsliste_Rechnung_'.$idSchule.'_'.$this->safeName($schuljahr).'_Teil_'.$this->safeName($teil).'.xlsx'
        );
    }

    public function zertifikatPobo(int $idSchule, string $schuljahr, string $teil)
    {
        $template = storage_path('vorlage/projekte/bop/word/Zertifikat_Maske_POBO.docx');
        if (! file_exists($template)) {
            return back()->with('error', 'POBO-Zertifikat-Vorlage wurde nicht gefunden.');
        }

        $partner = $this->partner($idSchule);
        $item = $this->schueler($idSchule, $schuljahr, $teil)->first();
        if (! $item) {
            return back()->with('error', 'Es wurden keine Teilnehmer fuer dieses Zertifikat gefunden.');
        }

        $person = $item->person;
        $processor = new TemplateProcessor($template);
        foreach ([
            'vorname' => $person?->vorname,
            'nachname' => $person?->nachname,
            'klasse' => $item->klasse,
            'schule' => $partner->name,
            'schuljahr' => $schuljahr,
            'teil' => $teil,
        ] as $key => $value) {
            $processor->setValue($key, $value ?? '');
        }

        $fileName = 'Zertifikat_POBO_'.$this->safeName(($person?->nachname ?? 'Teilnehmer').'_'.($person?->vorname ?? $item->id)).'.docx';
        $path = storage_path('app/tmp/'.Str::uuid().'_'.$fileName);
        File::ensureDirectoryExists(dirname($path));
        $processor->saveAs($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function zertifikatPoboPdf(int $schuleId, string $schuljahr, string $teil)
    {
        $partner = $this->partner($schuleId);
        $schueler = $this->schueler($schuleId, $schuljahr, $teil);
        $pdf = Pdf::loadView('bop.zertifikate-pobo', compact('partner', 'schueler', 'schuljahr', 'teil'))->setPaper('a4', 'landscape');

        return $pdf->download('Zertifikate_POBO_'.$schuleId.'_'.$this->safeName($schuljahr).'_Teil_'.$this->safeName($teil).'.pdf');
    }

    public function auswertungPobo(int $schulId, string $schuljahr, string $teil)
    {
        $partner = $this->partner($schulId);
        $project = $this->currentBopProject();
        $teilnehmer = $this->bopEvaluations->schoolEntries($schulId, $schuljahr, $teil, $project);
        abort_if($teilnehmer->isEmpty(), 422, 'Für diese Schule wurden noch keine BOP-Werkstattgruppen gefunden.');
        $config = $this->bopEvaluations->config($project);
        abort_unless($config['enabled'] && count($config['criteria']), 422, 'Für dieses Projekt ist keine Bereichsauswertung konfiguriert.');
        $pdf = Pdf::loadView('pdf.bereichsauswertung', compact('teilnehmer', 'config'))->setPaper('a4', 'portrait');

        return $pdf->download('Auswertung_BOP_'.$this->safeName($partner->name).'_'.$this->safeName($schuljahr).'_'.$this->safeName($teil).'.pdf');
    }

    public function auswertungPoboToFolder(int $schulId, string $schuljahr, string $teil)
    {
        $folder = $this->baseFolder($schulId, $schuljahr, $teil).DIRECTORY_SEPARATOR.'Auswertung_POBO';
        File::ensureDirectoryExists($folder);

        $project = $this->currentBopProject();
        $teilnehmer = $this->bopEvaluations->schoolEntries($schulId, $schuljahr, $teil, $project);
        $config = $this->bopEvaluations->config($project);
        abort_if($teilnehmer->isEmpty(), 422, 'Für diese Schule wurden noch keine BOP-Werkstattgruppen gefunden.');
        abort_unless($config['enabled'] && count($config['criteria']), 422, 'Für dieses Projekt ist keine Bereichsauswertung konfiguriert.');

        foreach ($teilnehmer->groupBy('personen_id') as $participantEntries) {
            $participant = $participantEntries->first();
            $filename = $this->safeName(
                $participant['klasse'].'_'.$participant['nachname'].'_'.$participant['vorname']
            ).'.pdf';
            Pdf::loadView('pdf.bereichsauswertung', [
                'teilnehmer' => $participantEntries,
                'config' => $config,
            ])->setPaper('a4', 'portrait')->save($folder.DIRECTORY_SEPARATOR.$filename);
        }

        return back()->with('success', 'POBO-Auswertungen wurden im Ordner generiert.');
    }

    private function currentBopProject(): Projekt
    {
        $project = Projekt::findOrFail((int) auth()->user()?->current_team_id);
        abort_unless(str_contains(mb_strtolower((string) $project->name), 'bop'), 404, 'Diese Funktion ist nur im Projekt BOP verfügbar.');

        return $project;
    }

    public function auswertungPaToFolder(
        int $schulId,
        string $schuljahr,
        string $teil,
        PotenzialanalyseReportService $reports
    ) {
        $partner = $this->partner($schulId);
        $projektId = (int) auth()->user()?->current_team_id;
        $assignments = $reports->schoolAssignments($schulId, $schuljahr, $teil, $projektId);

        if ($assignments->isEmpty()) {
            return back()->with('error', 'Für diese Schule wurden noch keine PA-Daten gespeichert.');
        }

        $folder = storage_path(
            'app/public/files/Schulen/'
            .$this->safeFolderSegment($partner->name)
            .'/'.$this->safeFolderSegment($schuljahr)
        );

        foreach ($assignments as $assignment) {
            $class = $this->safeFolderSegment((string) ($assignment['student']?->klasse ?? 'ohne Klasse'));
            $participant = $this->safeFolderSegment(trim(
                ($assignment['person']->nachname ?? '').' '.($assignment['person']->vorname ?? '')
            ));
            $participantFolder = $folder.DIRECTORY_SEPARATOR.$class.DIRECTORY_SEPARATOR.$participant;

            $reports->writePdf($assignment['gruppe'], $assignment['person'], $participantFolder);
        }

        return back()->with(
            'success',
            $assignments->count().' PA-Bericht(e) wurden für '.$partner->name.' im Ordner generiert: '.$folder
        );
    }

    public function schulAnwesenheitExport(Request $request, int $schulId, string $schuljahr, string $teil)
    {
        $validated = $request->validate([
            'von' => ['required', 'date'],
            'bis' => ['required', 'date', 'after_or_equal:von'],
        ]);
        $partner = $this->partner($schulId);
        $von = Carbon::parse($validated['von'])->startOfDay();
        $bis = Carbon::parse($validated['bis'])->startOfDay();
        abort_if($von->diffInDays($bis) > 366, 422, 'Der Zeitraum darf höchstens 366 Tage umfassen.');

        $schueler = $this->schueler($schulId, $schuljahr, $teil);
        $klassenNachPerson = $schueler->pluck('klasse', 'person_id');
        $projektId = (int) auth()->user()->current_team_id;
        $eintraege = GruppeHasPersonen::query()
            ->with(['teilnehmer', 'status', 'tag', 'zeitgeplant', 'zeittatsaechlich', 'gruppe.bereich'])
            ->whereIn('personen_id', $schueler->pluck('person_id')->filter())
            ->whereHas('gruppe', fn ($query) => $query->where('projekt_id', $projektId))
            ->whereHas('tag', fn ($query) => $query->whereBetween('datum', [$von->toDateString(), $bis->toDateString()]))
            ->get()
            ->sortBy(fn ($entry) => sprintf(
                '%s|%s|%s|%s',
                $entry->tag?->datum,
                $klassenNachPerson[$entry->personen_id] ?? '',
                $entry->teilnehmer?->nachname ?? '',
                $entry->teilnehmer?->vorname ?? ''
            ))
            ->values();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Schulanwesenheit');
        $sheet->setShowGridlines(false);
        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', 'Anwesenheit · '.$partner->name);
        $sheet->mergeCells('A2:J2');
        $teilLabel = str_starts_with(mb_strtolower($teil), 'teil') ? $teil : 'Teil '.$teil;
        $sheet->setCellValue('A2', 'Schuljahr '.$schuljahr.' · '.$teilLabel.' · '.$von->format('d.m.Y').($von->equalTo($bis) ? '' : ' - '.$bis->format('d.m.Y')));
        $headers = ['Datum', 'Klasse', 'Nachname', 'Vorname', 'Bereich / Gruppe', 'Status', 'Geplant', 'Tatsächlich', 'Verspätung', 'Bemerkung'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 4], $header);
        }

        foreach ($eintraege as $index => $entry) {
            $row = $index + 5;
            $planned = $entry->zeitgeplant?->startzeit;
            $actual = $entry->zeittatsaechlich?->startzeit;
            $delay = $this->attendanceDelayMinutes($planned, $actual);
            $sheet->setCellValue([1, $row], $entry->tag?->datum ? Carbon::parse($entry->tag->datum)->format('d.m.Y') : '');
            $sheet->setCellValueExplicit([2, $row], (string) ($klassenNachPerson[$entry->personen_id] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue([3, $row], $entry->teilnehmer?->nachname ?? '');
            $sheet->setCellValue([4, $row], $entry->teilnehmer?->vorname ?? '');
            $sheet->setCellValue([5, $row], $entry->gruppe?->bereich?->name ?? ('Gruppe '.$entry->gruppe_id));
            $sheet->setCellValue([6, $row], $entry->status?->status ?? 'Nicht erfasst');
            $sheet->setCellValue([7, $row], $planned ? substr((string) $planned, 0, 5) : '');
            $sheet->setCellValue([8, $row], $actual ? substr((string) $actual, 0, 5) : '');
            $sheet->setCellValue([9, $row], $delay > 0 ? $delay.' Min.' : '');
            $sheet->setCellValue([10, $row], $entry->bemerkung ?? '');
        }

        if ($eintraege->isEmpty()) {
            $sheet->mergeCells('A5:J5');
            $sheet->setCellValue('A5', 'Für den ausgewählten Zeitraum sind keine gespeicherten Gruppenanwesenheiten vorhanden.');
        }
        $lastRow = max(5, $eintraege->count() + 4);
        $sheet->getStyle('A1:J1')->getFont()->setBold(true)->setSize(18)->getColor()->setARGB('FF173B57');
        $sheet->getStyle('A1:J2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A4:J4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE4C7');
        $sheet->getStyle('A4:J4')->getFont()->setBold(true)->getColor()->setARGB('FF173B57');
        $sheet->getStyle('A4:J'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9E2EC');
        $sheet->getStyle('A5:J'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        foreach ([1 => 13, 2 => 10, 3 => 20, 4 => 18, 5 => 24, 6 => 17, 7 => 11, 8 => 11, 9 => 13, 10 => 28] as $column => $width) {
            $sheet->getColumnDimensionByColumn($column)->setWidth($width);
        }
        $sheet->freezePane('A5');
        $logoRow = $lastRow + 2;
        $this->addSchoolAttendanceLogos($sheet, $logoRow);
        $sheet->getRowDimension($logoRow)->setRowHeight(62);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        $sheet->getPageSetup()->setPrintArea('A1:J'.$logoRow);
        $sheet->getHeaderFooter()->setOddFooter('&L'.$partner->name.'&CSeite &P von &N&RStand: '.now()->format('d.m.Y H:i'));

        return $this->downloadSpreadsheet(
            $spreadsheet,
            'Schulanwesenheit_'.$this->safeName($partner->name).'_'.$von->format('Y-m-d').($von->equalTo($bis) ? '' : '_bis_'.$bis->format('Y-m-d')).'.xlsx'
        );
    }

    private function attendanceDelayMinutes(?string $planned, ?string $actual): int
    {
        if (! $planned || ! $actual) {
            return 0;
        }

        $plannedAt = Carbon::createFromFormat('H:i:s', strlen($planned) === 5 ? $planned.':00' : $planned);
        $actualAt = Carbon::createFromFormat('H:i:s', strlen($actual) === 5 ? $actual.':00' : $actual);

        return max(0, $plannedAt->diffInMinutes($actualAt, false));
    }

    private function addSchoolAttendanceLogos($sheet, int $row): void
    {
        $logos = [
            [public_path('img/einteilung-export/zbb.png'), 'B'.$row, 30, 'ZBB'],
            [public_path('img/einteilung-export/partner-berufsorientierung.png'), 'C'.$row, 25, 'Berufsorientierung'],
            [public_path('img/einteilung-export/partner-ministerium-saarland.png'), 'E'.$row, 31, 'Ministerium Saarland'],
            [public_path('img/einteilung-export/partner-bundesministerium.png'), 'G'.$row, 31, 'Bundesministerium'],
            [public_path('img/einteilung-export/partner-bibb.png'), 'I'.$row, 25, 'BIBB'],
        ];

        foreach ($logos as [$path, $coordinate, $height, $name]) {
            if (! file_exists($path)) {
                continue;
            }
            $drawing = new Drawing;
            $drawing->setName($name);
            $drawing->setDescription($name.' Logo');
            $drawing->setPath($path);
            $drawing->setCoordinates($coordinate);
            $drawing->setHeight($height);
            $drawing->setOffsetX(5);
            $drawing->setOffsetY(5);
            $drawing->setWorksheet($sheet);
        }
    }

    public function auswertungPoboRunde(int $schuleId, string $schuljahr, string $teil, Request $request)
    {
        $partner = $this->partner($schuleId);
        $schueler = $this->schueler($schuleId, $schuljahr, $teil);
        $pdf = Pdf::loadView('bop.auswertung-pobo-runde', [
            'partner' => $partner,
            'schueler' => $schueler,
            'schuljahr' => $schuljahr,
            'teil' => $teil,
            'runde' => $request->query('runde', 'alle'),
        ]);

        return $pdf->download('Auswertung_POBO_Runde_'.$schuleId.'_'.$this->safeName($schuljahr).'_Teil_'.$this->safeName($teil).'.pdf');
    }
}
