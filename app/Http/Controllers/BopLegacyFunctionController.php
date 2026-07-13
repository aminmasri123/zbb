<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\PersonenIstSchueler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\TemplateProcessor;
use Barryvdh\DomPDF\Facade\Pdf;
use ZipArchive;

class BopLegacyFunctionController extends Controller
{
    private function schueler(int $schuleId, string $schuljahr, string $teil)
    {
        return PersonenIstSchueler::with(['person', 'einteilungen'])
            ->where('schule_id', $schuleId)
            ->where('schuljahr', $schuljahr)
            ->where('teil', $teil)
            ->orderBy('klasse')
            ->get()
            ->sortBy(fn ($schueler) => $schueler->person?->nachname)
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

    private function baseFolder(int $schuleId, string $schuljahr, string $teil): string
    {
        $partner = $this->partner($schuleId);
        $folder = storage_path('app/bop/' . $this->safeName($partner->name) . '/' . $this->safeName($schuljahr) . '/Teil_' . $this->safeName($teil));

        File::ensureDirectoryExists($folder);

        return $folder;
    }

    private function simpleSpreadsheet(string $title, int $schuleId, string $schuljahr, string $teil, array $extraColumns = [])
    {
        $partner = $this->partner($schuleId);
        $schueler = $this->schueler($schuleId, $schuljahr, $teil);
        $spreadsheet = new Spreadsheet();
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
        $path = storage_path('app/tmp/' . Str::uuid() . '_' . $filename);
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

        $spreadsheet = new Spreadsheet();
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
        $sheet->getStyle('A7:' . $lastColumnLetter . '7')->getFont()->setBold(true);
        $sheet->getStyle('A7:' . $lastColumnLetter . '7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF3F7');
        $sheet->getStyle('A7:' . $lastColumnLetter . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD9DEE5');
        $sheet->getStyle('A7:' . $lastColumnLetter . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('D8:' . $lastColumnLetter . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (range(1, $lastColumn) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet(
            $spreadsheet,
            'Anwesenheitsdaten_' . $schulId . '_' . $this->safeName($schuljahr) . '_Teil_' . $this->safeName($teil) . '.xlsx'
        );
    }

    public function teilnehmerliste(int $schuleId, string $schuljahr, string $teil)
    {
        return $this->downloadSpreadsheet(
            $this->simpleSpreadsheet('Teilnehmerliste', $schuleId, $schuljahr, $teil),
            'Teilnehmerliste_' . $schuleId . '_' . $this->safeName($schuljahr) . '_Teil_' . $this->safeName($teil) . '.xlsx'
        );
    }

    public function createFolderAll(int $idSchule, string $schuljahr, string $teil)
    {
        $folder = $this->baseFolder($idSchule, $schuljahr, $teil);
        foreach (['Anwesenheit', 'Teilnehmerliste', 'Zertifikate_POBO', 'Auswertung_POBO', 'Auswertung_PA'] as $subfolder) {
            File::ensureDirectoryExists($folder . DIRECTORY_SEPARATOR . $subfolder);
        }

        return back()->with('success', 'BOP-Ordner wurden angelegt: ' . $folder);
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

        if (!$termin) {
            return back()->with('error', 'Bitte waehle einen Termin fuer die Anwesenheitsliste BO Vorbereitung.');
        }

        $template = storage_path('vorlage/projekte/bop/excel/Anwesenheitsliste-Vorbereitung-BO-Tage.xlsx');
        if (!file_exists($template)) {
            return back()->with('error', 'Die Vorlage fuer die Anwesenheitsliste BO Vorbereitung wurde nicht gefunden.');
        }

        $terminDatum = $this->formatTermin($termin);
        $klassen = $klasse
            ? collect([$klasse])
            : $schueler->pluck('klasse')->filter()->unique()->sort()->values();

        if ($klassen->isEmpty()) {
            return back()->with('error', 'Es wurden keine Klassen fuer diesen Export gefunden.');
        }

        if ($klasse && !$schueler->contains(fn ($item) => (string) $item->klasse === (string) $klasse)) {
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
                'Anwesenheitsliste_Vorbereitung_BO_Tage_' . $this->safeName($partner->name) . '_' . $this->safeName($klasse) . '_' . $this->safeName($terminDatum) . '.xlsx'
            );
        }

        $tempDir = storage_path('app/tmp/' . Str::uuid());
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
                $tempDir . DIRECTORY_SEPARATOR . 'Anwesenheitsliste_Vorbereitung_BO_Tage_' . $this->safeName($partner->name) . '_' . $this->safeName($klasseName) . '_' . $this->safeName($terminDatum) . '.xlsx'
            );
        }

        $zipName = 'Anwesenheitslisten_Vorbereitung_BO_Tage_' . $this->safeName($partner->name) . '_' . $this->safeName($terminDatum) . '.zip';
        $zipPath = storage_path('app/tmp/' . Str::uuid() . '_' . $zipName);
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            File::deleteDirectory($tempDir);
            return back()->with('error', 'Das ZIP-Archiv konnte nicht erstellt werden.');
        }

        foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*.xlsx') as $file) {
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

            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $person?->nachname);
            $sheet->setCellValue('C' . $row, $person?->vorname);
            $sheet->setCellValue('D' . $row, $person?->geschlecht);

            $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);

