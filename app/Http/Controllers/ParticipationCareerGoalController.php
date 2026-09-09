<?php

namespace App\Http\Controllers;

use App\Models\{ParticipationCareerGoal, ProjektHasPersonen};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ParticipationCareerGoalController extends Controller
{
    private function authorizeParticipation(Request $request, ProjektHasPersonen $participation): void
    {
        $user = $request->user();
        abort_unless((int) $participation->projekt_id === (int) $user->current_team_id, 404);
        abort_unless($user->projekte()->where('projekts.id', $participation->projekt_id)->exists(), 403);
        abort_unless($participation->teilnehmer()->visibleForUser($user)->exists(), 403);
        if (\App\Models\RoleDataAccessSetting::scopeForUser($user, 'participant') === 'current_project_same_location') {
            abort_unless(ProjektHasPersonen::where('personen_id', $user->person_id)
                ->where('projekt_id', $participation->projekt_id)->where('status', 'aktiv')
                ->whereNotNull('standort_id')->where('standort_id', $participation->standort_id)->exists(), 403);
        }
    }

    public function index(Request $request, ProjektHasPersonen $participation)
    {
        $this->authorizeParticipation($request, $participation);
        return response()->json(['history' => ParticipationCareerGoal::where('project_person_id', $participation->id)->orderByDesc('id')->get()]);
    }

    public function store(Request $request, ProjektHasPersonen $participation)
    {
        $this->authorizeParticipation($request, $participation);
        $data = $request->validate([
            'previous_id' => ['present', 'nullable', 'integer'],
            'target' => ['required', Rule::in(['training', 'employment', 'undecided'])],
            'occupation' => ['required_unless:target,undecided', 'nullable', 'string', 'max:255'],
            'alternatives' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'agreement_status' => ['required', Rule::in(['wish', 'agreed'])],
            'documented_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        DB::transaction(function () use ($data, $participation, $request) {
            ProjektHasPersonen::whereKey($participation->id)->lockForUpdate()->firstOrFail();
            $latest = ParticipationCareerGoal::where('project_person_id', $participation->id)->latest('id')->first();
            abort_unless((int) ($latest?->id ?? 0) === (int) ($data['previous_id'] ?? 0), 409, 'Die Zielvereinbarung wurde inzwischen geändert. Bitte neu laden und Ihre Angaben vergleichen.');
            abort_if($latest && $data['documented_on'] < $latest->documented_on->toDateString(), 422, 'Das Datum darf nicht vor der bisherigen Zielvereinbarung liegen.');
            unset($data['previous_id']);
            ParticipationCareerGoal::create($data + ['project_person_id' => $participation->id, 'created_by' => $request->user()->id, 'author_name' => $request->user()->name]);
        });
        return $this->index($request, $participation);
    }
}
