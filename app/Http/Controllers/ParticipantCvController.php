<?php

namespace App\Http\Controllers;

use App\Models\ParticipantCvEntry;
use App\Models\ParticipantCvVersion;
use App\Models\ParticipantPortalProfile;
use App\Models\Personen;
use App\Services\CvTemplateCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ParticipantCvController extends Controller
{
    public function __construct(private readonly CvTemplateCatalog $templates) {}

    public function index(Request $request) { return $this->editor($request->user()->person); }

    public function staffIndex(Personen $person)
    {
        abort_unless($person->typ === 'teilnehmer', 404);
        return $this->editor($person, true);
    }

    public function store(Request $request, ?Personen $person = null)
    {
        $person = $this->target($request, $person);
        $entry = ParticipantCvEntry::query()->create([...$request->validate($this->rules()), 'person_id' => $person->id]);
        return response()->json(['message' => 'Lebenslaufeintrag wurde angelegt.', 'entry' => $entry], 201);
    }

    public function update(Request $request, ParticipantCvEntry $entry)
    {
        $this->authorizeEntry($request, $entry);
        $entry->update($request->validate($this->rules()));
        return response()->json(['message' => 'Lebenslaufeintrag wurde gespeichert.', 'entry' => $entry->fresh()]);
    }

    public function destroy(Request $request, ParticipantCvEntry $entry)
    {
        $this->authorizeEntry($request, $entry); $entry->delete();
        return response()->json(['message' => 'Lebenslaufeintrag wurde gelöscht.']);
    }

    public function createVersion(Request $request, ?Personen $person = null)
    {
        $person = $this->target($request, $person);
        $data = $request->validate(['label' => ['nullable','string','max:255'], 'template_key' => ['nullable', Rule::in($this->templates->keys())]]);
        $data['template_key'] ??= $this->templates->all()->first()['key'];
        $snapshot = $this->snapshot($person->id);
        $canonical = json_encode($snapshot, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);
        $version = DB::transaction(function () use ($request, $person, $data, $snapshot, $canonical) {
            $next = (int) ParticipantCvVersion::query()->where('person_id', $person->id)->lockForUpdate()->max('version') + 1;
            return ParticipantCvVersion::query()->create(['person_id'=>$person->id, 'version'=>$next, 'label'=>$data['label']??null,
                'template_key'=>$data['template_key'], 'snapshot'=>$snapshot, 'snapshot_sha256'=>hash('sha256',$canonical),
                'created_by_user_id'=>$request->user()->id, 'created_at'=>now()]);
        });
        return response()->json(['message'=>'Unveränderliche Lebenslaufversion wurde erstellt.','version'=>$version],201);
    }

    public function download(Request $request, ParticipantCvVersion $version)
    {
        $this->authorizeVersion($request, $version);
        return $this->pdf($version)->download($this->filename($version));
    }

    public function print(Request $request, ParticipantCvVersion $version)
    {
        $this->authorizeVersion($request, $version);
        return $this->pdf($version)->stream($this->filename($version));
    }

    private function editor(Personen $person, bool $staff = false)
    {
        return Inertia::render('ParticipantPortal/Resume', ['participant'=>$person->only(['id','vorname','nachname','geburtsdatum']),
            'profile'=>ParticipantPortalProfile::query()->where('person_id',$person->id)->first(),
            'entries'=>ParticipantCvEntry::query()->where('person_id',$person->id)->orderBy('type')->orderBy('sort_order')->get(),
            'versions'=>ParticipantCvVersion::query()->where('person_id',$person->id)->latest('version')->get(),
            'templates'=>$this->templates->all(), 'staffMode'=>$staff]);
    }

    private function target(Request $request, ?Personen $person): Personen
    {
        if ($person) { abort_unless($request->user()->can('teilnehmer.update') && $person->typ === 'teilnehmer', 403); return $person; }
        return $request->user()->person;
    }

    private function authorizeEntry(Request $request, ParticipantCvEntry $entry): void
    {
        abort_unless((int)$entry->person_id === (int)$request->user()->person_id || $request->user()->can('teilnehmer.update'), 404);
    }

    private function authorizeVersion(Request $request, ParticipantCvVersion $version): void
    {
        abort_unless((int)$version->person_id === (int)$request->user()->person_id || $request->user()->can('teilnehmer.update'), 404);
    }

    private function pdf(ParticipantCvVersion $version)
    {
        return Pdf::loadView('pdf.participant-cv', ['version'=>$version, 'template'=>$this->templates->find($version->template_key)])
            ->setPaper('a4')->setOption('isRemoteEnabled', false);
    }

    private function filename(ParticipantCvVersion $version): string { return 'lebenslauf-v'.$version->version.'.pdf'; }
    private function snapshot(int $personId): array { $person=Personen::findOrFail($personId); $profile=ParticipantPortalProfile::where('person_id',$personId)->first(); return ['person'=>$person->only(['vorname','nachname','geburtsdatum']),'profile'=>$profile?->only(['professional_headline','career_goal','skills','interests','available_from']),'entries'=>ParticipantCvEntry::where('person_id',$personId)->orderBy('type')->orderBy('sort_order')->get()->toArray()]; }
    private function rules(): array { return ['type'=>['required',Rule::in(['experience','education','qualification','language','skill'])],'title'=>['required','string','max:255'],'organization'=>['nullable','string','max:255'],'location'=>['nullable','string','max:255'],'starts_at'=>['nullable','date'],'ends_at'=>['nullable','date','after_or_equal:starts_at'],'current'=>['required','boolean'],'description'=>['nullable','string','max:5000'],'proficiency'=>['nullable','string','max:100'],'sort_order'=>['required','integer','min:0','max:9999']]; }
}
