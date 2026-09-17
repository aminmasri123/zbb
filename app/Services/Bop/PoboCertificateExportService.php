<?php

namespace App\Services\Bop;

use App\Models\BopRun;
use App\Models\GruppeHasPersonen;
use App\Models\PoboCertificatePrintSetting;
use App\Models\Projekt;
use App\Services\SaarlandWorkdayService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use ZipArchive;

class PoboCertificateExportService
{
    public function __construct(
        private readonly BopEvaluationExportService $bopEntries,
        private readonly SaarlandWorkdayService $workdays,
    ) {}

    public function previewSchool(int $schoolId, string $schoolYear, string $part, Projekt $project): array
    {
        $context = $this->schoolContext($schoolId, $schoolYear, $part, $project);
        $eligible = $this->eligibleParticipants($context['entries'], $context['attendance']);
        $period = $this->commonPeriod(
            $context['entries'], $context['attendance'], $schoolId, $schoolYear, $part, $project
        );
        $total = $context['entries']->pluck('personen_id')->unique()->count();

        return [
            'period' => [
                ...$period,
                'from_formatted' => $this->formatDate($period['from']),
                'to_formatted' => $this->formatDate($period['to']),
            ],
            'participants' => $eligible->map(fn (array $item) => [
                'id' => (int) $item['first']['personen_id'],
                'name' => trim($item['first']['vorname'].' '.$item['first']['nachname']),
                'class' => $item['first']['klasse'],
                'document_type' => $item['type'],
                'document_label' => $item['type'] === 'certificate' ? 'Zertifikat' : 'Teilnahmebescheinigung',
                'attendance_days' => $item['all_dates']->count(),
            ])->values(),
            'total_participants' => $total,
            'eligible_participants' => $eligible->count(),
            'excluded_participants' => max(0, $total - $eligible->count()),
            'print_settings' => $this->printSettings(),
        ];
    }

    public function printSettings(): array
    {
        $settings = PoboCertificatePrintSetting::current();

        return [
            'horizontal_offset_mm' => (float) $settings->horizontal_offset_mm,
            'vertical_offset_mm' => (float) $settings->vertical_offset_mm,
            'row_spacing_offset_mm' => (float) $settings->row_spacing_offset_mm,
            'cross_font_size_pt' => (float) $settings->cross_font_size_pt,
        ];
    }

    /**
     * @return Collection<int, array{path: string, filename: string, type: string, values: array<string, string>}>
     */
    public function createSchoolDocuments(
        int $schoolId,
        string $schoolYear,
        string $part,
        Projekt $project,
        string $destination,
        ?string $periodStart = null,
        ?string $periodEnd = null,
    ): Collection {
        $context = $this->schoolContext($schoolId, $schoolYear, $part, $project);
        if ($context['entries']->isEmpty()) {
            return collect();
        }

        $period = $this->commonPeriod(
            $context['entries'], $context['attendance'], $schoolId, $schoolYear, $part, $project
        );
        $periodStart = $periodStart ?: $period['from'];
        $periodEnd = $periodEnd ?: $period['to'];
        if (! $periodStart || ! $periodEnd) {
            throw new RuntimeException('Für die POBO-Zertifikate konnte kein gemeinsamer Zeitraum ermittelt werden.');
        }

        $templateDirectory = storage_path('vorlage/projekte/bop/word');
        $templates = [
            'certificate' => [
                'regular' => $templateDirectory.'/Zertifikat_Maske_POBO.docx',
                'small' => $templateDirectory.'/Zertifikat_Maske_POBO_klein_text.docx',
            ],
            'participation' => [
                'regular' => $templateDirectory.'/Teilnahmebescheinigung_Maske_POBO.docx',
                'small' => $templateDirectory.'/Teilnahmebescheinigung_Maske_POBO_klein_text.docx',
            ],
        ];
        foreach (collect($templates)->flatten() as $template) {
            if (! is_file($template)) {
                throw new RuntimeException('Eine Originalvorlage für POBO-Zertifikate wurde nicht gefunden.');
            }
        }

        File::ensureDirectoryExists($destination);

        $printSettings = $this->printSettings();
        foreach ($templates as $type => $variants) {
            foreach ($variants as $variant => $template) {
                $templates[$type][$variant] = $this->calibratedTemplate(
                    $template,
                    $destination,
                    $printSettings,
                );
            }
        }

        return $this->eligibleParticipants($context['entries'], $context['attendance'])
            ->map(function (array $participant) use ($templates, $destination, $periodStart, $periodEnd) {
                $first = $participant['first'];
                $type = $participant['type'];
                $fullName = trim($first['vorname'].' '.$first['nachname']);
                $template = $templates[$type][mb_strlen($fullName) <= 19 ? 'regular' : 'small'];
                $values = array_merge([
                    'vorname' => $first['vorname'],
                    'nachname' => $first['nachname'],
                    'anfangsdatum' => Carbon::parse($periodStart)->format('d.m.Y'),
                    'enddatum' => Carbon::parse($periodEnd)->format('d.m.Y'),
                ], $this->areaMarkers($participant['area_dates']->keys()));

                $processor = new TemplateProcessor($template);
                foreach ($processor->getVariables() as $variable) {
                    $processor->setValue($variable, $values[$variable] ?? $values[mb_strtolower($variable)] ?? '');
                }

                $prefix = $type === 'certificate' ? 'Zertifikat' : 'Teilnahmebescheinigung';
                $filename = $this->safeName(implode('_', [
                    $prefix, $first['schule_name'], $first['klasse'], $first['nachname'], $first['vorname'],
                ])).'.docx';
                $path = $destination.DIRECTORY_SEPARATOR.Str::uuid().'_'.$filename;
                $processor->saveAs($path);

                return compact('path', 'filename', 'type', 'values');
            })
            ->values();
    }

