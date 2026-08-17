<?php

namespace App\Http\Controllers;

use App\Models\Adresse;
use App\Models\Personen;
use App\Models\PersonenHasBildungsmassnahmen;
use App\Models\Projekt;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

class InternshipController extends Controller
{
    private const CERTIFICATE_REMOVE_MARKER = '__REMOVE_CERTIFICATE_PARAGRAPH__';

    public function __construct(private readonly ActiveProjectContext $activeProjectContext) {}

    public function index(Request $request)
    {
        $project = $this->activeProject($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'placement_type' => ['nullable', 'in:internal,external'],
            'status' => ['nullable', 'in:geplant,laufend,abgeschlossen,abgebrochen'],
            'host_project_id' => ['nullable', 'integer', 'exists:projekts,id'],
            'supervisor_person_id' => ['nullable', 'integer', 'exists:personens,id'],
            'follow_up' => ['nullable', 'in:overdue'],
        ]);

        $baseQuery = $this->projectInternships($request, $project->id);
        $query = (clone $baseQuery)
            ->with([
                'participant:id,vorname,nachname,geburtsdatum,geschlecht',
                'hostProject:id,name',
                'supervisor:id,vorname,nachname',
                'projektTeilnahme.projekt:id,name',
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->whereHas('participant', function (Builder $participant) use ($search) {
                    $participant->where(function (Builder $names) use ($search) {
                        $names->where('vorname', 'like', "%{$search}%")
                            ->orWhere('nachname', 'like', "%{$search}%")
                            ->orWhereRaw("concat(vorname, ' ', nachname) like ?", ["%{$search}%"]);
                    });
                });
            })
            ->when($filters['placement_type'] ?? null, fn (Builder $query, string $value) => $query->where('placement_type', $value))
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['host_project_id'] ?? null, fn (Builder $query, int $value) => $query->where('host_project_id', $value))
            ->when($filters['supervisor_person_id'] ?? null, fn (Builder $query, int $value) => $query->where('supervisor_person_id', $value))
            ->when(($filters['follow_up'] ?? null) === 'overdue', fn (Builder $query) => $query
                ->whereIn('status', ['geplant', 'laufend'])
                ->whereDate('next_follow_up_at', '<', today()))
            ->orderByRaw("case status when 'laufend' then 1 when 'geplant' then 2 when 'abgebrochen' then 3 else 4 end")
            ->orderByRaw('next_follow_up_at is null')
            ->orderBy('next_follow_up_at')
            ->orderByDesc('start');

        return Inertia::render('Praktikum/Index', [
            'internships' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'internal' => (clone $baseQuery)->where('placement_type', 'internal')->count(),
                'external' => (clone $baseQuery)->where('placement_type', 'external')->count(),
                'running' => (clone $baseQuery)->where('status', 'laufend')->count(),
                'overdue' => (clone $baseQuery)->whereIn('status', ['geplant', 'laufend'])
                    ->whereDate('next_follow_up_at', '<', today())->count(),
            ],
            'hostProjects' => Projekt::query()->where('aktiv', true)->orderBy('name')->get(['id', 'name']),
            'supervisors' => Personen::query()->mitarbeiter()->orderBy('nachname')->orderBy('vorname')
                ->get(['id', 'vorname', 'nachname']),
        ]);
    }

    public function contract(Request $request, PersonenHasBildungsmassnahmen $measure)
    {
        $measure = $this->authorizedMeasure($request, $measure);
        abort_if(mb_strlen((string) $measure->bemerkung) > 600, 422, 'Die weiterführende Vereinbarung ist für die Vertragsvorlage zu lang. Bitte kürzen Sie die Bemerkung auf höchstens 600 Zeichen.');

        $values = $this->documentValues($measure);
        $template = resource_path('templates/internships/praktikumsvertrag.docx');
        abort_unless(File::isFile($template), 500, 'Die Vorlage für den Praktikumsvertrag fehlt.');

        $processor = new TemplateProcessor($template);
        $processor->setValues([
            'host_name' => $values['contract_host_name'],
            'host_address' => $values['host_address'],
            'host_contact' => $values['supervisor_name'],
            'host_phone' => $values['supervisor_phone'],
            'host_email' => $values['supervisor_email'],
            'participant_name' => $values['participant_name'],
            'participant_address' => $values['participant_address'],
            'birth_date' => $values['birth_date'],
            'participant_phone' => $values['participant_phone'],
            'participant_email' => $values['participant_email'],
            'support_name' => $values['support_name'],
            'start' => $values['start'],
            'end' => $values['end'],
            'weekly_hours' => (string) ($measure->weekly_hours ?: 39),
            'attendance_weekday' => $measure->attendance_weekday ?: '________________',
            'occupation' => $measure->occupation ?: ($measure->department ?: '________________'),
            'supplementary' => trim((string) $measure->bemerkung),
            'check_orientation' => $measure->internship_kind === 'orientation' ? '☒' : '☐',
            'check_qualification' => $measure->internship_kind === 'qualification' ? '☒' : '☐',
            'check_integration' => $measure->internship_kind === 'integration' ? '☒' : '☐',
            'issued_at' => now()->format('d.m.Y'),
        ]);

        return $this->download($processor, 'Praktikumsvertrag', $values['participant_name']);
    }

    public function certificate(Request $request, PersonenHasBildungsmassnahmen $measure)
    {
        $measure = $this->authorizedMeasure($request, $measure);
        abort_unless($measure->status === 'abgeschlossen', 422, 'Eine Praktikumsbescheinigung kann erst nach Abschluss des Praktikums exportiert werden.');
        $values = $this->documentValues($measure);
        abort_if(blank($values['birth_date']) || blank($values['participant_address']), 422, 'Für die Bescheinigung müssen Geburtsdatum und Anschrift der Praktikantin bzw. des Praktikanten hinterlegt sein.');
        abort_if(blank($measure->activities), 422, 'Bitte hinterlegen Sie vor dem Export die Tätigkeiten des Praktikums.');
        abort_if(blank($measure->assessment), 422, 'Bitte hinterlegen Sie vor dem Export die Beurteilung und sozialen Kompetenzen.');

        $activities = collect(preg_split('/\R+/u', trim((string) $measure->activities)))
            ->map(fn ($value) => trim($value))->filter()->values();
        $assessment = collect(preg_split('/\R+/u', trim((string) $measure->assessment)))
            ->map(fn ($value) => trim($value))->filter()->values();
        abort_if($activities->count() > 6 || $activities->contains(fn ($value) => mb_strlen($value) > 180), 422, 'Die Bescheinigung unterstützt höchstens sechs Tätigkeiten mit jeweils maximal 180 Zeichen.');
        abort_if($assessment->count() > 4 || $assessment->contains(fn ($value) => mb_strlen($value) > 450), 422, 'Die Beurteilung unterstützt höchstens vier Absätze mit jeweils maximal 450 Zeichen.');

        $template = resource_path('templates/internships/praktikumsbescheinigung.docx');
        abort_unless(File::isFile($template), 500, 'Die Vorlage für die Praktikumsbescheinigung fehlt.');

        $processor = new TemplateProcessor($template);
        $replacement = [
            'organization_address' => $values['organization_address'],
            'salutation' => $values['salutation'],
            'participant_name' => $values['participant_name'],
            'birth_date' => $values['birth_date'],
            'participant_address' => $values['participant_address'],
            'start' => $values['start'],
            'end' => $values['end'],
            'host_name' => $values['certificate_host_name'],
            'support_name' => $values['support_name'],
            'support_role' => $values['support_name'] ? 'Projektbegleitung' : '',
            'supervisor_name' => $values['supervisor_name'],
            'supervisor_role' => $values['supervisor_name'] ? 'Praktikumsbetreuung' : '',
            'supervisor_phone' => $values['supervisor_phone'],
            'supervisor_mobile' => $values['supervisor_mobile'],
            'supervisor_email' => $values['supervisor_email'],
            'issued_at' => now()->format('d.m.Y'),
        ];

        for ($index = 1; $index <= 6; $index++) {
            $replacement["activity{$index}"] = $activities->get($index - 1, self::CERTIFICATE_REMOVE_MARKER);
        }
        for ($index = 1; $index <= 4; $index++) {
            $replacement["assessment{$index}"] = $assessment->get($index - 1, self::CERTIFICATE_REMOVE_MARKER);
        }
        $processor->setValues($replacement);

        return $this->download($processor, 'Praktikumsbescheinigung', $values['participant_name'], true);
    }

    private function activeProject(Request $request): Projekt
    {
        $project = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($project && $project->featureEnabled('internship_management'), 404);

        return $project;
    }

    private function projectInternships(Request $request, int $projectId): Builder
    {
        return PersonenHasBildungsmassnahmen::query()
            ->where('typ', 'Praktikum')
            ->whereNull('archived_at')
            ->whereHas('projektTeilnahme', fn (Builder $query) => $query->where('projekt_id', $projectId))
            ->whereHas('participant', fn (Builder $query) => $query->visibleForUser($request->user()));
    }

    private function authorizedMeasure(Request $request, PersonenHasBildungsmassnahmen $measure): PersonenHasBildungsmassnahmen
    {
        $project = $this->activeProject($request);
        abort_unless($measure->typ === 'Praktikum' && ! $measure->archived_at, 404);
        abort_unless($this->projectInternships($request, $project->id)->whereKey($measure->id)->exists(), 404);

        return $measure->load([
            'participant.adresses',
            'participant.kontaktes.kontakttyp',
            'participant.user:id,person_id,email',
            'hostProject.standorte.adresse',
            'supervisor.kontaktes.kontakttyp',
            'supervisor.user:id,person_id,email',
            'projektTeilnahme.standort.adresse',
            'projektTeilnahme.meta.betreuer.kontaktes.kontakttyp',
            'projektTeilnahme.meta.betreuer.user:id,person_id,email',
            'projektTeilnahme.meta.projektbegleiter.kontaktes.kontakttyp',
            'projektTeilnahme.meta.projektbegleiter.user:id,person_id,email',
        ]);
    }

    private function documentValues(PersonenHasBildungsmassnahmen $measure): array
    {
        $participant = $measure->participant;
        $support = $measure->projektTeilnahme?->meta?->projektbegleiter ?: $measure->projektTeilnahme?->meta?->betreuer;
        $supervisor = $measure->placement_type === 'internal' ? $measure->supervisor : null;
        $hostAddress = trim((string) $measure->host_address);
        if ($hostAddress === '' && $measure->placement_type === 'internal') {
            $hostAddress = $this->formatAddress($measure->hostProject?->standorte?->first()?->adresse?->first());
        }
        $organizationAddress = $this->formatAddress($measure->projektTeilnahme?->standort?->adresse?->first())
            ?: 'Ernst-Abbe-Straße 9, 66115 Saarbrücken';

        return [
            'participant_name' => trim("{$participant->vorname} {$participant->nachname}"),
            'participant_address' => $this->formatAddress($participant->adresses->first()),
            'birth_date' => $participant->geburtsdatum?->format('d.m.Y') ?: '',
            'participant_phone' => $this->contactValue($participant, ['mobil', 'telefon']),
            'participant_email' => $this->emailValue($participant),
            'salutation' => match ($participant->geschlecht) {
                'm' => 'Herr',
                'w' => 'Frau',
                default => '',
            },
            'contract_host_name' => $measure->placement_type === 'internal'
                ? trim('ZBB – '.($measure->hostProject?->name ?: $measure->traeger))
                : trim((string) $measure->traeger),
            'certificate_host_name' => $measure->placement_type === 'internal'
                ? trim('Zentrum für Bildung und Beruf Saar gGmbH – '.($measure->hostProject?->name ?: $measure->traeger))
                : trim((string) $measure->traeger),
            'host_address' => $hostAddress,
            'organization_address' => $organizationAddress,
            'supervisor_name' => $supervisor
                ? trim("{$supervisor->vorname} {$supervisor->nachname}")
                : trim((string) $measure->contact_name),
            'supervisor_phone' => $supervisor
                ? $this->contactValue($supervisor, ['telefon'])
                : trim((string) $measure->contact_phone),
            'supervisor_mobile' => $supervisor ? $this->contactValue($supervisor, ['mobil']) : '',
            'supervisor_email' => $supervisor ? $this->emailValue($supervisor) : trim((string) $measure->contact_email),
            'support_name' => $support ? trim("{$support->vorname} {$support->nachname}") : '',
            'start' => $measure->start?->format('d.m.Y') ?: '',
            'end' => $measure->end?->format('d.m.Y') ?: '',
        ];
    }

    private function formatAddress(?Adresse $address): string
    {
        if (! $address) {
            return '';
        }

        return trim(implode(', ', array_filter([
            trim("{$address->strasse} {$address->hausnummer}"),
            trim("{$address->plz} {$address->stadt}"),
            trim((string) $address->land),
        ])));
    }

    private function contactValue(?Personen $person, array $typeNames): string
    {
        if (! $person) {
            return '';
        }

        foreach ($typeNames as $needle) {
            $contact = $person->kontaktes->first(fn ($item) => str_contains(
                Str::lower((string) $item->kontakttyp?->name),
                Str::lower($needle)
            ));
            if ($contact?->wert) {
                return trim((string) $contact->wert);
            }
        }

        return '';
    }

    private function emailValue(?Personen $person): string
    {
        $contact = $this->contactValue($person, ['e-mail', 'email']);

        return $contact ?: trim((string) $person?->user?->email);
    }

    private function download(TemplateProcessor $processor, string $documentType, string $participantName, bool $removeEmptyParagraphs = false)
    {
        $directory = storage_path('app/temp');
        File::ensureDirectoryExists($directory);
        $safeName = Str::of($participantName)->ascii()->replaceMatches('/[^A-Za-z0-9_-]+/', '_')->trim('_')->toString();
        $filename = $documentType.'_'.($safeName ?: 'Praktikum').'_'.now()->format('Ymd').'.docx';
        $path = $directory.DIRECTORY_SEPARATOR.Str::uuid().'.docx';
        $processor->saveAs($path);

        if ($removeEmptyParagraphs) {
            $this->removeMarkedParagraphs($path);
        }

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function removeMarkedParagraphs(string $path): void
    {
        $zip = new ZipArchive;
        abort_unless($zip->open($path) === true, 500, 'Die erzeugte Bescheinigung konnte nicht nachbearbeitet werden.');
        $xml = $zip->getFromName('word/document.xml');
        abort_unless(is_string($xml), 500, 'Die erzeugte Bescheinigung ist unvollständig.');

        $document = new \DOMDocument;
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;
        abort_unless($document->loadXML($xml), 500, 'Die erzeugte Bescheinigung ist ungültig.');
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        foreach (iterator_to_array($xpath->query('//w:p')) as $paragraph) {
            if (str_contains($paragraph->textContent, self::CERTIFICATE_REMOVE_MARKER)) {
                $paragraph->parentNode?->removeChild($paragraph);
            }
        }

        $zip->addFromString('word/document.xml', $document->saveXML());
        $zip->close();
    }
}