            $row++;
        }

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
        if (!$schueler) {
            return '';
        }

        return ($schueler->foerderschueler ?? $schueler->foederschueler ?? false)
            ? 'Foerderschule'
            : 'Gemeinschaftsschule';
    }

    public function anwesenheitslisteRechnung(int $idSchule, string $schuljahr, string $teil)
    {
        return $this->downloadSpreadsheet(
            $this->simpleSpreadsheet('Anwesenheitsliste Rechnung', $idSchule, $schuljahr, $teil, ['Anwesend', 'Bemerkung']),
            'Anwesenheitsliste_Rechnung_' . $idSchule . '_' . $this->safeName($schuljahr) . '_Teil_' . $this->safeName($teil) . '.xlsx'
        );
    }

    public function zertifikatPobo(int $idSchule, string $schuljahr, string $teil)
    {
        $template = storage_path('vorlage/projekte/bop/word/Zertifikat_Maske_POBO.docx');
        if (!file_exists($template)) {
            return back()->with('error', 'POBO-Zertifikat-Vorlage wurde nicht gefunden.');
        }

        $partner = $this->partner($idSchule);
        $item = $this->schueler($idSchule, $schuljahr, $teil)->first();
        if (!$item) {
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

        $fileName = 'Zertifikat_POBO_' . $this->safeName(($person?->nachname ?? 'Teilnehmer') . '_' . ($person?->vorname ?? $item->id)) . '.docx';
        $path = storage_path('app/tmp/' . Str::uuid() . '_' . $fileName);
        File::ensureDirectoryExists(dirname($path));
        $processor->saveAs($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function zertifikatPoboPdf(int $schuleId, string $schuljahr, string $teil)
    {
        $partner = $this->partner($schuleId);
        $schueler = $this->schueler($schuleId, $schuljahr, $teil);
        $pdf = Pdf::loadView('bop.zertifikate-pobo', compact('partner', 'schueler', 'schuljahr', 'teil'))->setPaper('a4', 'landscape');

        return $pdf->download('Zertifikate_POBO_' . $schuleId . '_' . $this->safeName($schuljahr) . '_Teil_' . $this->safeName($teil) . '.pdf');
    }

    public function auswertungPobo(int $schulId, string $schuljahr, string $teil)
    {
        $partner = $this->partner($schulId);
        $schueler = $this->schueler($schulId, $schuljahr, $teil);
        $pdf = Pdf::loadView('bop.auswertung-pobo', compact('partner', 'schueler', 'schuljahr', 'teil'));

        return $pdf->download('Auswertung_POBO_' . $schulId . '_' . $this->safeName($schuljahr) . '_Teil_' . $this->safeName($teil) . '.pdf');
    }

    public function auswertungPoboToFolder(int $schulId, string $schuljahr, string $teil)
    {
        $folder = $this->baseFolder($schulId, $schuljahr, $teil) . DIRECTORY_SEPARATOR . 'Auswertung_POBO';
        File::ensureDirectoryExists($folder);

        foreach ($this->schueler($schulId, $schuljahr, $teil) as $item) {
            $partner = $this->partner($schulId);
            $schueler = collect([$item]);
            Pdf::loadView('bop.auswertung-pobo', compact('partner', 'schueler', 'schuljahr', 'teil'))
                ->save($folder . DIRECTORY_SEPARATOR . $this->safeName($item->person?->nachname . '_' . $item->person?->vorname) . '.pdf');
        }

        return back()->with('success', 'POBO-Auswertungen wurden im Ordner generiert.');
    }

    public function auswertungPaToFolder(int $schulId, string $schuljahr, string $teil)
    {
        $folder = $this->baseFolder($schulId, $schuljahr, $teil) . DIRECTORY_SEPARATOR . 'Auswertung_PA';
        File::ensureDirectoryExists($folder);
        $spreadsheet = $this->simpleSpreadsheet('PA Berichte', $schulId, $schuljahr, $teil, ['Bericht erstellt']);
        (new Xlsx($spreadsheet))->save($folder . DIRECTORY_SEPARATOR . 'PA_Berichte.xlsx');

        return back()->with('success', 'PA-Berichte wurden im Ordner generiert.');
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

        return $pdf->download('Auswertung_POBO_Runde_' . $schuleId . '_' . $this->safeName($schuljahr) . '_Teil_' . $this->safeName($teil) . '.pdf');
    }
}