    private function calibratedTemplate(string $source, string $destination, array $settings): string
    {
        $target = $destination.DIRECTORY_SEPARATOR.Str::uuid().'_'.basename($source);
        if (! File::copy($source, $target)) {
            throw new RuntimeException('Die POBO-Zertifikatsvorlage konnte nicht vorbereitet werden.');
        }

        $horizontal = (float) $settings['horizontal_offset_mm'];
        $vertical = (float) $settings['vertical_offset_mm'];
        $rowSpacing = (float) $settings['row_spacing_offset_mm'];
        $fontSize = (float) $settings['cross_font_size_pt'];
        if ($horizontal === 0.0 && $vertical === 0.0 && $rowSpacing === 0.0 && $fontSize === 13.0) {
            return $target;
        }

        $zip = new ZipArchive;
        if ($zip->open($target) !== true) {
            throw new RuntimeException('Die POBO-Zertifikatsvorlage konnte nicht kalibriert werden.');
        }

        try {
            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) {
                throw new RuntimeException('Die POBO-Zertifikatsvorlage enthält keinen Dokumenttext.');
            }
            $document = new \DOMDocument;
            $document->preserveWhiteSpace = true;
            if (! $document->loadXML($xml)) {
                throw new RuntimeException('Die POBO-Zertifikatsvorlage ist ungültig.');
            }
            $xpath = new \DOMXPath($document);
            $wordNamespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $xpath->registerNamespace('w', $wordNamespace);
            $markerNames = ['hauswirtschaft', 'metall', 'holz', 'IT', 'verkauf', 'kosmetik', 'elektro', 'farbe'];
            $paragraphs = [];
            foreach ($xpath->query('//w:body/w:p') as $paragraph) {
                $text = (string) $paragraph->textContent;
                if (collect($markerNames)->contains(fn (string $marker) => str_contains($text, '${'.$marker.'}'))) {
                    $paragraphs[] = $paragraph;
                }
            }
            if ($paragraphs === []) {
                throw new RuntimeException('Die POBO-Zertifikatsvorlage enthält keine Bereichspositionen.');
            }

            $twipsPerMillimetre = 1440 / 25.4;
            foreach ($paragraphs as $index => $paragraph) {
                $properties = $this->wordChild($document, $paragraph, 'pPr', true);
                $indent = $this->wordChild($document, $properties, 'ind', true);
                $currentLeft = (int) $indent->getAttributeNS($wordNamespace, 'left');
                $indent->setAttributeNS($wordNamespace, 'w:left', (string) max(0, round($currentLeft + $horizontal * $twipsPerMillimetre)));

                $spacing = $this->wordChild($document, $properties, 'spacing', true);
                if ($index < count($paragraphs) - 1 && $rowSpacing !== 0.0) {
                    $currentAfter = (int) $spacing->getAttributeNS($wordNamespace, 'after');
                    $spacing->setAttributeNS($wordNamespace, 'w:after', (string) max(0, round($currentAfter + $rowSpacing * $twipsPerMillimetre)));
                }

                foreach ($xpath->query('.//w:r[w:t[contains(text(), "${")]]', $paragraph) as $run) {
                    $runProperties = $this->wordChild($document, $run, 'rPr', true);
                    foreach (['sz', 'szCs'] as $sizeElement) {
                        $size = $this->wordChild($document, $runProperties, $sizeElement, true);
                        $size->setAttributeNS($wordNamespace, 'w:val', (string) round($fontSize * 2));
                    }
                }
            }

            if ($vertical !== 0.0) {
                $first = $paragraphs[0];
                $previous = $first->previousSibling;
                while ($previous && ! ($previous instanceof \DOMElement && $previous->localName === 'p')) {
                    $previous = $previous->previousSibling;
                }
                if ($previous instanceof \DOMElement) {
                    $properties = $this->wordChild($document, $previous, 'pPr', true);
                    $spacing = $this->wordChild($document, $properties, 'spacing', true);
                    $currentAfter = (int) $spacing->getAttributeNS($wordNamespace, 'after');
                    $spacing->setAttributeNS($wordNamespace, 'w:after', (string) max(0, round($currentAfter + $vertical * $twipsPerMillimetre)));
                }
            }

            $zip->addFromString('word/document.xml', $document->saveXML());
        } finally {
            $zip->close();
        }

