<?php

namespace App\Http\Controllers;

use App\Models\EducationMeasureStatusHistory;
use App\Models\Personen;
use App\Models\PersonenHasBildungsmassnahmen;
use App\Models\ProjektHasPersonen;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PersonenHasBildungsmassnahmenController extends Controller
{
    private const STATUSES = ['geplant', 'laufend', 'abgeschlossen', 'abgebrochen'];
    private const TRANSITIONS = ['geplant' => ['geplant','laufend','abgebrochen'], 'laufend' => ['laufend','abgeschlossen','abgebrochen'], 'abgeschlossen' => ['abgeschlossen'], 'abgebrochen' => ['abgebrochen']];

    public function __construct(private readonly ActiveProjectContext $activeProjectContext) {}

    public function store(Request $request)
    {
        $participation = $this->participationForPerson($request, $request->integer('teilnehmer_id'));
        $data = $request->validate($this->rules());
        $this->validateResult($request, $data);

        $measure = DB::transaction(function () use ($request, $participation, $data) {
            $measure = PersonenHasBildungsmassnahmen::query()->create([...$data, 'person_id' => $participation->personen_id, 'projekt_person_id' => $participation->id]);
            EducationMeasureStatusHistory::query()->create(['education_measure_id' => $measure->id, 'from_status' => null, 'to_status' => $measure->status, 'note' => 'Eintrag angelegt', 'changed_by_user_id' => $request->user()->id]);
            return $measure;
        });

        return response()->json(['message' => 'Praktikum/Bildungsmaßnahme wurde angelegt.', 'data' => $this->load($measure)], 201);
    }

    public function update(Request $request, PersonenHasBildungsmassnahmen $measure)
    {
        $this->authorizeMeasure($request, $measure);
        abort_if($measure->archived_at, 422, 'Archivierte Einträge können nicht bearbeitet werden.');
        $data = $request->validate($this->rules(false));
        $this->validateResult($request, $data);
        abort_unless(in_array($data['status'], self::TRANSITIONS[$measure->status] ?? [], true), 422, 'Dieser Statusübergang ist nicht erlaubt.');

        DB::transaction(function () use ($request, $measure, $data) {
            $oldStatus = $measure->status;
            $measure->update($data);
            if ($oldStatus !== $data['status']) EducationMeasureStatusHistory::query()->create(['education_measure_id' => $measure->id, 'from_status' => $oldStatus, 'to_status' => $data['status'], 'note' => $request->input('status_note'), 'changed_by_user_id' => $request->user()->id]);
        });

        return response()->json(['message' => 'Verlauf wurde gespeichert.', 'data' => $this->load($measure->fresh())]);
    }

    public function destroy(Request $request, PersonenHasBildungsmassnahmen $measure)
    {
        $this->authorizeMeasure($request, $measure);
        $measure->update(['archived_at' => now()]);
        return response()->json(['message' => 'Eintrag wurde archiviert. Die Historie bleibt erhalten.']);
    }

    private function rules(bool $withPerson = true): array
    {
        return [
            'teilnehmer_id' => [$withPerson ? 'required' : 'sometimes', 'integer', 'exists:personens,id'],
            'typ' => ['required', Rule::in(['Praktikum','Fortbildung','Schulung','Weiterbildung','Sprachkurs','Integrationskurs'])],
            'traeger' => ['nullable','string','max:255'], 'contact_name' => ['nullable','string','max:255'],
            'contact_email' => ['nullable','email','max:255'], 'contact_phone' => ['nullable','string','max:50'],
            'start' => ['required','date'], 'end' => ['required','date','after_or_equal:start'],
            'weekly_hours' => ['nullable','integer','min:1','max:168'], 'next_follow_up_at' => ['nullable','date'],
            'bemerkung' => ['nullable','string','max:10000'], 'objective' => ['nullable','string','max:10000'],
            'result' => ['nullable','string','max:10000'], 'status' => ['required', Rule::in(self::STATUSES)],
            'status_note' => ['nullable','string','max:3000'],
        ];
    }

    private function validateResult(Request $request, array $data): void
    {
        if (in_array($data['status'], ['abgeschlossen','abgebrochen'], true) && blank($data['result'] ?? null)) {
            $request->validate(['result' => ['required','string','max:10000']]);
        }
    }

    private function participationForPerson(Request $request, int $personId): ProjektHasPersonen
    {
        $project = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($project && $project->featureEnabled('internship_management'), 404);
        abort_unless(Personen::query()->teilnehmer()->visibleForUser($request->user())->whereKey($personId)->exists(), 403);
        return ProjektHasPersonen::query()->where('projekt_id', $project->id)->where('personen_id', $personId)->firstOrFail();
    }

    private function authorizeMeasure(Request $request, PersonenHasBildungsmassnahmen $measure): void
    {
        $measure->loadMissing('projektTeilnahme');
        $participation = $this->participationForPerson($request, $measure->person_id);
        abort_unless((int) $measure->projekt_person_id === (int) $participation->id, 404);
    }

    private function load(PersonenHasBildungsmassnahmen $measure): PersonenHasBildungsmassnahmen
    {
        return $measure->load('statusHistory.changer:id,name');
    }
}
