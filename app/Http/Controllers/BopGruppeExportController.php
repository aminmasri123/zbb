<?php

namespace App\Http\Controllers;

use App\Models\Gruppe;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\BerufsorientierungBewertung;
use App\Services\BerufsorientierungAuswertungService;
use App\Services\Bop\AttendanceFooterService;
use App\Services\Projects\ActiveProjectContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;
use setasign\Fpdi\Fpdi;
use ZipArchive;

class BopGruppeExportController extends Controller
{
    public function __construct(
        private readonly AttendanceFooterService $attendanceFooter,
        private readonly ActiveProjectContext $activeProjectContext,
        private readonly BerufsorientierungAuswertungService $boAuswertung,
    ) {}

    public function namensschilder(Gruppe $gruppe)
    {
        $gruppe = $this->gruppeMitDaten($gruppe);
        $teilnehmer = $this->teilnehmerDaten($gruppe);

        if ($teilnehmer->isEmpty()) {
            return back()->with('error', 'Die Gruppe verfuegt derzeit ueber keine Teilnehmer.');
        }

        $filename = $this->safeFileName('Namensschilder_Gruppe_'.($gruppe->bereich?->name ?? $gruppe->id)).'.docx';

        return $this->namensschilderDownload($teilnehmer->pluck('voller_name'), $filename);
    }

    public function partnerNamensschilder(Request $request, Partner $partner)
    {
        $validated = $request->validate([
            'schuljahr' => ['required', 'string'],
            'teil' => ['required', 'string'],
        ]);
        $project = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($project && str_contains(mb_strtoupper((string) $project->name), 'BOP'), 404, 'Diese Funktion ist nur im Projekt BOP verfügbar.');
        abort_unless(
            DB::table('projekt_has_partners')
                ->where('projekt_id', $project->id)
                ->where('partner_id', $partner->id)
                ->exists(),
            404
        );

        $names = PersonenIstSchueler::query()
            ->filterSchueler($partner->id, $validated['schuljahr'], $validated['teil'])
            ->with('person')
            ->get()
            ->unique('person_id')
            ->sort(function ($a, $b) {
                $lastName = strnatcasecmp((string) ($a->person?->nachname ?? ''), (string) ($b->person?->nachname ?? ''));

                return $lastName !== 0
                    ? $lastName
                    : strnatcasecmp((string) ($a->person?->vorname ?? ''), (string) ($b->person?->vorname ?? ''));
            })
            ->map(fn (PersonenIstSchueler $student) => trim(
                (string) ($student->person?->vorname ?? '').' '.(string) ($student->person?->nachname ?? '')
            ))
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return back()->with('error', 'Die Schule verfügt für diese Auswahl derzeit über keine Teilnehmer.');
        }

        $filename = $this->safeFileName(
            'Namensschilder_'.$partner->name.'_'.$validated['schuljahr'].'_Teil_'.$validated['teil']
        ).'.docx';