        return $target;
    }

    private function wordChild(\DOMDocument $document, \DOMNode $parent, string $localName, bool $prepend = false): \DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        $child = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:'.$localName);
        if ($prepend && $parent->firstChild) {
            $parent->insertBefore($child, $parent->firstChild);
        } else {
            $parent->appendChild($child);
        }

        return $child;
    }

    private function schoolContext(int $schoolId, string $schoolYear, string $part, Projekt $project): array
    {
        $entries = $this->bopEntries->schoolEntries($schoolId, $schoolYear, $part, $project);
        if ($entries->isEmpty()) {
            return ['entries' => collect(), 'attendance' => collect()];
        }

        $attendance = GruppeHasPersonen::query()
            ->with(['status:id,status', 'tag:id,datum', 'gruppe:id,non_working_dates'])
            ->whereIn('gruppe_id', $entries->pluck('gruppe_id')->unique())
            ->whereIn('personen_id', $entries->pluck('personen_id')->unique())
            ->get()
            ->filter(function (GruppeHasPersonen $entry): bool {
                if (mb_strtolower(trim((string) $entry->status?->status)) !== 'anwesend' || ! $entry->tag?->datum) {
                    return false;
                }

                return $this->workdays->isAttendanceDay(
                    $entry->tag->datum,
                    [],
                    $entry->gruppe?->non_working_dates ?? []
                );
            });

        return compact('entries', 'attendance');
    }

    private function eligibleParticipants(Collection $entries, Collection $attendance): Collection
    {
        return $entries
            ->groupBy('personen_id')
            ->map(function (Collection $participantEntries, int|string $personId) use ($attendance) {
                $first = $participantEntries->first();
                $present = $attendance
                    ->where('personen_id', (int) $personId)
                    ->whereIn('gruppe_id', $participantEntries->pluck('gruppe_id'));
                $allDates = $present->pluck('tag.datum')->filter()->unique()->sort()->values();
                $areaDates = $participantEntries
                    ->groupBy('bereich_name')
                    ->map(fn (Collection $areaEntries) => $present
                        ->whereIn('gruppe_id', $areaEntries->pluck('gruppe_id'))
                        ->pluck('tag.datum')->filter()->unique()->sort()->values())
                    ->filter->isNotEmpty();

                $type = match (true) {
                    $allDates->count() >= 5 => 'certificate',
                    $allDates->count() >= 3 && ($areaDates->map->count()->max() ?? 0) >= 2 => 'participation',
                    default => null,
                };

                return $type ? [
                    'first' => $first,
                    'type' => $type,
                    'all_dates' => $allDates,
                    'area_dates' => $areaDates,
                ] : null;
            })
            ->filter()
            ->values();
    }

    private function commonPeriod(
        Collection $entries,
        Collection $attendance,
        int $schoolId,
        string $schoolYear,
        string $part,
        Projekt $project,
    ): array {
        $rollEntries = $entries->filter(
            fn (array $entry) => str_contains(mb_strtolower($entry['bereich_name']), 'rolltag')
        );
        $workshopEntries = $entries->reject(function (array $entry) {
            $area = mb_strtolower($entry['bereich_name']);

            return str_contains($area, 'rolltag')
                || str_contains($area, 'potenzialanalyse')
                || str_contains($area, 'feedback')
                || str_contains($area, 'vorbereitung');
        });
        $planned = $this->plannedPeriod($schoolId, $schoolYear, $part, $project);
        $attendanceDates = $attendance->pluck('tag.datum')->filter()->unique()->sort()->values();
        $rollStart = $rollEntries->pluck('anfangsdatum_iso')->filter()->min()
            ?: $rollEntries->pluck('enddatum_iso')->filter()->min();
        $workshopEnd = $workshopEntries->pluck('enddatum_iso')->filter()->max()
            ?: $workshopEntries->pluck('anfangsdatum_iso')->filter()->max();

        $from = $rollStart ?: $planned['from'] ?: $entries->pluck('anfangsdatum_iso')->filter()->min()
            ?: $attendanceDates->first();
        $to = $workshopEnd ?: $planned['to'] ?: $entries->pluck('enddatum_iso')->filter()->max()
            ?: $attendanceDates->last();
        $source = $rollStart ? 'rolltag_area' : ($planned['from'] || $planned['to'] ? 'bop_planning' : 'group_dates');

        return [
            'from' => $from,
            'to' => $to,
            'source' => $source,
            'source_label' => match ($source) {
                'rolltag_area' => 'Bereich Rolltag bis letzter Werkstatttag',
                'bop_planning' => 'Gespeicherte BOP-Planung',
                default => 'Termine der zugeordneten POBO-Gruppen',
            },
        ];
    }

    private function plannedPeriod(int $schoolId, string $schoolYear, string $part, Projekt $project): array
    {
        $normalisePart = fn ($value) => trim((string) preg_replace('/^Teil\s*/i', '', (string) $value));
        $normalisedPart = $normalisePart($part);
        $run = BopRun::query()
            ->where('projekt_id', $project->id)
            ->where('partner_id', $schoolId)
            ->forSchuljahr($schoolYear)
            ->whereIn('teil', array_values(array_unique([$part, $normalisedPart, 'Teil '.$normalisedPart, '_all'])))
            ->with('phases')
            ->orderByRaw('CASE WHEN teil = ? THEN 0 WHEN teil = ? THEN 1 WHEN teil = ? THEN 2 ELSE 3 END', [
                $part, $normalisedPart, 'Teil '.$normalisedPart,
            ])
            ->first();
        if (! $run) {
            return ['from' => null, 'to' => null];
        }

        $plannedClasses = collect($run->planned_classes ?? [])
            ->filter(fn ($class) => $normalisePart($class['part'] ?? '1') === $normalisedPart)
            ->pluck('name')->filter()->values();
        $datesFor = function ($phase) use ($normalisePart, $normalisedPart, $plannedClasses) {
            $dates = collect($phase->dates ?? []);
            $partAssignments = collect($phase->part_date_assignments ?? [])
                ->mapWithKeys(fn ($assignedDates, $assignedPart) => [$normalisePart($assignedPart) => $assignedDates]);
            if ($partAssignments->has($normalisedPart)) {
                $dates = collect($partAssignments->get($normalisedPart));
            } elseif (! empty($phase->class_date_assignments) && $plannedClasses->isNotEmpty()) {
                $classAssignments = collect($phase->class_date_assignments);
                $dates = $plannedClasses->flatMap(fn ($className) => $classAssignments->get($className, []));
            }

            return $dates->filter()->unique()->sort()->values();
        };

        return [
            'from' => $run->phases->where('phase_type', 'roll_day')->flatMap($datesFor)->min(),
            'to' => $run->phases->where('phase_type', 'workshop_days')->flatMap($datesFor)->max(),
        ];
    }

    private function areaMarkers(Collection $areas): array
    {
        $names = $areas
            ->map(fn ($area) => mb_strtolower(Str::ascii(trim((string) $area))))
            ->reject(fn (string $area) => str_contains($area, 'rolltag'))
            ->values();
        $contains = fn (array $needles): bool => $names->contains(
            fn (string $name) => collect($needles)->contains(
                fn (string $needle) => str_contains($name, $needle)
            )
        );
        $it = $names->contains(
            fn (string $name) => $name === 'it'
                || str_contains($name, 'medien')
                || str_contains($name, 'informationstechnik')
        );

        return [
            'hauswirtschaft' => $contains(['hauswirtschaft']) ? 'X' : '',
            'metall' => $contains(['metall', 'metaltechnik']) ? 'X' : '',
            'holz' => $contains(['holz']) ? 'X' : '',
            'IT' => $it ? 'X' : '',
            'it' => $it ? 'X' : '',
            'verkauf' => $contains(['verkauf']) ? 'X' : '',
            'kosmetik' => $contains(['kosmetik', 'friseur', 'korperpflege']) ? 'X' : '',
            'elektro' => $contains(['elektro']) ? 'X' : '',
            'farbe' => $contains(['farbe', 'raumgestaltung', 'maler', 'lackierer']) ? 'X' : '',
        ];
    }

    private function formatDate(?string $date): string
    {
        return $date ? Carbon::parse($date)->format('d.m.Y') : '';
    }

    private function safeName(string $value): string
    {
        return trim((string) preg_replace('/[^\pL\pN._-]+/u', '_', $value), '._-') ?: 'POBO_Zertifikat';
    }
}
