<?php

namespace App\Services\Scheduling;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BopTimetableSpreadsheetExporter
{
    private const AREA_COLOURS = [
        ['fill' => 'FFEDD5', 'border' => 'F97316'],
        ['fill' => 'D1FAE5', 'border' => '10B981'],
        ['fill' => 'CFFAFE', 'border' => '06B6D4'],
        ['fill' => 'FEF3C7', 'border' => 'F59E0B'],
        ['fill' => 'FFE4E6', 'border' => 'F43F5E'],
    ];

    public function create(array $timetable, string $schoolName): string
    {
        $config = $timetable['config'];
        $groups = array_values($config['groups']);
        $entries = array_values($timetable['entries']);
        $start = $this->minutes($config['start_time']);
        $end = $this->minutes($config['end_time']);
        $minuteCount = max(1, $end - $start);
        $lastColumnIndex = $minuteCount + 1;
        $lastColumn = Coordinate::stringFromColumnIndex($lastColumnIndex);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Zeitplan');
        $sheet->getSheetView()->setZoomScale(70);

        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', 'Zeitplan · '.$schoolName.' · '.$this->dateLabel($timetable['schedule_date']));
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '111827']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', 'Alle Zeiten werden minutengenau dargestellt. Bereiche, Pausen und gemeinsame Aktivitäten sind farblich gekennzeichnet.');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
        $sheet->getRowDimension(2)->setRowHeight(19);

        $headerRow = 4;
        $sheet->setCellValue("A{$headerRow}", 'Gruppe');
        $sheet->getColumnDimension('A')->setWidth(12);
        for ($minute = 0; $minute < $minuteCount; $minute++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($minute + 2))->setWidth(0.45);
        }

        for ($offset = 0; $offset < $minuteCount; $offset += 15) {
            $fromColumn = Coordinate::stringFromColumnIndex($offset + 2);
            $toColumn = Coordinate::stringFromColumnIndex(min($minuteCount, $offset + 15) + 1);
            if ($fromColumn !== $toColumn) {
                $sheet->mergeCells("{$fromColumn}{$headerRow}:{$toColumn}{$headerRow}");
            }
            $sheet->setCellValue("{$fromColumn}{$headerRow}", $this->timeLabel($start + $offset));
        }

        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '111827']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FBBF24']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B45309']]],
        ]);
        $sheet->getStyle("A{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($headerRow)->setRowHeight(26);

        foreach ($groups as $groupIndex => $group) {
            $row = $headerRow + 1 + $groupIndex;
            $sheet->setCellValueExplicit("A{$row}", (string) $group, DataType::TYPE_STRING);
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FBBF24']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '9CA3AF']]],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(52);

            foreach ($this->entriesForGroup($entries, $group) as $entry) {
                $entryStart = max($start, $this->minutes($entry['start_time']));
                $entryEnd = min($end, $this->minutes($entry['end_time']));
                if ($entryEnd <= $entryStart) {
                    continue;
                }

                $fromIndex = ($entryStart - $start) + 2;
                $toIndex = ($entryEnd - $start) + 1;
                $fromColumn = Coordinate::stringFromColumnIndex($fromIndex);
                $toColumn = Coordinate::stringFromColumnIndex($toIndex);
                $range = "{$fromColumn}{$row}:{$toColumn}{$row}";
                if ($fromIndex < $toIndex) {
                    $sheet->mergeCells($range);
                }

                $supervisor = trim((string) data_get($entry, 'meta.supervisor_name', ''));
                $text = $entry['title']."\n".substr($entry['start_time'], 0, 5).'–'.substr($entry['end_time'], 0, 5);
                if ($supervisor !== '') {
                    $text .= "\n".$supervisor;
                }
                $sheet->setCellValueExplicit("{$fromColumn}{$row}", $text, DataType::TYPE_STRING);

                $colours = $this->colours($entry);
                $sheet->getStyle($range)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '172033']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colours['fill']]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                        'shrinkToFit' => true,
                    ],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $colours['border']]]],
                ]);
            }
        }

        $lastRow = $headerRow + count($groups);
        $sheet->freezePane('B5');
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A3)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        $sheet->getPageSetup()->setPrintArea("A1:{$lastColumn}{$lastRow}");
        $sheet->getPageMargins()->setTop(0.3)->setRight(0.25)->setBottom(0.3)->setLeft(0.25);
        $sheet->getHeaderFooter()->setOddFooter('&LZeitplan · '.$schoolName.'&RSeite &P von &N');

        $path = tempnam(sys_get_temp_dir(), 'bop-zeitplan-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function entriesForGroup(array $entries, string $group): array
    {
        $filtered = array_filter($entries, function (array $entry) use ($group) {
            if (! empty($entry['group_key'])) {
                return $entry['group_key'] === $group;
            }

            $groupLabels = data_get($entry, 'meta.group_labels');

            return ! is_array($groupLabels) || in_array($group, $groupLabels, true);
        });

        usort($filtered, fn (array $left, array $right) => strcmp($left['start_time'], $right['start_time']));

        return $filtered;
    }

    private function colours(array $entry): array
    {
        return match ($entry['type']) {
            'break' => ['fill' => 'E2E8F0', 'border' => '64748B'],
            'shared' => ['fill' => 'DBEAFE', 'border' => '3B82F6'],
            'extra' => ['fill' => 'EDE9FE', 'border' => '8B5CF6'],
            default => self::AREA_COLOURS[abs((int) ($entry['bereich_id'] ?? 0)) % count(self::AREA_COLOURS)],
        };
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));

        return ($hours * 60) + $minutes;
    }

    private function timeLabel(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function dateLabel(string $date): string
    {
        return date('d.m.Y', strtotime($date));
    }
}
