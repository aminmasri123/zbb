<?php

namespace App\Http\Controllers;

use App\Models\ParticipantApplication;
use App\Models\ParticipantCareerDocument;
use App\Models\ParticipantCvEntry;
use App\Models\ParticipantPortalProfile;
use App\Models\Personen;
use App\Models\ProjektHasPersonen;
use App\Services\Ai\AgentClient;
use App\Services\CareerDocumentPdfImporter;
use App\Services\CvTemplateCatalog;
use App\Services\Projects\ActiveProjectContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class ParticipantCareerStudioController extends Controller
{
    public function __construct(private readonly CvTemplateCatalog $templates) {}

    public function index(Request $r)
    {
        $person = $this->target($r);

        return Inertia::render('ParticipantPortal/CareerStudio', ['participant' => $person->only(['id', 'vorname', 'nachname', 'geburtsdatum']), 'documents' => ParticipantCareerDocument::where('person_id', $person->id)->latest('updated_at')->get(), 'templates' => $this->templates->all(), 'starter' => $this->starter($person), 'staffMode' => $r->routeIs('teilnehmer.career-studio.*')]);
    }

    public function store(Request $r)
    {
        $person = $this->target($r);
        $data = $r->validate($this->rules());
        $doc = ParticipantCareerDocument::create([...$data, 'person_id' => $person->id, 'created_by_user_id' => $r->user()->id]);

        return response()->json(['message' => 'Dokument wurde erstellt.', 'document' => $doc], 201);
    }

    public function importPreview(Request $r, CareerDocumentPdfImporter $importer)
    {
        $this->target($r);
        $data = $r->validate(['file' => ['required', 'file', 'mimes:pdf', 'max:10240'], 'type' => ['required', Rule::in(['resume', 'cover_letter'])]]);
        $preview = $importer->preview($r->file('file'), $data['type']);

        return response()->json([...$preview, 'document' => ['type' => $data['type'], 'title' => mb_substr(pathinfo($r->file('file')->getClientOriginalName(), PATHINFO_FILENAME), 0, 255), 'template_key' => $this->templates->keys()[0], 'content' => $preview['content'], 'is_default' => false]])->header('Cache-Control', 'no-store');
    }

    public function importConfirm(Request $r)
    {
        $r->validate(['reviewed' => ['required', 'accepted'], 'type' => ['required', Rule::in(['resume', 'cover_letter'])]]);

        return $this->store($r);
    }

    public function update(Request $r, ParticipantCareerDocument $document)
    {
        $this->own($r, $document);
        $document->update($r->validate($this->rules()));

        return response()->json(['message' => 'Dokument wurde gespeichert.', 'document' => $document->fresh()]);
    }

    public function duplicate(Request $r, ParticipantCareerDocument $document)
    {
        $this->own($r, $document);
        $copy = $document->replicate();
        $copy->title = $document->title.' – Kopie';
        $copy->created_by_user_id = $r->user()->id;
        $copy->save();

        return response()->json(['message' => 'Kopie wurde erstellt.', 'document' => $copy], 201);
    }

    public function destroy(Request $r, ParticipantCareerDocument $document)
    {
        $this->own($r, $document);
        abort_if($document->applications()->exists(), 422, 'Das Dokument ist mit einer Bewerbung verknüpft.');
        $document->delete();

        return response()->json(['message' => 'Dokument wurde gelöscht.']);
    }

    public function preview(Request $r, ParticipantCareerDocument $document)
    {
        $this->own($r, $document);

        return $this->pdf($document)->stream($this->filename($document));
    }

    public function download(Request $r, ParticipantCareerDocument $document)
    {
        $this->own($r, $document);

        return $this->pdf($document)->download($this->filename($document));
    }

    public function downloadDocx(Request $r, ParticipantCareerDocument $document)
    {
        $this->own($r, $document);
        $c = $document->content;
        $word = new PhpWord;
        $section = $word->addSection(['marginTop' => 1000, 'marginRight' => 1200, 'marginBottom' => 1000, 'marginLeft' => 1200]);
        $section->addTitle((string) ($c['full_name'] ?? $document->title), 1);
        if (! empty($c['contact'])) {
            $section->addText((string) $c['contact'], ['size' => 9, 'color' => '666666']);
        }if ($document->type === 'cover_letter') {
            $section->addTextBreak();
            $section->addText((string) ($c['recipient'] ?? ''));
            $section->addTextBreak();
            $section->addText((string) ($c['subject'] ?? ''), ['bold' => true]);
            foreach (preg_split('/\R{2,}/', (string) ($c['body'] ?? '')) as $paragraph) {
                $section->addText($paragraph);
            }
        } else {
            if (! empty($c['headline'])) {
                $section->addText((string) $c['headline'], ['bold' => true]);
            }if (! empty($c['summary'])) {
                $section->addText((string) $c['summary']);
            }foreach (($c['entries'] ?? []) as $entry) {
                $section->addTitle((string) ($entry['section'] ?? 'Station'), 2);
                $section->addText(trim(($entry['title'] ?? '').' · '.($entry['period'] ?? '')), ['bold' => true]);
                $section->addText((string) ($entry['subtitle'] ?? ''));
                $section->addText((string) ($entry['description'] ?? ''));
            }
        }$path = tempnam(sys_get_temp_dir(), 'zbb-docx-');
        IOFactory::createWriter($word, 'Word2007')->save($path);

        return response()->download($path, str($document->title)->slug().'.docx')->deleteFileAfterSend(true);
    }

    public function generateCoverLetter(Request $r, AgentClient $agent)
    {
        $data = $r->validate(['application_id' => ['nullable', 'integer'], 'job_description' => ['required', 'string', 'max:12000'], 'tone' => ['nullable', Rule::in(['professional', 'warm', 'concise'])]]);
        $person = $r->user()->person;
        $application = null;
        if (! empty($data['application_id'])) {
            $application = ParticipantApplication::query()->whereKey($data['application_id'])->whereHas('participation', fn ($q) => $q->where('personen_id', $person->id))->firstOrFail();
        }$profile = ParticipantPortalProfile::where('person_id', $person->id)->first();
        $entries = ParticipantCvEntry::where('person_id', $person->id)->orderBy('starts_at')->get();
        $sources = [['source_id' => 'participant-profile', 'label' => 'Freigegebenes Teilnehmerprofil', 'page' => null, 'text' => json_encode(['name' => trim($person->vorname.' '.$person->nachname), 'headline' => $profile?->professional_headline, 'career_goal' => $profile?->career_goal, 'skills' => $profile?->skills, 'interests' => $profile?->interests], JSON_UNESCAPED_UNICODE)], ['source_id' => 'participant-cv', 'label' => 'Lebenslauf', 'page' => null, 'text' => $entries->toJson(JSON_UNESCAPED_UNICODE)], ['source_id' => 'job-description', 'label' => 'Stellenbeschreibung', 'page' => null, 'text' => $data['job_description']]];
        if ($application) {
            $sources[] = ['source_id' => 'application', 'label' => 'Bewerbung', 'page' => null, 'text' => json_encode($application->only(['title', 'employer', 'location', 'notes']), JSON_UNESCAPED_UNICODE)];
        }$result = $agent->generate(['run_id' => (string) Str::uuid(), 'task' => 'cover_letter', 'instruction' => 'Erstelle ein individuelles deutsches Bewerbungsanschreiben. Ton: '.($data['tone'] ?? 'professional').'. Gib nur den Brieftext aus, mit Anrede und Grußformel. Keine erfundenen Qualifikationen.', 'sources' => $sources, 'image_base64' => null]);

        return response()->json($result);
    }

    public function pdf(ParticipantCareerDocument $document)
    {
        return Pdf::loadView('pdf.career-document', ['document' => $document, 'template' => $this->templates->find($document->template_key)])->setPaper('a4');
    }

    private function filename($d)
    {
        return str($d->title)->slug().'.pdf';
    }

    private function own(Request $r, $d)
    {
        abort_unless((int) $d->person_id === (int) $this->target($r)->id, 404);
    }

    private function target(Request $r): Personen
    {
        if (! $r->routeIs('teilnehmer.career-studio.*')) {
            return $r->user()->person;
        }
        abort_unless($r->user()->can('teilnehmer.update'), 403);
        $person = $r->route('person') ?? $r->route('document')?->person_id;
        if (! $person instanceof Personen) {
            $person = Personen::findOrFail($person);
        }
        $project = app(ActiveProjectContext::class)->currentAvailableFor($r->user());
        abort_unless($project && $project->portalFeatureEnabled('application_management') && ProjektHasPersonen::where('projekt_id', $project->id)->where('personen_id', $person->id)->exists(), 404);
        abort_unless(Personen::query()->teilnehmer()->visibleForUser($r->user())->whereKey($person->id)->exists(), 403);

        return $person;
    }

    private function rules()
    {
        return ['type' => ['required', Rule::in(['resume', 'cover_page', 'cover_letter'])], 'title' => ['required', 'string', 'max:255'], 'template_key' => ['required', Rule::in($this->templates->keys())], 'content' => ['required', 'array'], 'content.full_name' => ['nullable', 'string', 'max:255'], 'content.headline' => ['nullable', 'string', 'max:255'], 'content.recipient' => ['nullable', 'string', 'max:1000'], 'content.subject' => ['nullable', 'string', 'max:255'], 'content.body' => ['nullable', 'string', 'max:15000'], 'content.summary' => ['nullable', 'string', 'max:5000'], 'content.contact' => ['nullable', 'string', 'max:1000'], 'content.entries' => ['nullable', 'array', 'max:100'], 'content.entries.*.section' => ['nullable', 'string', 'max:100'], 'content.entries.*.title' => ['nullable', 'string', 'max:255'], 'content.entries.*.subtitle' => ['nullable', 'string', 'max:255'], 'content.entries.*.period' => ['nullable', 'string', 'max:100'], 'content.entries.*.description' => ['nullable', 'string', 'max:5000'], 'is_default' => ['sometimes', 'boolean']];
    }

    private function starter(Personen $p)
    {
        $profile = ParticipantPortalProfile::where('person_id', $p->id)->first();

        return ['full_name' => trim($p->vorname.' '.$p->nachname), 'headline' => $profile?->professional_headline, 'summary' => $profile?->career_goal, 'contact' => '', 'recipient' => '', 'subject' => 'Bewerbung als …', 'body' => "Sehr geehrte Damen und Herren,\n\nmit großem Interesse bewerbe ich mich bei Ihnen.\n\nMit freundlichen Grüßen\n".trim($p->vorname.' '.$p->nachname), 'entries' => []];
    }
}
