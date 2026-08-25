<?php

namespace App\Services\Bop;

use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\PotenzialanalyseBericht;
use App\Models\PotenzialanalyseBeurteilung;
use App\Models\PotenzialanalyseKompetenzbewertung;
use App\Models\PotenzialanalyseSelbsteinschaetzung;
use App\Models\PotenzialanalyseUebungErgebnis;
use App\Services\PotenzialanalyseProfileService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class PotenzialanalyseReportService
{
    private const MERKMALE = [
        ['key' => 'feinmotorik', 'bereich' => 'Berufsübergreifende Kompetenzen', 'label' => 'Feinmotorik'],
        ['key' => 'grobmotorik', 'bereich' => 'Berufsübergreifende Kompetenzen', 'label' => 'Grobmotorik'],
        ['key' => 'wahrnehmung_symmetrie', 'bereich' => 'Berufsübergreifende Kompetenzen', 'label' => 'Wahrnehmung und Symmetrie'],
        ['key' => 'analyse_problemloesefaehigkeit', 'bereich' => 'Methodenkompetenz', 'label' => 'Analyse- und Problemlösefähigkeit'],
        ['key' => 'arbeitsplanung', 'bereich' => 'Methodenkompetenz', 'label' => 'Arbeitsplanung'],
        ['key' => 'motivation_leistungsbereitschaft', 'bereich' => 'Personale Kompetenzen', 'label' => 'Motivation und Leistungsbereitschaft'],
        ['key' => 'durchhaltevermoegen', 'bereich' => 'Personale Kompetenzen', 'label' => 'Durchhaltevermögen'],
        ['key' => 'sorgfalt', 'bereich' => 'Personale Kompetenzen', 'label' => 'Sorgfalt und Genauigkeit'],
        ['key' => 'kommunikation', 'bereich' => 'Soziale Kompetenzen', 'label' => 'Kommunikationsfähigkeit'],
        ['key' => 'teamfaehigkeit', 'bereich' => 'Soziale Kompetenzen', 'label' => 'Teamfähigkeit'],
        ['key' => 'umgangsformen', 'bereich' => 'Soziale Kompetenzen', 'label' => 'Umgangsformen'],
    ];

    public function __construct(
        private readonly PotenzialanalyseProfileService $profiles,
    ) {
    }

    public function reportData(Gruppe $gruppe, Personen $person): array
    {
        $gruppe->loadMissing(['projekt', 'bereich', 'partner', 'teilnehmer.schueler']);
        $person->loadMissing('schueler.schule');

        $student = $this->studentContext($gruppe, $person);
        $ratings = PotenzialanalyseKompetenzbewertung::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $person->id)
            ->get()
            ->groupBy('typ');

        $selfRatings = ($ratings->get('selbst') ?? collect())->keyBy('merkmal');
        $coachRatings = ($ratings->get('anleiter') ?? collect())->keyBy('merkmal');

        $definitions = collect($this->profiles->competenciesForGroup($gruppe))
            ->map(fn (array $competency) => [
                'key' => $competency['key'],
                'bereich' => $competency['category'] ?? $competency['bereich'],
                'label' => $competency['label'],
                'rating_descriptions' => array_values($competency['rating_descriptions'] ?? []),
            ]);

        $merkmale = $definitions->map(function (array $merkmal) use ($selfRatings, $coachRatings) {
            $self = $selfRatings->get($merkmal['key']);
            $coach = $coachRatings->get($merkmal['key']);
            $coachRating = is_numeric($coach?->bewertung) ? (int) $coach->bewertung : null;
            $ratingDescription = $coachRating && $coachRating >= 1 && $coachRating <= 5
                ? ($merkmal['rating_descriptions'][$coachRating - 1] ?? null)
                : null;

            return $merkmal + [
                'selbst' => $self?->bewertung,
                'selbst_bemerkung' => $self?->bemerkung,
                'anleiter' => $coach?->bewertung,
                'anleiter_beurteilung' => filled($ratingDescription) ? trim($ratingDescription) : null,
                'anleiter_bemerkung' => $coach?->bemerkung,
            ];
        })->groupBy('bereich');

        $results = PotenzialanalyseUebungErgebnis::query()
            ->with('uebung')
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $person->id)
            ->whereHas('uebung', fn ($query) => $query->where('im_bericht_anzeigen', true))
            ->get()
            ->sortBy([
                fn ($a, $b) => ((int) ($a->uebung?->tag ?? 0)) <=> ((int) ($b->uebung?->tag ?? 0)),
                fn ($a, $b) => ((int) ($a->uebung?->sort_order ?? 0)) <=> ((int) ($b->uebung?->sort_order ?? 0)),
            ])
            ->values()
            ->map(fn ($result) => [
                'name' => $result->uebung?->name ?? 'Übung',
                'tag' => $result->uebung?->tag,
                'punkte' => $result->berechnete_punkte ?? $result->punkte,
                'fehler' => $result->fehler,
                'hoechstwert' => $result->uebung?->hoechstwert,
                'zeit' => ($result->uebung?->berechnungsregel === 'zeit' || $result->uebung?->zeit_erfassen)
                    ? $this->formatDuration((int) ($result->zeit ?? 0))
                    : '',
            ]);

        $assessment = PotenzialanalyseBeurteilung::query()
            ->with('kriterium.uebung')
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $person->id)
            ->get()
            ->keyBy('kriterium_id');

        $selfAssessment = PotenzialanalyseSelbsteinschaetzung::query()
            ->with('kriterium.uebung')
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $person->id)
            ->get()
            ->keyBy('kriterium_id');

        $kriterien = $assessment->keys()
            ->merge($selfAssessment->keys())
            ->unique()
            ->map(function ($kriteriumId) use ($assessment, $selfAssessment) {
                $entry = $assessment->get($kriteriumId);
                $self = $selfAssessment->get($kriteriumId);
                $kriterium = $entry?->kriterium ?: $self?->kriterium;

                return [
                    'uebung' => $kriterium?->uebung?->name ?? '',
                    'kriterium' => $kriterium?->name ?? '',
                    'selbst' => $self?->bewertung,
                    'anleiter' => $entry?->bewertung,
                    'bemerkung' => $entry?->bemerkung ?: $self?->bemerkung,
                ];
            })
            ->filter(fn (array $entry) => filled($entry['kriterium']))
            ->values();

        $bericht = PotenzialanalyseBericht::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $person->id)
            ->first();

        $school = $student?->schule ?: $gruppe->partner;
        $profil = $this->profiles->profileForGroup($gruppe);

        return [
            'person' => $person,
            'gruppe' => $gruppe,
            'student' => $student,
            'school' => $school,
            'merkmale' => $merkmale,
            'uebungen' => $results,
            'kriterien' => $kriterien,
            'bericht' => $bericht,
            'profil' => $profil,
            'berichtConfig' => $this->profiles->reportDisplayConfig($profil),
            'berichtLogoPath' => $this->profiles->reportLogoFile($profil),
            'statusLabel' => $this->statusLabel($bericht?->status),
            'zeitraum' => $this->dateRange($gruppe->anfangsdatum, $gruppe->enddatum),
            'erstelltAm' => $bericht?->fertiggestellt_at?->format('d.m.Y')
                ?: $bericht?->updated_at?->format('d.m.Y')
                ?: now()->format('d.m.Y'),
        ];
    }

    public function renderPdf(Gruppe $gruppe, Personen $person): string
    {
        if ($this->profiles->profileForGroup($gruppe)) {
            return Pdf::loadView('pdf.bericht-pa-profil', $this->reportData($gruppe, $person))
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setPaper('a4', 'portrait')
                ->output();
        }

        $data = $this->originalBopReportData($gruppe, $person);

        return Pdf::loadView('pdf.berichtPA', $data)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setPaper('a4', 'portrait')
            ->output();
    }

    private function originalBopReportData(Gruppe $gruppe, Personen $person): array
    {
        $gruppe->loadMissing(['projekt', 'bereich', 'partner']);
        $person->loadMissing('schueler.schule');

        $student = $this->studentContext($gruppe, $person);
        $school = $student?->schule ?: $gruppe->partner;
        $ratings = PotenzialanalyseKompetenzbewertung::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $person->id)
            ->get()
            ->groupBy('typ');

        $coach = ($ratings->get('anleiter') ?? collect())->keyBy('merkmal');
        $self = ($ratings->get('selbst') ?? collect())->keyBy('merkmal');
        $fields = collect(self::MERKMALE)->pluck('key');

        $coachValues = $fields
            ->mapWithKeys(fn (string $field) => [$field => $coach->get($field)?->bewertung])
            ->all();
        $selfValues = $fields
            ->mapWithKeys(fn (string $field) => [$field => $self->get($field)?->bewertung])
            ->all();

        $exercises = PotenzialanalyseUebungErgebnis::query()
            ->with('uebung')
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $person->id)
            ->get()
            ->sortBy([
                fn ($a, $b) => ((int) ($a->uebung?->tag ?? 0)) <=> ((int) ($b->uebung?->tag ?? 0)),
                fn ($a, $b) => ((int) ($a->uebung?->sort_order ?? 0)) <=> ((int) ($b->uebung?->sort_order ?? 0)),
            ])
            ->map(function ($result) {
                return (object) [
                    'name' => $result->uebung?->name ?? '',
                    'hoechstwert' => $result->uebung?->hoechstwert,
                    'auswertbar' => $result->uebung?->auswertbar ? '1' : '0',
                    'pivot' => (object) [
                        'punkte' => $result->punkte,
                        'zeit' => (string) ($result->zeit ?? ''),
                    ],
                ];
            })
            ->values();

        $report = PotenzialanalyseBericht::query()
            ->where('gruppe_id', $gruppe->id)
            ->where('personen_id', $person->id)
            ->first();

        $participant = (object) [
            'id' => $person->id,
            'vorname' => $person->vorname ?? '',
            'nachname' => $person->nachname ?? '',
            'klasse' => $student?->klasse ?? '',
            'schule' => (object) ['schule' => $school?->name ?? ''],
            'auswertungPa' => (object) $coachValues,
            'selbsteinschaetzung' => (object) $selfValues,
            'zusammenfassung' => (string) ($report?->bericht_text ?? ''),
            'uebungen' => $exercises,
        ];

        return [
            'beurteilungen' => config('beurteilungen'),
            'teilnehmer' => $participant,
        ];
    }

    public function writePdf(Gruppe $gruppe, Personen $person, string $directory): string
    {
        File::ensureDirectoryExists($directory);
        $path = $directory . DIRECTORY_SEPARATOR . $this->fileName($person, 'pdf', $gruppe);
        File::put($path, $this->renderPdf($gruppe, $person));

        return $path;
    }

    public function createGroupPdf(Gruppe $gruppe): array
    {
        $gruppe->loadMissing('bereich');
        $people = $this->orderedParticipants($gruppe);

        abort_if($people->isEmpty(), 422, 'Die Gruppe verfügt über keine Teilnehmer.');

        $name = $this->safeName('PA_Berichte_Gruppe_' . ($gruppe->bereich?->name ?: $gruppe->id)) . '.pdf';
        $token = (string) Str::uuid();
        $temporaryDirectory = storage_path('app/tmp/pa-group-' . $token);
        $path = storage_path('app/tmp/' . $token . '_' . $name);

        File::ensureDirectoryExists(dirname($path));
        File::ensureDirectoryExists($temporaryDirectory);

        try {
            $partPaths = [];

            foreach ($people as $index => $person) {
                $partPath = $temporaryDirectory . DIRECTORY_SEPARATOR . sprintf('%04d.pdf', $index + 1);
                $contents = $this->renderPdf($gruppe, $person);
                File::put($partPath, $contents);
                $partPaths[] = $partPath;

                unset($contents);
                gc_collect_cycles();
                if (function_exists('gc_mem_caches')) {
                    gc_mem_caches();
                }
            }

            $this->mergePdfFiles($partPaths, $path);
        } catch (\Throwable $exception) {
            File::delete($path);

            throw $exception;
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }

        return ['path' => $path, 'name' => $name, 'count' => $people->count()];
    }

    /**
     * @param  iterable<string>  $paths
     */
    private function mergePdfFiles(iterable $paths, string $outputPath): void
    {
        $merged = new Fpdi();

        foreach ($paths as $path) {
            $pageCount = $merged->setSourceFile($path);

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $template = $merged->importPage($pageNumber);
                $size = $merged->getTemplateSize($template);
                $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

                $merged->AddPage($orientation, [$size['width'], $size['height']]);
                $merged->useImportedPage($template, 0, 0, $size['width'], $size['height']);
            }
        }

        $merged->Output('F', $outputPath);
    }

    public function orderedParticipants(Gruppe $gruppe): Collection
    {
        $gruppe->loadMissing('teilnehmer');

        return $gruppe->teilnehmer
            ->unique('id')
            ->sort(function (Personen $left, Personen $right): int {
                $lastName = strnatcasecmp(
                    Str::ascii((string) $left->nachname),
                    Str::ascii((string) $right->nachname),
                );

                if ($lastName !== 0) {
                    return $lastName;
                }

                $firstName = strnatcasecmp(
                    Str::ascii((string) $left->vorname),
                    Str::ascii((string) $right->vorname),
                );

                return $firstName !== 0 ? $firstName : ((int) $left->id <=> (int) $right->id);
            })
            ->values();
    }

    public function schoolAssignments(int $schoolId, string $schoolYear, string $part, int $projectId): Collection
    {
        $students = PersonenIstSchueler::query()
            ->with(['person', 'schule'])
            ->where('schule_id', $schoolId)
            ->forSchuljahr($schoolYear)
            ->where('teil', $part)
            ->get()
            ->keyBy('person_id');

        if ($students->isEmpty()) {
            return collect();
        }

        $groupIds = GruppeHasPersonen::query()
            ->whereIn('personen_id', $students->keys())
            ->whereHas('gruppe', fn ($query) => $query->where('projekt_id', $projectId))
            ->pluck('gruppe_id')
            ->unique()
            ->values();

        if ($groupIds->isEmpty()) {
            return collect();
        }

        $pairs = collect();
        foreach ([
            PotenzialanalyseBericht::class,
            PotenzialanalyseKompetenzbewertung::class,
            PotenzialanalyseUebungErgebnis::class,
            PotenzialanalyseBeurteilung::class,
            PotenzialanalyseSelbsteinschaetzung::class,
        ] as $model) {
            $pairs = $pairs->concat(
                $model::query()
                    ->whereIn('gruppe_id', $groupIds)
                    ->whereIn('personen_id', $students->keys())
                    ->orderByDesc('updated_at')
                    ->get(['gruppe_id', 'personen_id', 'updated_at'])
            );
        }

        $latestByPerson = $pairs
            ->unique('personen_id')
            ->keyBy('personen_id');

        $groups = Gruppe::query()
            ->with(['projekt', 'bereich', 'partner'])
            ->whereIn('id', $latestByPerson->pluck('gruppe_id')->unique())
            ->get()
            ->keyBy('id');

        return $latestByPerson
            ->map(function ($pair) use ($students, $groups) {
                $student = $students->get($pair->personen_id);
                $group = $groups->get($pair->gruppe_id);

                if (! $student?->person || ! $group) {
                    return null;
                }

                return ['gruppe' => $group, 'person' => $student->person, 'student' => $student];
            })
            ->filter()
            ->sortBy(fn (array $item) => mb_strtolower(($item['person']->nachname ?? '') . ' ' . ($item['person']->vorname ?? '')))
            ->values();
    }

    public function fileName(Personen $person, string $extension = 'pdf', ?Gruppe $gruppe = null): string
    {
        if ($gruppe) {
            $person->loadMissing('schueler.schule');
        }

        $student = $gruppe ? $this->studentContext($gruppe, $person) : null;
        $name = trim(($person->nachname ?? '') . ' ' . ($person->vorname ?? ''));
        $class = trim((string) ($student?->klasse ?? ''));

        return trim('Bericht PA - ' . ($name ?: 'Teilnehmer') . ' ' . $class) . '.' . $extension;
    }

    private function studentContext(Gruppe $gruppe, Personen $person): ?PersonenIstSchueler
    {
        $context = $this->groupContext($gruppe);
        $students = $person->schueler;

        return $students->first(function (PersonenIstSchueler $student) use ($context) {
            if (! empty($context['partner_id']) && (int) $student->schule_id !== (int) $context['partner_id']) {
                return false;
            }
            if (! empty($context['schuljahr']) && (string) $student->schuljahr !== (string) $context['schuljahr']) {
                return false;
            }
            if (! empty($context['teil']) && (string) $student->teil !== (string) $context['teil']) {
                return false;
            }

            return true;
        }) ?: $students->sortByDesc('id')->first();
    }

    private function groupContext(Gruppe $gruppe): array
    {
        $context = ['partner_id' => $gruppe->partner_id];

        if (preg_match('/BOP Einteilung Schule\s+(\d+)\s+Schuljahr\s+(.+?)\s+Teil\s+(.+?)\s+Runde\s+(\d+)/u', (string) $gruppe->bemerkung, $matches)) {
            $context = [
                'partner_id' => (int) $matches[1],
                'schuljahr' => trim($matches[2]),
                'teil' => trim($matches[3]),
            ];
        }

        return array_filter($context, fn ($value) => filled($value));
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'in_bearbeitung' => 'In Bearbeitung',
            'fertig' => 'Fertig',
            'geprueft' => 'Geprüft',
            default => 'Entwurf',
        };
    }

    private function dateRange($start, $end): string
    {
        if (! $start && ! $end) {
            return '';
        }

        $startValue = $start ? Carbon::parse($start)->format('d.m.Y') : '';
        $endValue = $end ? Carbon::parse($end)->format('d.m.Y') : '';

        return $startValue === $endValue || $endValue === '' ? $startValue : $startValue . ' - ' . $endValue;
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '';
        }

        return intdiv($seconds, 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT) . ' min';
    }

    private function safeName(string $value): string
    {
        $value = Str::ascii($value);
        $value = preg_replace('/[^A-Za-z0-9_.-]+/', '_', trim($value));

        return trim((string) $value, '_') ?: 'PA_Bericht';
    }
}
