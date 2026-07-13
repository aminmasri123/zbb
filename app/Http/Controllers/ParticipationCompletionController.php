<?php

namespace App\Http\Controllers;

use App\Models\ParticipationCompletionChecklistCompletion;
use App\Models\ParticipationCompletionReport;
use App\Models\Personen;
use App\Models\ProjectCompletionChecklistItem;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ParticipationCompletionController extends Controller
{
    public function __construct(private readonly ActiveProjectContext $activeProjectContext) {}

    public function updateDefinition(Request $request, Projekt $projekt)
    {
        $this->authorizeProject($request, $projekt);
        $data = $request->validate([
            'items' => ['present', 'array', 'max:100'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.label' => ['required', 'string', 'max:150'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.required' => ['required', 'boolean'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        DB::transaction(function () use ($projekt, $data) {
            $kept = [];
            foreach ($data['items'] as $item) {
                $model = isset($item['id'])
                    ? ProjectCompletionChecklistItem::query()->where('project_id', $projekt->id)->findOrFail($item['id'])
                    : new ProjectCompletionChecklistItem(['project_id' => $projekt->id]);
                $model->fill([...$item, 'active' => true])->save();
                $kept[] = $model->id;
            }
            ProjectCompletionChecklistItem::query()->where('project_id', $projekt->id)->when($kept, fn ($query) => $query->whereNotIn('id', $kept))->update(['active' => false]);
        });

        return response()->json(['message' => 'Abschlusscheckliste wurde gespeichert.', 'items' => $this->items($projekt->id)]);
    }

    public function updateCompletion(Request $request, ProjektHasPersonen $participation, ProjectCompletionChecklistItem $item)
    {
        $this->authorizeParticipation($request, $participation);
        abort_unless((int) $item->project_id === (int) $participation->projekt_id && $item->active, 404);
        $data = $request->validate(['completed' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:3000']]);
        $completion = ParticipationCompletionChecklistCompletion::query()->updateOrCreate(
            ['project_person_id' => $participation->id, 'checklist_item_id' => $item->id],
            [...$data, 'completed_by_user_id' => $data['completed'] ? $request->user()->id : null, 'completed_at' => $data['completed'] ? now() : null]
        );
        return response()->json(['message' => 'Abschlussprüfpunkt wurde gespeichert.', 'completion' => $completion->load('completedBy:id,name')]);
    }

    public function submit(Request $request, ProjektHasPersonen $participation)
    {
        $this->authorizeParticipation($request, $participation);
        $data = $request->validate([
            'completion_type' => ['required', Rule::in(['completed', 'terminated'])],
            'exit_date' => ['required', 'date'],
            'outcome' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:20000'],
            'recommendations' => ['nullable', 'string', 'max:10000'],
        ]);

        $snapshot = $this->snapshot($participation, $data);
        $report = DB::transaction(function () use ($request, $participation, $data, $snapshot) {
            $version = ((int) ParticipationCompletionReport::query()->where('project_person_id', $participation->id)->max('version')) + 1;
            return ParticipationCompletionReport::query()->create([...$data, 'project_person_id' => $participation->id, 'version' => $version, 'status' => 'submitted', 'snapshot' => $snapshot, 'snapshot_sha256' => hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 'created_by_user_id' => $request->user()->id]);
        });
        return response()->json(['message' => 'Abschlussbericht wurde als neue Version zur Freigabe eingereicht.', 'report' => $report->load('creator:id,name')], 201);
    }

    public function decide(Request $request, ParticipationCompletionReport $report)
    {
        $report->load('participation');
        $this->authorizeParticipation($request, $report->participation);
        abort_unless($report->status === 'submitted', 422, 'Nur eingereichte Berichte können entschieden werden.');
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'decision_note' => ['nullable', 'required_if:decision,rejected', 'string', 'max:5000']]);
        abort_if((int) $report->created_by_user_id === (int) $request->user()->id, 422, 'Der Abschlussbericht muss von einer anderen berechtigten Person entschieden werden.');

        if ($data['decision'] === 'approved') {
            $missing = ProjectCompletionChecklistItem::query()
                ->where('project_id', $report->participation->projekt_id)->where('active', true)->where('required', true)
                ->whereDoesntHave('completions', fn ($query) => $query->where('project_person_id', $report->participation->id)->where('completed', true))
                ->exists();
            abort_if($missing, 422, 'Vor der Freigabe müssen alle Pflichtpunkte der Abschlusscheckliste erledigt sein.');
        }

        DB::transaction(function () use ($request, $report, $data) {
            $report->update(['status' => $data['decision'], 'decision_note' => $data['decision_note'] ?? null, 'approved_by_user_id' => $request->user()->id, 'approved_at' => now()]);
            if ($data['decision'] === 'approved') {
                $report->participation->update(['status' => $report->completion_type === 'completed' ? 'abgeschlossen' : 'abgebrochen']);
            }
        });
        return response()->json(['message' => $data['decision'] === 'approved' ? 'Teilnahmeabschluss wurde freigegeben.' : 'Bericht wurde zur Überarbeitung abgelehnt.', 'report' => $report->fresh()->load(['creator:id,name', 'approver:id,name'])]);
    }

    public function export(Request $request, ParticipationCompletionReport $report)
    {
        $report->load('participation');
        $this->authorizeParticipation($request, $report->participation);
        abort_unless($report->status === 'approved', 404);
        return response()->streamDownload(fn () => print(json_encode(['report' => $report->only(['id','version','status','completion_type','exit_date','outcome','summary','recommendations','snapshot','snapshot_sha256','approved_at'])], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 'teilnahmeabschluss-'.$report->participation->id.'-v'.$report->version.'.json', ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    private function items(int $projectId)
    {
        return ProjectCompletionChecklistItem::query()->where('project_id', $projectId)->where('active', true)->orderBy('sort_order')->orderBy('id')->get();
    }

    private function snapshot(ProjektHasPersonen $participation, array $data): array
    {
        $participation->load(['projekt:id,name', 'teilnehmer:id,vorname,nachname,geburtsdatum', 'standort:id,name']);
        $items = ProjectCompletionChecklistItem::query()->where('project_id', $participation->projekt_id)->where('active', true)->with(['completions' => fn ($query) => $query->where('project_person_id', $participation->id)])->orderBy('sort_order')->get();
        return ['generated_at' => now()->toISOString(), 'participation' => ['id' => $participation->id, 'project' => $participation->projekt?->name, 'participant' => trim($participation->teilnehmer?->vorname.' '.$participation->teilnehmer?->nachname), 'location' => $participation->standort?->name, 'status_before_completion' => $participation->status], 'report' => $data, 'checklist' => $items->map(fn ($item) => ['label' => $item->label, 'required' => $item->required, 'completed' => (bool) $item->completions->first()?->completed, 'note' => $item->completions->first()?->note])->all()];
    }

    private function authorizeProject(Request $request, Projekt $project): void
    {
        $active = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($active && (int) $active->id === (int) $project->id && $project->featureEnabled('completion_management'), 404);
    }

    private function authorizeParticipation(Request $request, ProjektHasPersonen $participation): void
    {
        $participation->loadMissing('projekt');
        $this->authorizeProject($request, $participation->projekt);
        abort_unless(Personen::query()->teilnehmer()->visibleForUser($request->user())->whereKey($participation->personen_id)->exists(), 403);
    }
}
