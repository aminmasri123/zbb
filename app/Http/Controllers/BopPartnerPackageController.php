<?php

namespace App\Http\Controllers;

use App\Models\Dokumente;
use App\Models\Partner;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\User;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\InputBag;
use ZipArchive;

class BopPartnerPackageController extends Controller
{
    private const PACKAGES = [
        'vorbereitung-pa' => [
            'name' => 'Vorbereitung für die PA',
            'permissions' => ['dokumente.ansprechpartner.manage', 'anwesenheit.abrechnung'],
            'documents' => ['Elterneinverständniserklärung', 'Anwesenheitsliste Vorbereitung PA'],
        ],
        'rolltag' => [
            'name' => 'Rolltag',
            'permissions' => ['dokumente.schule.export', 'gruppe.bop.export.namensschilder', 'anwesenheit.abrechnung', 'anwesenheit.abrechnung'],
            'documents' => ['BOP Hausordnung', 'Namensschilder', 'Anwesenheitsliste Rolltag', 'Anwesenheitsliste BO/BIBB DIN A3'],
        ],
        'feedbackgespraech' => [
            'name' => 'Feedbackgespräch',
            'permissions' => [],
            'documents' => ['USB - Steckbrief', 'Lehrerliste BO-Tage Auswertungsgespräch', 'BOP Bestätigung Programmdurchführung'],
        ],
    ];

    public static function options(Projekt $project, User $user, Collection $partnerDocuments): array
    {
        if (! str_contains(mb_strtoupper($project->name), 'BOP')) {
            return [];
        }

        $templates = $partnerDocuments->keyBy(fn ($document) => Str::slug($document['name']));

        return collect(self::PACKAGES)->map(function ($package, $key) use ($project, $user, $templates) {
            $definition = self::definition($project, $key);
            $documents = self::visibleDocuments($key, $definition['documents'], $user, $templates);

            return [
                'id' => $key,
                'name' => $definition['name'],
                'documents' => $documents,
                'documentOptions' => self::documentOptions($key, $user, $templates),
                'unavailableReason' => $documents === [] ? 'Für dieses Paket sind keine Dokumente zum Export verfügbar.' : null,
            ];
        })->values()->all();
    }

    private static function documentOptions(string $package, User $user, Collection $templates): array
    {
        $options = array_merge(self::PACKAGES[$package]['documents'], ['Namensschilder'], $templates->pluck('name')->all());

        return self::visibleDocuments($package, array_unique($options), $user, $templates);
    }

    private static function builtInDocuments(string $package): array
    {
        return $package === 'feedbackgespraech' ? ['Namensschilder'] : [...self::PACKAGES[$package]['documents'], 'Namensschilder'];
    }

    private static function visibleDocuments(string $package, array $documents, User $user, Collection $templates): array
    {
        $builtIn = self::builtInDocuments($package);

        return array_values(array_filter($documents, function ($name) use ($package, $user, $templates, $builtIn) {
            if (! in_array($name, $builtIn, true)) {
                return $templates->has(Str::slug($name));
            }

            $index = array_search($name, self::PACKAGES[$package]['documents'], true);
            $permission = $name === 'Namensschilder'
                ? 'gruppe.bop.export.namensschilder'
                : self::PACKAGES[$package]['permissions'][$index];

            return $user->can($permission);
        }));
    }

    private static function definition(Projekt $project, string $package): array
    {
        return array_replace(self::PACKAGES[$package], $project->rule_settings['bop_partner_packages'][$package] ?? []);
    }

    public function schools(Request $request, ActiveProjectContext $context)
    {
        $project = $this->bopProject($request, $context);
        $schools = PersonenIstSchueler::forExportProject($project->id)
            ->join('partners', 'partners.id', '=', 'personen_ist_schuelers.schule_id')
            ->whereIn('schule_id', $project->partners()->select('partners.id'))
            ->whereNotNull('schuljahr')->whereNotNull('teil')
            ->select('schule_id', 'partners.name', 'schuljahr', 'teil')
            ->distinct()->orderBy('partners.name')->get()
            ->filter(fn ($row) => preg_match('/^\d{4}/', (string) $row->schuljahr) && filled($row->teil))
            ->groupBy('schule_id')
            ->map(fn ($rows) => [
                'id' => $rows->first()->schule_id,
                'name' => $rows->first()->name,
                'years' => $rows->groupBy(fn ($row) => substr($row->schuljahr, 0, 4))
                    ->map(fn ($entries) => $entries->pluck('teil')->map(fn ($part) => (string) $part)->unique()->values()),
            ])->values();

        return response()->json(['schools' => $schools]);
    }

