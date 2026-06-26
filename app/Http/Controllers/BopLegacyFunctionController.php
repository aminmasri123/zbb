<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\PersonenIstSchueler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;
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
        $path = storage_path('app/tmp/' . $filename);
        File::ensureDirectoryExists(dirname($path));
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function anwesenheitsdaten(int $schulId, string $schuljahr, string $teil)
    {
        $partner = $this->partner($schulId);
        $schueler = $this->schueler($schulId, $schuljahr, $teil);

        return response()->view('bop.anwesenheitsdaten', compact('partner', 'schueler', 'schuljahr', 'teil'));
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

    public function anwesenheitslisteVorbereitung(int $schuleId, string $schuljahr, string $teil)
    {
        return $this->downloadSpreadsheet(
            $this->simpleSpreadsheet('Anwesenheitsliste BO Vorbereitung', $schuleId, $schuljahr, $teil, ['Termin', 'Unterschrift']),
            'Anwesenheitsliste_BO_Vorbereitung_' . $schuleId . '_' . $this->safeName($schuljahr) . '_Teil_' . $this->safeName($teil) . '.xlsx'
        );
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
