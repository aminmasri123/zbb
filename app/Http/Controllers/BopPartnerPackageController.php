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
            'permissions' => ['dokumente.schule.export', 'gruppe.bop.export.namensschilder', 'anwesenheit.abrechnung'],
            'documents' => ['BOP Hausordnung', 'Namensschilder', 'Anwesenheitsliste Rolltag'],
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

        return collect(self::PACKAGES)->map(function ($package, $key) use ($project, $user, $partnerDocuments) {
            $definition = self::definition($project, $key);
            $documents = $partnerDocuments->keyBy(fn ($document) => Str::slug($document['name']));
            $available = collect(self::requiredPermissions($key, $definition['documents']))->every(fn ($permission) => $user->can($permission));
            $builtIn = $key === 'feedbackgespraech' ? ['Namensschilder'] : [...$package['documents'], 'Namensschilder'];
            $missing = collect($definition['documents'])->reject(fn ($name) =>
                in_array($name, $builtIn, true) || isset($documents[Str::slug($name)])
            )->values()->all();

            return [
                'id' => $key,
                'name' => $definition['name'],
                'documents' => $definition['documents'],
                'documentOptions' => self::documentOptions($key, $partnerDocuments),
                'unavailableReason' => ! $available
                    ? 'Für dieses Paket fehlen Exportberechtigungen.'
                    : ($missing ? 'Vorlage fehlt oder ist nicht freigegeben: '.implode(', ', $missing).'.' : null),
            ];
        })->values()->all();
    }

    private static function documentOptions(string $package, Collection $partnerDocuments): array
    {
        return collect(self::PACKAGES[$package]['documents'])
            ->push('Namensschilder')
            ->merge($partnerDocuments->pluck('name'))
            ->unique()->values()->all();
    }

    private static function requiredPermissions(string $package, array $documents): array
    {
        $preset = self::PACKAGES[$package];

        return array_values(array_unique(array_merge(in_array('Namensschilder', $documents, true) ? ['gruppe.bop.export.namensschilder'] : [], array_filter($preset['permissions'], fn ($index) => in_array($preset['documents'][$index], $documents, true), ARRAY_FILTER_USE_KEY))));
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['required', 'string', 'distinct', Rule::in(self::documentOptions($package, app(PartnerController::class)->partnerDocumentsForProject($project, $request->user())))],
        ]);

        DB::transaction(function () use ($project, $package, $data) {
            $locked = Projekt::query()->lockForUpdate()->findOrFail($project->id);
            $settings = $locked->rule_settings ?? [];
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

        $definition = self::definition($project, $package);
        $includes = fn (int $index) => in_array(self::PACKAGES[$package]['documents'][$index], $definition['documents'], true);
        foreach (self::requiredPermissions($package, $definition['documents']) as $permission) {
            abort_unless($request->user()->can($permission), 403);
        }

        $data = $request->validate([
            'schuljahr' => ['required', 'regex:/^\d{4}(?:[-\/]\d{2,4})?$/'],
            'teil' => ['required', 'string', 'max:20'],
            'termin' => ['nullable', 'date_format:Y-m-d'],
            'sortierung' => ['nullable', 'in:klasse,nachname'],
        ]);
        $year = $data['schuljahr'];
        $part = $data['teil'];
        $date = $data['termin'] ?? null;
        $sort = $data['sortierung'] ?? 'klasse';
        abort_if($package === 'rolltag' && ($includes(0) || $includes(2)) && ! $date, 422, 'Bitte den Rolltag-Termin auswählen.');

        $students = PersonenIstSchueler::forExportProject($project->id)
            ->filterSchueler($partner->id, $year, $part);
        abort_unless((clone $students)->exists(), 422, 'Für diese Schule, dieses Schuljahr und diesen Teil wurden im aktiven Projekt keine Teilnehmenden gefunden.');

        $builtIn = $package === 'feedbackgespraech' ? ['Namensschilder'] : [...self::PACKAGES[$package]['documents'], 'Namensschilder'];
        $documents = app(PartnerController::class)->partnerDocumentsForProject($project, $request->user())
            ->keyBy(fn ($document) => Str::slug($document['name']));
        $templates = collect($definition['documents'])
            ->reject(fn ($name) => in_array($name, $builtIn, true))
            ->map(function ($name) use ($documents) {
                $document = $documents->get(Str::slug($name));
                abort_unless($document, 422, 'Vorlage fehlt oder ist nicht freigegeben: '.$name);

                return $document;
            });

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
                ]);
                if ($includes(1)) {
                    $this->add($zip, 'Anwesenheitsliste Vorbereitung PA.docx', $bop->anwesenheitslistePAPreparationExportWord($scope, $project->id, $sort), $temporary);
                }

                if ($includes(0) && (clone $students)->where('eee', false)->exists()) {
                    $this->add($zip, 'Ausstehende Elterneinverständniserklärungen.xlsx', $bop->exportElterneinverstaendniserklaerungSchule($partner->id, $year, $part, $project->id, $sort), $temporary);
                } elseif ($includes(0)) {
                    abort_unless($zip->addFromString('Elterneinverständniserklärung.txt', "Für diese Auswahl sind keine ausstehenden Elterneinverständniserklärungen erfasst.\r\n"), 500);
                }
            } elseif ($package === 'rolltag') {
                $scope = $this->exportRequest($request, ['termin' => $date, 'schuljahr' => $year, 'teil' => $part, 'sortierung' => $sort]);
                if ($includes(0)) {
                    $hausordnung = $bop->hausordnungExportPdf($partner->id, $year, $part, $sort, $date, $project->id);
                    abort_unless($hausordnung->isSuccessful() && str_starts_with((string) $hausordnung->headers->get('Content-Type'), 'application/pdf'), 422, 'Die Hausordnung konnte nicht erstellt werden.');
                    abort_unless($zip->addFromString('BOP Hausordnung.pdf', $hausordnung->getContent()), 500);
                }
                if ($includes(2)) {
                    $attendance = $bop->anwesenheitslistePOBOTag1($scope, $partner->id, $year, $part, 'exportAlleKlassenZip', $project->id);
                    $this->addAttendanceLists($zip, $attendance, $temporary);
                }
            }
            if (in_array('Namensschilder', $definition['documents'], true)) {
                $scope = $this->exportRequest($request, ['schuljahr' => $year, 'teil' => $part, 'sortierung' => $sort]);
                $this->add($zip, 'Namensschilder.docx', app(BopGruppeExportController::class)->partnerNamensschilder($scope, $partner, $project->id), $temporary);
            }
            foreach ($templates as $template) {
                $format = $template['ausgabeformate'][0];
                $scope = $this->exportRequest($request, ['format' => $format, 'schuljahr' => $year, 'teil' => $part, 'sortierung' => $sort]);
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