        return $this->namensschilderDownload($names, $filename);
    }

    public function hausordnung(Gruppe $gruppe)
    {
        return $this->combinedTemplateExport(
            $gruppe,
            $this->wordTemplate('Hausordnung_BOP.docx'),
            'Hausordnung_Gruppe_'.$gruppe->id,
            fn (TemplateProcessor $processor, array $item) => $this->fillTemplate($processor, $item)
        );
    }

    public function berufsfelderprobung(Gruppe $gruppe)
    {
        return $this->combinedTemplateExport(
            $gruppe,
            $this->wordTemplate('Berufsfelderprobung_Schueler_BOP.docx'),
            'Berufsfelderprobung_Gruppe_'.$gruppe->id,
            fn (TemplateProcessor $processor, array $item) => $this->fillTemplate($processor, $item)
        );
    }

    public function auswertungsbogenBop(Gruppe $gruppe)
    {
        $gruppe = $this->gruppeMitDaten($gruppe);
        $teilnehmer = $this->teilnehmerDaten($gruppe);
        abort_if($teilnehmer->isEmpty(), 422, 'Die Gruppe verfügt derzeit über keine Teilnehmer.');
        abort_unless($gruppe->bereich_id, 422, 'Der Gruppe ist kein Bereich zugeordnet.');
        $config = $this->boAuswertung->config($gruppe->projekt);
        abort_unless($config['enabled'] && count($config['criteria']), 422, 'Für dieses Projekt ist keine Bereichsauswertung konfiguriert.');

        $ratings = BerufsorientierungBewertung::query()
            ->where('gruppe_id', $gruppe->id)
            ->whereIn('personen_id', $teilnehmer->pluck('personen_id'))
            ->get()->groupBy('personen_id')->map->keyBy('kriterium');
        $pdf = Pdf::loadView('pdf.bereichsauswertung', compact('gruppe', 'teilnehmer', 'config', 'ratings'))
            ->setPaper('a4', 'portrait');
        $filename = $this->safeFileName('Auswertungsbogen_'.($gruppe->bereich?->name ?: 'Gruppe_'.$gruppe->id)).'.pdf';

        return $pdf->download($filename);
    }

    public function tagesauswertungBop(Request $request, Gruppe $gruppe)
    {
        $validated = $request->validate([
            'bo_tag' => ['required', 'in:1,2,3,alle'],
        ]);
        $gruppe = $this->gruppeMitDaten($gruppe);
        abort_unless(str_contains(mb_strtolower((string) $gruppe->projekt?->name), 'bop'), 404, 'Diese Funktion ist nur im Projekt BOP verfügbar.');
        $teilnehmer = $this->teilnehmerDaten($gruppe);
        abort_if($teilnehmer->isEmpty(), 422, 'Die Gruppe verfügt derzeit über keine Teilnehmer.');

        $template = $this->tagesauswertungTemplate((string) $gruppe->bereich?->name);
        abort_unless($template && file_exists($template), 422, 'Für diesen BOP-Bereich ist keine Tagesauswertung hinterlegt.');
        $tage = $validated['bo_tag'] === 'alle' ? [1, 2, 3] : [(int) $validated['bo_tag']];
        $termine = $this->tagesauswertungTermine($gruppe);
        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(false);

        foreach ($teilnehmer as $item) {
            $lastPageSize = null;

            foreach ($tage as $tag) {
                $pdf->setSourceFile($template);
                $page = $pdf->importPage($tag);
                $size = $pdf->getTemplateSize($page);
                $lastPageSize = $size;
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($page);
                $this->writeTagesauswertungPartnerLogos($pdf);
                $this->writeTagesauswertungHeader($pdf, $item, $termine[$tag] ?? '', str_contains(mb_strtolower((string) $gruppe->bereich?->name), 'holz'));
            }

            // Jeder Teilnehmer beginnt beim Duplexdruck auf einer neuen Vorderseite.
            if (count($tage) % 2 === 1 && $lastPageSize !== null) {
                $pdf->AddPage(
                    $lastPageSize['orientation'],
                    [$lastPageSize['width'], $lastPageSize['height']]
                );
            }
        }

        $tagLabel = $validated['bo_tag'] === 'alle' ? 'Alle_BO_Tage' : 'BO_Tag_'.$validated['bo_tag'];
        $filename = $this->safeFileName('Tagesauswertung_'.($gruppe->bereich?->name ?: 'Gruppe_'.$gruppe->id).'_'.$tagLabel).'.pdf';
        $path = $this->tmpPath($filename);
        $pdf->Output('F', $path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function anwesenheitsliste(Gruppe $gruppe)
    {
        $gruppe = $this->gruppeMitDaten($gruppe);
        $teilnehmer = $this->teilnehmerDaten($gruppe);

        if ($teilnehmer->isEmpty()) {
            return back()->with('error', 'Die Gruppe verfuegt derzeit ueber keine Teilnehmer.');
        }

        $templateFile = storage_path('vorlage/projekte/bop/excel/Anwesenheitsliste.xlsx');
        if (! file_exists($templateFile)) {
            return back()->with('error', 'Die Anwesenheitsliste-Vorlage wurde nicht gefunden.');
        }

        $spreadsheet = SpreadsheetIOFactory::load($templateFile);
        $this->attendanceFooter->applyToSpreadsheet($spreadsheet);
        $sheet = $spreadsheet->getActiveSheet();

        $start = Carbon::parse($gruppe->anfangsdatum);
        $end = Carbon::parse($gruppe->enddatum);
        $attendanceDays = $this->attendanceDays($start, $end);
        $first = $teilnehmer->first();
        $school = $first['schule'] ?: '';
        $bereich = $gruppe->bereich?->name ?? '';

        $sheet->setCellValue('B3', $start->format('Y'));
        $sheet->setCellValue('B6', $start->weekOfYear.'KW');
        $sheet->setCellValue('C2', 'Praxisbereich: '.$bereich.'          '.$school);
        $this->writeAttendanceDayHeaders($sheet, $attendanceDays);

        $row = 8;
        foreach ($teilnehmer as $item) {
            $sheet->setCellValue('B'.$row, $item['name']);
            $sheet->setCellValueExplicit('D'.$row, $item['klasse'], DataType::TYPE_STRING);
            $sheet->setCellValue('E'.$row, $item['geschlecht']);
            $row++;
        }

        $sheet->setCellValue('D25', $first['anleiter_name'] ?? '');

        $filename = $this->safeFileName('Anwesenheitsliste_'.($bereich ?: 'Gruppe_'.$gruppe->id).'_'.$start->format('d.m.Y').'_'.$end->format('d.m.Y')).'.xlsx';
        $path = $this->tmpPath($filename);
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function unterweisungsnachweis(Request $request, Gruppe $gruppe)
    {
        $gruppe = $this->gruppeMitDaten($gruppe);
        $user = $request->user();

        $istZugeordneterAnleiter = (int) $gruppe->personen_id === (int) $user?->person_id;
        $darfVertreten = $user?->can('gruppe.view.all') || $user?->can('projekt.mitarbeiter.view.all');
        abort_unless($istZugeordneterAnleiter || $darfVertreten, 403, 'Sie dürfen diese Gruppe nicht vertreten.');

        $unterschrift = $user->unterweisung_unterschrift;

        $teilnehmer = $this->teilnehmerDaten($gruppe);
        if ($teilnehmer->isEmpty()) {
            return back()->with('error', 'Die Gruppe verfügt derzeit über keine Teilnehmer.');
        }

        $themen = collect(config('unterweisung.themen', []));
        $ausgewaehlteThemen = collect($gruppe->bereich?->unterweisung_themen ?? [])
            ->filter(fn (string $key) => $themen->has($key))
            ->values();
        $ort = $gruppe->ort_typ === 'extern'
            ? (string) $gruppe->externer_ort
            : (string) ($gruppe->raum?->name ?? $gruppe->standort?->name ?? '');
        $datum = $this->formatDate($gruppe->anfangsdatum ?: now());
        $anleiter = trim(($user->person?->vorname ?? '').' '.($user->person?->nachname ?? '')) ?: $user->name;

        $pdf = Pdf::loadView('pdf.unterweisungsnachweis', [
            'gruppe' => $gruppe,
            'teilnehmer' => $teilnehmer,
            'themen' => $themen,
            'ausgewaehlteThemen' => $ausgewaehlteThemen,
            'ort' => $ort,
            'datum' => $datum,
            'anleiter' => $anleiter,
            'unterschriftDataUrl' => ! empty($unterschrift['data']) && ! empty($unterschrift['mime'])
                ? 'data:'.$unterschrift['mime'].';base64,'.$unterschrift['data']
                : null,
        ])->setPaper('a4', 'portrait');

        $output = $pdf->output();
        $filename = $this->safeFileName('Unterweisungsnachweis_'.($gruppe->bereich?->name ?: 'Gruppe_'.$gruppe->id).'_'.$datum).'.pdf';

        Log::info('Unterweisungsnachweis als PDF exportiert', [
            'user_id' => $user->id,
            'personen_id' => $user->person_id,
            'gruppe_id' => $gruppe->id,
            'bereich_id' => $gruppe->bereich_id,
            'themen' => $ausgewaehlteThemen->all(),
            'teilnehmer_ids' => $gruppe->teilnehmer->pluck('id')->all(),
            'sha256' => hash('sha256', $output),
            'vertretung' => ! $istZugeordneterAnleiter,
        ]);

        return response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function toilettennutzungsliste(Gruppe $gruppe)
    {
        $gruppe = $this->gruppeMitDaten($gruppe);
        $teilnehmer = $this->teilnehmerDaten($gruppe);

        if ($teilnehmer->isEmpty()) {
            return back()->with('error', 'Die Gruppe verfuegt derzeit ueber keine Teilnehmer.');
        }

        $templateFile = $this->wordTemplate('Toilettennutzungsliste.docx');
        if (! file_exists($templateFile)) {
            return back()->with('error', 'Die Toilettennutzungsliste-Vorlage wurde nicht gefunden.');
        }

        $processor = new TemplateProcessor($templateFile);
        $this->fillTemplate($processor, $teilnehmer->first());

        $filename = $this->safeFileName('Toilettennutzungsliste_'.($teilnehmer->first()['schule'] ?: 'Gruppe_'.$gruppe->id)).'.docx';
        $path = $this->tmpPath($filename);
        $processor->saveAs($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function zertifikatPobo(Gruppe $gruppe)
    {
        return $this->combinedTemplateExport(
            $gruppe,
            null,
            'Zertifikate_POBO_Gruppe_'.$gruppe->id,
            function (TemplateProcessor $processor, array $item) {
                $this->fillTemplate($processor, $item);
            },
            fn (array $item) => $this->wordTemplate(strlen($item['voller_name']) <= 19 ? 'Zertifikat_Maske_POBO.docx' : 'Zertifikat_Maske_POBO_klein_text.docx')
        );
    }

    public function teilnahmePobo(Gruppe $gruppe)
    {
        return $this->combinedTemplateExport(
            $gruppe,
            null,
            'Teilnahmebescheinigung_POBO_Gruppe_'.$gruppe->id,
            function (TemplateProcessor $processor, array $item) {
                $this->fillTemplate($processor, $item);
            },
            fn (array $item) => $this->wordTemplate(strlen($item['voller_name']) <= 19 ? 'Teilnahmebescheinigung_Maske_POBO.docx' : 'Teilnahmebescheinigung_Maske_POBO_klein_text.docx')
        );
    }

    public function zertifikatPa(Gruppe $gruppe)
    {
        return $this->combinedTemplateExport(
            $gruppe,
            null,
            'Zertifikate_PA_Gruppe_'.$gruppe->id,
            fn (TemplateProcessor $processor, array $item) => $this->fillTemplate($processor, $item),
            fn (array $item) => $this->wordTemplate(strlen($item['voller_name']) <= 19 ? 'Zertifikat_Maske_PA.docx' : 'Zertifikat_Maske_PA_klein_text.docx')
        );
    }

    public function teilnahmePa(Gruppe $gruppe)
    {
        return $this->combinedTemplateExport(
            $gruppe,
            $this->wordTemplate('Teilnahmebescheinigung_PA.docx'),
            'Teilnahmebescheinigung_PA_Gruppe_'.$gruppe->id,
            fn (TemplateProcessor $processor, array $item) => $this->fillTemplate($processor, $item)
        );
    }

    public function auswertungsbogenPa(Gruppe $gruppe)
    {
        return $this->combinedTemplateExport(
            $gruppe,
            $this->wordTemplate('Auswertung_PA.docx'),
            'Auswertungsbogen_PA_Gruppe_'.$gruppe->id,
            fn (TemplateProcessor $processor, array $item) => $this->fillTemplate($processor, $item)
        );
    }

    private function combinedTemplateExport(
        Gruppe $gruppe,
        ?string $templateFile,
        string $baseName,
        callable $fill,
        ?callable $templateFor = null
    ) {
        $gruppe = $this->gruppeMitDaten($gruppe);
        $teilnehmer = $this->teilnehmerDaten($gruppe);

        if ($teilnehmer->isEmpty()) {
            return back()->with('error', 'Die Gruppe verfuegt derzeit ueber keine Teilnehmer.');
        }

        if ($templateFile && ! file_exists($templateFile)) {
            return back()->with('error', 'Die BOP-Vorlage wurde nicht gefunden.');
        }

        $tmpDir = storage_path('app/tmp/bop_'.uniqid('', true));
        File::ensureDirectoryExists($tmpDir);

        $documentPaths = [];

        foreach ($teilnehmer as $item) {
            $currentTemplate = $templateFor ? $templateFor($item) : $templateFile;
            if (! $currentTemplate || ! file_exists($currentTemplate)) {
                File::deleteDirectory($tmpDir);

                return back()->with('error', 'Eine BOP-Vorlage wurde nicht gefunden.');
            }

            $processor = new TemplateProcessor($currentTemplate);
            $fill($processor, $item);

            $fileName = count($documentPaths).'.docx';
            $filePath = $tmpDir.DIRECTORY_SEPARATOR.$fileName;
            $processor->saveAs($filePath);
            $documentPaths[] = $filePath;
        }

        $filename = $this->safeFileName($baseName).'.docx';
        $outputPath = $this->tmpPath($filename);
        $this->mergeDocxFiles($documentPaths, $outputPath);
        File::deleteDirectory($tmpDir);

        return response()->download($outputPath, $filename)->deleteFileAfterSend(true);
    }

    private function namensschilderDownload(Collection $names, string $filename)
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection([
            'pageSizeW' => 11906,
            'pageSizeH' => 16838,
        ]);

        foreach ($names->values() as $index => $name) {
            if ($index > 0) {
                $section->addPageBreak();
            }

            $section->addTextBreak(8);
            $section->addText('-----------------------', ['size' => 40], ['alignment' => 'center']);
            $section->addText((string) $name, ['size' => 48], ['alignment' => 'center']);
            $section->addText('-----------------------', ['size' => 40], ['alignment' => 'center']);
        }

        $path = $this->tmpPath($filename);
        $phpWord->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function mergeDocxFiles(array $documentPaths, string $outputPath): void
    {
        if (count($documentPaths) === 1) {
            File::copy($documentPaths[0], $outputPath);

            return;
        }

        File::copy($documentPaths[0], $outputPath);

        $baseZip = new ZipArchive;
        if ($baseZip->open($outputPath) !== true) {
            throw new \RuntimeException('Sammel-DOCX konnte nicht geoeffnet werden.');
        }

        $baseXml = $baseZip->getFromName('word/document.xml');
        $baseParts = $this->documentBodyParts($baseXml);
        $combinedBody = $baseParts['content'];
        $pageBreak = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';

        foreach (array_slice($documentPaths, 1) as $documentPath) {
            $zip = new ZipArchive;
            if ($zip->open($documentPath) !== true) {
                continue;
            }

            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            $parts = $this->documentBodyParts($xml);
            $combinedBody .= $pageBreak.$parts['content'];
        }

        $newXml = preg_replace_callback('/(<w:body[^>]*>).*?(<\/w:body>)/s', function ($matches) use ($combinedBody, $baseParts) {
            return $matches[1].$combinedBody.$baseParts['sectPr'].$matches[2];
        }, $baseXml);

        $baseZip->deleteName('word/document.xml');
        $baseZip->addFromString('word/document.xml', $newXml);
        $baseZip->close();
    }

    private function documentBodyParts(string $xml): array
    {
        preg_match('/<w:body[^>]*>(.*)<\/w:body>/s', $xml, $matches);
        $body = $matches[1] ?? '';
        $sectPr = '';

        if (preg_match('/(<w:sectPr\b[^>]*(?:\/>|>.*<\/w:sectPr>))\s*$/s', $body, $sectionMatches)) {
            $sectPr = $sectionMatches[1];
            $body = substr($body, 0, -strlen($sectionMatches[0]));
        }

        return [
            'content' => $body,
            'sectPr' => $sectPr,
        ];
    }

    private function gruppeMitDaten(Gruppe $gruppe): Gruppe
    {
        abort_unless($this->canUseGroup(auth()->user(), $gruppe), 403);

        return $gruppe->loadMissing([
            'teilnehmer.schueler',
            'bereich',
            'betreuer',
            'projekt',
            'raum',
            'standort',
        ]);
    }

    private function teilnehmerDaten(Gruppe $gruppe): Collection
    {
        $context = $this->bopContext($gruppe);
        $partner = ! empty($context['partner_id']) ? Partner::find($context['partner_id']) : null;

        return $gruppe->teilnehmer
            ->unique('id')
            ->sortBy(fn (Personen $person) => strtolower(($person->nachname ?? '').' '.($person->vorname ?? '')))
            ->values()
            ->map(function (Personen $person, int $index) use ($gruppe, $context, $partner) {
                $schueler = $this->schuelerFuerPerson($person, $context);
                $itemPartner = $partner ?: ($schueler?->schule_id ? Partner::find($schueler->schule_id) : null);
                $bereich = $gruppe->bereich?->name ?? '';
                $schule = $itemPartner?->name ?? '';
                $anleiter = trim(($gruppe->betreuer?->vorname ?? '').' '.($gruppe->betreuer?->nachname ?? ''));

                $values = [
                    'personen_id' => $person->id,
                    'nr' => $index + 1,
                    'nummer' => $index + 1,
                    'vorname' => $person->vorname ?? '',
                    'nachname' => $person->nachname ?? '',
                    'voller_name' => trim(($person->vorname ?? '').' '.($person->nachname ?? '')),
                    'name' => trim(($person->nachname ?? '').', '.($person->vorname ?? '')),
                    'geburtsdatum' => $this->formatDate($person->geburtsdatum),
                    'geschlecht' => $person->geschlecht ?? '',
                    'klasse' => $schueler?->klasse ?? '',
                    'schule' => $schule,
                    'schule_name' => $schule,
                    'schulform' => $this->schulform($schueler),
                    'schuljahr' => $schueler?->schuljahr ?? ($context['schuljahr'] ?? ''),
                    'teil' => $schueler?->teil ?? ($context['teil'] ?? ''),
                    'bereich' => $bereich,
                    'bereich_name' => $bereich,
                    'gruppe' => $bereich ?: ('Gruppe '.$gruppe->id),
                    'anleiter' => $anleiter,
                    'anleiter_name' => $anleiter,
                    'betreuer' => $anleiter,
                    'datum' => $this->formatDate($gruppe->enddatum ?: $gruppe->anfangsdatum),
                    'tag1' => $this->formatDate($gruppe->anfangsdatum),
                    'tag2' => $this->formatDate($gruppe->enddatum),
                    'anfangsdatum' => $this->formatDate($gruppe->anfangsdatum),
                    'enddatum' => $this->formatDate($gruppe->enddatum),
                    'anfang' => $this->formatDate($gruppe->anfangsdatum),
                    'ende' => $this->formatDate($gruppe->enddatum),
                ];

                return array_merge($values, $this->bereichMarkierungen($bereich));
            });
    }

    private function fillTemplate(TemplateProcessor $processor, array $values): void
    {
        foreach ($processor->getVariables() as $variable) {
            $processor->setValue($variable, $values[$variable] ?? $values[strtolower($variable)] ?? '');
        }
    }

    private function schuelerFuerPerson(Personen $person, array $context)
    {
        $schueler = $person->schueler ?? collect();

        return $schueler->first(function ($item) use ($context) {
            if (! empty($context['partner_id']) && (int) $item->schule_id !== (int) $context['partner_id']) {
                return false;
            }

            if (! empty($context['schuljahr']) && (string) $item->schuljahr !== (string) $context['schuljahr']) {
                return false;
            }

            if (! empty($context['teil']) && (string) $item->teil !== (string) $context['teil']) {
                return false;
            }

            return true;
        }) ?: $schueler->last();
    }

    private function bopContext(Gruppe $gruppe): array
    {
        $context = [];
        $bemerkung = (string) $gruppe->bemerkung;

        if (preg_match('/BOP Einteilung Schule\s+(\d+)\s+Schuljahr\s+(.+?)\s+Teil\s+(.+?)\s+Runde\s+(\d+)/u', $bemerkung, $matches)) {
            $context = [
                'partner_id' => (int) $matches[1],
                'schuljahr' => trim($matches[2]),
                'teil' => trim($matches[3]),
                'runde' => (int) $matches[4],
            ];
        }

        $schueler = $gruppe->teilnehmer
            ->flatMap(fn (Personen $person) => $person->schueler ?? collect())
            ->filter(fn ($item) => $item->schule_id && $item->schuljahr && $item->teil);

        if ($schueler->isNotEmpty()) {
            $best = $schueler
                ->groupBy(fn ($item) => $item->schule_id.'|'.$item->schuljahr.'|'.$item->teil)
                ->sortByDesc(fn ($items) => $items->count())
                ->first()
                ?->first();

            $context['partner_id'] ??= (int) $best->schule_id;
            $context['schuljahr'] ??= (string) $best->schuljahr;
            $context['teil'] ??= (string) $best->teil;
        }

        return $context;
    }

    private function bereichMarkierungen(string $bereich): array
    {
        $lower = mb_strtolower($bereich);

        return [
            'elektro' => str_contains($lower, 'elektro') ? 'X' : '',
            'IT' => str_contains($lower, 'it') || str_contains($lower, 'medien') ? 'X' : '',
            'it' => str_contains($lower, 'it') || str_contains($lower, 'medien') ? 'X' : '',
            'hauswirtschaft' => str_contains($lower, 'hauswirtschaft') ? 'X' : '',
            'verkauf' => str_contains($lower, 'verkauf') ? 'X' : '',
            'kosmetik' => str_contains($lower, 'kosmetik') || str_contains($lower, 'friseur') ? 'X' : '',
            'metall' => str_contains($lower, 'metall') ? 'X' : '',
            'holz' => str_contains($lower, 'holz') ? 'X' : '',
        ];
    }

    private function schulform($schueler): string
    {
        if (! $schueler) {
            return '';
        }

        return ($schueler->foerderschueler ?? $schueler->foederschueler ?? false)
            ? 'Foerderschule'
            : 'Gemeinschaftsschule';
    }

    private function wordTemplate(string $name): string
    {
        return storage_path('vorlage/projekte/bop/word/'.$name);
    }

    private function attendanceDays(Carbon $start, Carbon $end): array
    {
        if ($end->lessThan($start)) {
            $end = $start->copy();
        }

        $days = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $days[] = $date->copy();
        }

        return $days;
    }

    private function writeAttendanceDayHeaders($sheet, array $days): void
    {
        $blocks = [
            ['weekday' => 'G5', 'date' => 'H5'],
            ['weekday' => 'K5', 'date' => 'L5'],
            ['weekday' => 'O5', 'date' => 'P5'],
        ];

        foreach ($blocks as $block) {
            $sheet->setCellValue($block['weekday'], '');
            $sheet->setCellValueExplicit($block['date'], '', DataType::TYPE_STRING);
        }

        $daysByBlock = match (count($days)) {
            0 => [],
            1 => [0 => $days[0]],
            2 => [0 => $days[0], 2 => $days[1]],
            default => [0 => $days[0], 1 => $days[1], 2 => $days[2]],
        };

        foreach ($daysByBlock as $blockIndex => $day) {
            $sheet->setCellValue($blocks[$blockIndex]['weekday'], $this->weekdayShort($day));
            $sheet->setCellValueExplicit($blocks[$blockIndex]['date'], $day->format('d.m.Y'), DataType::TYPE_STRING);
        }
    }

    private function tagesauswertungTemplate(string $bereich): ?string
    {
        $name = mb_strtolower($bereich);
        $file = match (true) {
            str_contains($name, 'it') || str_contains($name, 'medien') => 'it.pdf',
            str_contains($name, 'verkauf') => 'verkauf.pdf',
            str_contains($name, 'metall') => 'metall.pdf',
            str_contains($name, 'maler') || str_contains($name, 'lack') => 'maler.pdf',
            str_contains($name, 'holz') => 'holztechnik.pdf',
            str_contains($name, 'friseur') || str_contains($name, 'kosmetik') || str_contains($name, 'körperpflege') => 'friseur.pdf',
            str_contains($name, 'elektro') => 'elektro.pdf',
            str_contains($name, 'hauswirtschaft') => 'hauswirtschaft.pdf',
            default => null,
        };

        return $file ? storage_path('vorlage/projekte/bop/pdf/tagesauswertung/'.$file) : null;
    }

    private function tagesauswertungTermine(Gruppe $gruppe): array
    {
        $start = Carbon::parse($gruppe->anfangsdatum ?: now());
        $end = Carbon::parse($gruppe->enddatum ?: $start);
        $excluded = collect($gruppe->non_working_dates ?? [])->map(fn ($date) => Carbon::parse($date)->toDateString())->all();
        $dates = [];
        for ($date = $start->copy(); $date->lte($end) && count($dates) < 3; $date->addDay()) {
            if ($date->isWeekend() || in_array($date->toDateString(), $excluded, true)) {
                continue;
            }
            $dates[] = $date->format('d.m.Y');
        }

        return [1 => $dates[0] ?? '', 2 => $dates[1] ?? '', 3 => $dates[2] ?? ''];
    }

    private function writeTagesauswertungHeader(Fpdi $pdf, array $item, string $datum, bool $combinedName): void
    {
        $pdf->SetTextColor(20, 31, 43);
        if ($combinedName) {
            $this->writePdfValue($pdf, 48, 27.3, trim($item['vorname'].' '.$item['nachname']), 91);
        } else {
            $this->writePdfValue($pdf, 48, 27.3, $item['vorname'], 34);
            $this->writePdfValue($pdf, 108, 27.3, $item['nachname'], 34);
        }
        $this->writePdfValue($pdf, 151, 27.3, $datum, 45);
        $this->writePdfValue($pdf, 48, 39.1, $item['schule'], 30);
        $this->writePdfValue($pdf, 105, 39.1, $item['klasse'], 37);
    }

    private function writeTagesauswertungPartnerLogos(Fpdi $pdf): void
    {
        $ministryLogo = public_path('img/einteilung-export/partner-bundesministerium.png');
        $bibbLogo = public_path('img/einteilung-export/partner-bibb.png');

        if (! is_file($ministryLogo) || ! is_file($bibbLogo)) {
            return;
        }

        // Die Vorlagen enthalten noch die alten BMBF/BIBB-Grafiken. Der weiße
        // Hintergrund entfernt diese gezielt, ohne das Berufsfeldmotiv anzutasten.
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(154, 240, 56, 57, 'F');
        $pdf->Image($ministryLogo, 156, 241, 52, 32, 'PNG');
        $pdf->Image($bibbLogo, 160, 277, 46, 0, 'PNG');
    }

    private function writePdfValue(Fpdi $pdf, float $x, float $y, string $value, float $width): void
    {
        $encoded = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value) ?: '';
        $fontSize = 9.0;
        $pdf->SetFont('Helvetica', 'B', $fontSize);
        while ($pdf->GetStringWidth($encoded) > $width && $fontSize > 6.5) {
            $fontSize -= 0.25;
            $pdf->SetFont('Helvetica', 'B', $fontSize);
        }
        $pdf->SetXY($x, $y);
        $pdf->Cell($width, 4.5, $encoded, 0, 0, 'L', false);
    }

    private function weekdayShort(Carbon $date): string
    {
        return match ($date->dayOfWeekIso) {
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            6 => 'Sa',
            default => 'So',
        };
    }

    private function canUseGroup($user, ?Gruppe $gruppe): bool
    {
        if (! $user || ! $gruppe) {
            return false;
        }

        if ($user->can('gruppe.view.all') || $user->can('projekt.mitarbeiter.view.all')) {
            return true;
        }

        return (int) $gruppe->personen_id === (int) $this->userPersonId($user);
    }

    private function userPersonId($user): ?int
    {
        return $user?->person_id ?? $user?->person?->id;
    }

    private function tmpPath(string $filename): string
    {
        $dir = storage_path('app/tmp');
        File::ensureDirectoryExists($dir);

        return $dir.DIRECTORY_SEPARATOR.uniqid('', true).'_'.$filename;
    }

    private function formatDate($value): string
    {
        if (! $value) {
            return '';
        }

        return Carbon::parse($value)->format('d.m.Y');
    }

    private function safeFileName(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', trim($value));

        return trim($value, '_') ?: 'export';
    }
}
