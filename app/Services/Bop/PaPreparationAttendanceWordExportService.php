<?php

namespace App\Services\Bop;

use App\Models\Partner;
use App\Models\PersonenIstSchueler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpWord\PhpWord;

class PaPreparationAttendanceWordExportService
{
    private const PAGE_MARGIN = 850; // 15 mm

    public function __construct(private readonly AttendanceFooterService $attendanceFooter)
    {
    }

    public function create(
        Partner $school,
        Collection $participants,
        array $day,
        array $signatures,
        string $schoolYear,
        string $part,
        string $exportMode,
        ?string $className,
        string $exportFormat,
        string $outputPath
    ): void {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(9);

        $isA3 = strtoupper($exportFormat) === 'A3';
        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'pageSizeW' => $isA3 ? 23811 : 16838,
            'pageSizeH' => $isA3 ? 16838 : 11906,
            'marginTop' => self::PAGE_MARGIN,
            'marginRight' => self::PAGE_MARGIN,
            'marginBottom' => 1250,
            'marginLeft' => self::PAGE_MARGIN,
            'headerHeight' => 300,
            'footerHeight' => 300,
        ]);

        $section->addText(
            'Anwesenheitsliste Vorbereitung Potenzialanalyse (PA)',
            ['bold' => true, 'size' => 14],
            ['alignment' => 'center', 'spaceAfter' => 180]
        );

        $schoolForm = $this->schoolForm($participants);
        $classLabel = $exportMode === 'klasse' && filled($className)
            ? $className
            : $participants->pluck('klasse')->filter()->unique()->implode(', ');
        $dateLabel = !empty($day['date'])
            ? date('d.m.Y', strtotime((string) $day['date']))
            : '';

        $metadata = $section->addTable([
            'width' => 100 * 50,
            'unit' => 'pct',
            'borderSize' => 0,
            'cellMargin' => 40,
        ]);
        $metadata->addRow();
        $metadata->addCell(7600)->addText('Schule: ' . $school->name, ['bold' => true]);
        $metadata->addCell(7600)->addText('Schuljahr: ' . $schoolYear . ' · Teil: ' . $part);
        $metadata->addRow();
        $metadata->addCell(7600)->addText('Schulform: ' . $schoolForm);
        $metadata->addCell(7600)->addText('Klasse(n): ' . ($classLabel ?: 'alle'));
        $metadata->addRow();
        $metadata->addCell(7600)->addText('Termin Vorbereitung PA: ' . $dateLabel, ['bold' => true]);
        $metadata->addCell(7600)->addText('Teilnehmer/-innen: ' . $participants->count());
        $section->addTextBreak(1);

        $phpWord->addTableStyle('PaPreparationAttendance', [
            'borderSize' => 8,
            'borderColor' => '1F2937',
            'cellMargin' => 70,
            'width' => 100 * 50,
            'unit' => 'pct',
        ], [
            'bgColor' => 'E5E7EB',
        ]);
        $table = $section->addTable('PaPreparationAttendance');
        $header = $table->addRow(620, ['tblHeader' => true, 'cantSplit' => true]);
        foreach ([
            [600, 'Nr.'],
            [2800, 'Name'],
            [2600, 'Vorname'],
            [1300, 'Klasse'],
            [7838, "Unterschrift\n{$dateLabel}"],
        ] as [$width, $label]) {
            $header->addCell($width, ['valign' => 'center'])
                ->addText($label, ['bold' => true], ['alignment' => 'center']);
        }

        $signatureFiles = [];

        try {
            foreach ($participants->values() as $index => $participant) {
                $person = $participant->person;
                $row = $table->addRow(620, ['cantSplit' => true]);
                $row->addCell(600, ['valign' => 'center'])->addText((string) ($index + 1), [], ['alignment' => 'center']);
                $row->addCell(2800, ['valign' => 'center'])->addText((string) ($person?->nachname ?? ''));
                $row->addCell(2600, ['valign' => 'center'])->addText((string) ($person?->vorname ?? ''));
                $row->addCell(1300, ['valign' => 'center'])->addText((string) ($participant->klasse ?? ''), [], ['alignment' => 'center']);
                $signatureCell = $row->addCell(7838, ['valign' => 'center']);
                $signature = $this->signatureFor($day, (int) $participant->person_id, $signatures);
                $signaturePath = $this->writeSignatureImage($signature);

                if ($signaturePath) {
                    $signatureFiles[] = $signaturePath;
                    $signatureCell->addImage($signaturePath, [
                        'width' => 150,
                        'height' => 38,
                        'alignment' => 'center',
                    ]);
                }
            }

            $footer = $section->addFooter();
            $footer->addImage($this->attendanceFooter->imagePath(), [
                'width' => 535,
                'alignment' => 'center',
            ]);

            File::ensureDirectoryExists(dirname($outputPath));
            $phpWord->save($outputPath, 'Word2007');
            $this->attendanceFooter->applyToWordDocument($outputPath);
        } finally {
            foreach ($signatureFiles as $signatureFile) {
                File::delete($signatureFile);
            }
        }
    }

    private function signatureFor(array $day, int $personId, array $signatures): ?string
    {
        $dayId = (string) ($day['id'] ?? '');
        $directKey = $dayId . ':' . $personId;
        if (is_string($signatures[$directKey] ?? null) && $signatures[$directKey] !== '') {
            return $signatures[$directKey];
        }

        $suffix = ':' . $personId;
        $date = (string) ($day['date'] ?? '');

        foreach ($signatures as $key => $signature) {
            if (!is_string($key) || !is_string($signature) || $signature === '' || !str_ends_with($key, $suffix)) {
                continue;
            }

            $storedDayId = substr($key, 0, -strlen($suffix));
            if ($date !== '' && str_contains($storedDayId, $date)
                && (str_contains(mb_strtolower($storedDayId), 'vorbereitung')
                    || str_contains(mb_strtolower($storedDayId), 'preparation'))) {
                return $signature;
            }
        }

        return null;
    }

    private function writeSignatureImage(?string $signature): ?string
    {
        if (!$signature || !preg_match('/^data:image\/png;base64,(.+)$/s', $signature, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[1], true);
        if ($binary === false || !str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        $directory = storage_path('app/tmp/pa-word-signatures');
        File::ensureDirectoryExists($directory);
        $path = $directory . DIRECTORY_SEPARATOR . uniqid('signature_', true) . '.png';
        File::put($path, $binary);

        return $path;
    }

    private function schoolForm(Collection $participants): string
    {
        if ($participants->isEmpty()) {
            return '';
        }

        $specialNeedsCount = $participants->filter(fn (PersonenIstSchueler $participant) =>
            (bool) ($participant->foerderschueler ?? $participant->foederschueler ?? false)
        )->count();

        return ($specialNeedsCount / $participants->count()) > 0.5
            ? 'Förderschule'
            : 'Gemeinschaftsschule';
    }
}