    public function update(Request $request, string $package, ActiveProjectContext $context)
    {
        $project = $this->bopProject($request, $context);
        abort_unless($request->user()->can('projekt.update'), 403);
        abort_unless(isset(self::PACKAGES[$package]), 404);
        $user = $request->user();
        $templates = app(PartnerController::class)->partnerDocumentsForProject($project, $user)
            ->keyBy(fn ($document) => Str::slug($document['name']));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['required', 'string', 'distinct', Rule::in(self::documentOptions($package, $user, $templates))],
        ]);

        DB::transaction(function () use ($project, $package, $data, $user, $templates) {
            $locked = Projekt::query()->lockForUpdate()->findOrFail($project->id);
            $settings = $locked->rule_settings ?? [];
            $existing = self::definition($locked, $package)['documents'];
            $hidden = array_diff($existing, self::visibleDocuments($package, $existing, $user, $templates));
            $data['documents'] = array_values(array_merge($data['documents'], $hidden));
            $settings['bop_partner_packages'][$package] = $data;
            $locked->update(['rule_settings' => $settings]);
        });

        return response()->json(['packages' => self::options(
            $project->refresh(), $request->user(), app(PartnerController::class)->partnerDocumentsForProject($project, $request->user())
        )]);
    }

    private function bopProject(Request $request, ActiveProjectContext $context): Projekt
    {
        $project = $context->currentAvailableFor($request->user());
        abort_unless($project && str_contains(mb_strtoupper($project->name), 'BOP'), 404);

        return $project;
    }

    public function __invoke(Request $request, Partner $partner, string $package, ActiveProjectContext $context)
    {
        $project = $context->currentAvailableFor($request->user());
        abort_unless($project && str_contains(mb_strtoupper($project->name), 'BOP'), 404);
        abort_unless($project->partners()->whereKey($partner->id)->exists(), 404);
        abort_unless(isset(self::PACKAGES[$package]), 404);

        $user = $request->user();
        $availableTemplates = app(PartnerController::class)->partnerDocumentsForProject($project, $user)
            ->keyBy(fn ($document) => Str::slug($document['name']));
        $definition = self::definition($project, $package);
        $definition['documents'] = self::visibleDocuments($package, $definition['documents'], $user, $availableTemplates);
        abort_if($definition['documents'] === [], 403, 'Für dieses Paket sind keine Dokumente zum Export verfügbar.');
        $includes = fn (int $index) => in_array(self::PACKAGES[$package]['documents'][$index], $definition['documents'], true);

        $data = $request->validate([
            'schuljahr' => ['required', 'regex:/^\d{4}(?:[-\/]\d{2,4})?$/'],
            'teil' => ['required', 'string', 'max:20'],
            'termin' => ['nullable', 'date_format:Y-m-d'],
            'preview' => ['nullable', 'boolean'],
            'sortierung' => ['nullable', 'in:klasse,nachname'],
            'rolltag_mode' => ['nullable', 'in:classes,rooms'],
            'rooms' => ['nullable', 'array', 'min:1', 'max:50'],
            'rooms.*.name' => ['required_with:rooms', 'string', 'max:120', 'distinct:ignore_case'],
            'rooms.*.capacity' => ['required_with:rooms', 'integer', 'min:1'],
        ]);
        $year = $data['schuljahr'];
        $part = $data['teil'];
        $date = $data['termin'] ?? null;
        $sort = $data['sortierung'] ?? 'klasse';
        $planning = app(\App\Services\Bop\PackagePlanning::class);
        $phaseType = ['vorbereitung-pa' => 'pa_preparation', 'rolltag' => 'roll_day', 'feedbackgespraech' => 'wt_feedback'][$package];
        $phase = $planning->phase($project->id, $partner->id, $year, $part, $phaseType);
        $plannedDates = $planning->dates($phase, $project->id, $partner->id, $year, $part);
        $hasPlannedGroups = $package === 'rolltag' && ($phase?->participants
            ?->contains(fn ($participant) => filled($participant->group_key)) ?? false);
        $needsDate = $package === 'vorbereitung-pa' ? $includes(1)
            : collect($definition['documents'])->contains(fn ($name) => $name !== 'Namensschilder');
        $students = PersonenIstSchueler::forExportProject($project->id)
            ->filterSchueler($partner->id, $year, $part);
        abort_unless((clone $students)->exists(), 422, 'Für diese Schule, dieses Schuljahr und diesen Teil wurden im aktiven Projekt keine Teilnehmenden gefunden.');
        if ($request->boolean('preview')) {
            $studentRows = (clone $students)->get();
            $scopedRows = $phase && $package !== 'vorbereitung-pa'
                ? $studentRows->filter(fn ($student) => $planning->includes($phase, $student, null))
                : $studentRows;

            return response()->json([
                'dates' => $plannedDates,
                'needsDate' => $needsDate,
                'hasPlannedGroups' => $hasPlannedGroups,
                'participantCount' => $scopedRows->count(),
                'participantCountsByDate' => collect($plannedDates)->mapWithKeys(fn ($plannedDate) => [
                    $plannedDate => $scopedRows
                        ->filter(fn ($student) => $planning->includes($phase, $student, $plannedDate))
                        ->count(),
                ]),
            ]);
        }
        if ($plannedDates && $needsDate) {
            if (count($plannedDates) === 1 || $package === 'vorbereitung-pa') $date = $plannedDates[0];
            else abort_unless(in_array($date, $plannedDates, true), 422, 'Bitte einen Termin aus der Planung auswählen.');
        }
        abort_if($needsDate && !$date, 422, 'In der Planung fehlt der Termin. Bitte ein Datum für dieses Paket eingeben.');

        $plannedStudentIds = $phase && $package !== 'vorbereitung-pa'
            ? (clone $students)->get()->filter(fn ($student) => $planning->includes($phase, $student, $plannedDates ? $date : null))->pluck('id')->all()
            : null;
        abort_if($plannedStudentIds === [], 422, 'Für diesen Planungstermin sind keine Teilnehmenden zugeordnet.');
        $roomMode = $package === 'rolltag' && ! $hasPlannedGroups && ($data['rolltag_mode'] ?? 'classes') === 'rooms';
        if ($roomMode) {
            abort_if(empty($data['rooms']), 422, 'Bitte mindestens einen Raum mit Kapazität angeben.');
            $participantCount = $plannedStudentIds === null ? (clone $students)->count() : count($plannedStudentIds);
            abort_if(collect($data['rooms'])->sum('capacity') < $participantCount, 422,
                'Die Raumkapazitäten reichen nicht für alle Teilnehmenden aus.');
        }
        $request->attributes->set('bop_package_student_ids', $plannedStudentIds);

        $builtIn = self::builtInDocuments($package);
        $templates = collect($definition['documents'])
            ->reject(fn ($name) => in_array($name, $builtIn, true))
            ->map(fn ($name) => $availableTemplates->get(Str::slug($name)));

        $directory = storage_path('exports');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/'.Str::uuid().'.zip';
        $zip = new ZipArchive;
        abort_unless($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'Das ZIP konnte nicht geöffnet werden.');
        $temporary = [];
        $complete = false;
        try {
            $bop = app(ProjektBopController::class);
            if ($package === 'vorbereitung-pa') {
                $scope = $this->exportRequest($request, [
                    'schuleId' => $partner->id, 'schuljahr' => $year, 'teil' => $part,
                    'listType' => 'pa_preparation', 'exportMode' => 'alle',
                    'usePlanning' => true, 'termin' => $date,
                ]);
                if ($includes(1)) {
                    $this->add($zip, 'Anwesenheitsliste Vorbereitung PA.xlsx', $bop->anwesenheitslistePAPreparationExportWord($scope, $project->id, $sort, true), $temporary);
                }

                if ($includes(0) && (clone $students)->where('eee', false)->exists()) {
                    $this->add($zip, 'Ausstehende Elterneinverständniserklärungen.xlsx', $bop->exportElterneinverstaendniserklaerungSchule($partner->id, $year, $part, $project->id, $sort), $temporary);
                } elseif ($includes(0)) {
                    abort_unless($zip->addFromString('Elterneinverständniserklärung.txt', "Für diese Auswahl sind keine ausstehenden Elterneinverständniserklärungen erfasst.\r\n"), 500);
                }
            } elseif ($package === 'rolltag') {
                $scope = $this->exportRequest($request, [
                    'termin' => $date,
                    'schuljahr' => $year,
                    'teil' => $part,
                    'sortierung' => $sort,
                    'usePlanning' => true,
                    'anzahlRaeumlichkeiten' => $roomMode ? count($data['rooms']) : null,
                    'raumNamen' => $roomMode ? collect($data['rooms'])->pluck('name')->all() : null,
                    'kapazitaeten' => $roomMode ? collect($data['rooms'])->pluck('capacity')->all() : null,
                ]);
                if ($includes(0)) {
                    $hausordnung = $bop->hausordnungExportPdf($partner->id, $year, $part, $sort, $date, $project->id, $plannedStudentIds);
                    abort_unless($hausordnung->isSuccessful() && str_starts_with((string) $hausordnung->headers->get('Content-Type'), 'application/pdf'), 422, 'Die Hausordnung konnte nicht erstellt werden.');
                    abort_unless($zip->addFromString('BOP Hausordnung.pdf', $hausordnung->getContent()), 500);
                }
                if ($includes(2)) {
                    $attendance = $bop->anwesenheitslistePOBOTag1(
                        $scope,
                        $partner->id,
                        $year,
                        $part,
                        ($hasPlannedGroups || $roomMode) ? 'exportAlleKlassen' : 'exportAlleKlassenZip',
                        $project->id
                    );
                    $this->addAttendanceLists($zip, $attendance, $temporary);
                }
                if ($includes(3)) {
                    $bibbScope = $this->exportRequest($request, [
                        'exportFormat' => 'A3',
                        'schuleIdInputBibb' => $partner->id,
                        'schuljahrInputBibb' => $year,
                        'teilInputBibb' => $part,
                        'days' => [['date' => $date, 'selected' => true]],
                    ]);
                    $this->add($zip, 'Anwesenheitsliste BO-BIBB DIN A3.docx',
                        $bop->anwesenheitslistePOBOExportWordBIBB($bibbScope, $project->id), $temporary);
                }
            }
            if (in_array('Namensschilder', $definition['documents'], true)) {
                $scope = $this->exportRequest($request, ['schuljahr' => $year, 'teil' => $part, 'sortierung' => $sort]);
                $this->add($zip, 'Namensschilder.docx', app(BopGruppeExportController::class)->partnerNamensschilder($scope, $partner, $project->id), $temporary);
            }
            foreach ($templates as $template) {
                $format = $template['ausgabeformate'][0];
                $scope = $this->exportRequest($request, ['format' => $format, 'schuljahr' => $year, 'teil' => $part, 'sortierung' => $sort]);
                $scope->attributes->set('bop_package_date', $date);
                $scope->attributes->set('bop_package_phase', $phaseType);
                $document = Dokumente::findOrFail($template['id']);
                $response = app(ExportWordController::class)->partnerDokument($scope, $partner, $document, $context, $project->id);
                $this->add($zip, basename($template['name']).'.'.$format, $response, $temporary, $document->typ !== 'pdf');
            }

            abort_unless($zip->close(), 500, 'Das ZIP konnte nicht gespeichert werden.');
            $complete = true;
        } finally {
            if (! $complete) {
                unset($zip);
                File::delete($path);
            }
            File::delete($temporary);
        }

        $filename = Str::slug($partner->name.'-'.$year.'-Teil-'.$part.'-'.$definition['name']).'.zip';

        return response()->download($path, $filename, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    private function exportRequest(Request $request, array $input): Request
    {
        return $request->duplicate($input, $input)->setJson(new InputBag($input));
    }

    private function addAttendanceLists(ZipArchive $zip, $response, array &$temporary): void
    {
        abort_unless($response instanceof BinaryFileResponse && $response->isSuccessful(), 422, 'Die Anwesenheitsliste Rolltag konnte nicht erstellt werden.');
        $path = $response->getFile()->getPathname();
        $temporary[] = $path;
        $lists = new ZipArchive;
        abort_unless($lists->open($path, ZipArchive::RDONLY) === true, 500, 'Die Klassenlisten konnten nicht geöffnet werden.');
        try {
            abort_unless($lists->numFiles > 0, 422, 'Für den Rolltag wurden keine Klassenlisten erstellt.');
            for ($index = 0; $index < $lists->numFiles; $index++) {
                $name = $lists->getNameIndex($index);
                $content = $lists->getFromIndex($index);
                abort_unless($name !== false && $content !== false
                    && $zip->addFromString('Anwesenheitsliste Rolltag/'.basename($name), $content), 500);
            }
        } finally {
            $lists->close();
        }
    }

    private function add(ZipArchive $zip, string $name, $response, array &$temporary, bool $delete = true): void
    {
        abort_unless($response instanceof BinaryFileResponse && $response->isSuccessful(), 422, 'Die Datei „'.$name.'“ konnte nicht erstellt werden. Bitte den Einzelexport prüfen.');
        $path = $response->getFile()->getPathname();
        if ($delete) {
            $temporary[] = $path;
        }

        abort_unless($zip->addFile($path, $name), 500, 'Die Datei „'.$name.'“ konnte nicht zum ZIP hinzugefügt werden.');
    }
}
