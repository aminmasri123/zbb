<?php

namespace App\Http\Controllers;

use App\Models\Personen;
use App\Models\ProjectIntakeChecklistItem;
use App\Models\ParticipationIntakeChecklistCompletion;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntakeChecklistController extends Controller
{
    public function __construct(private readonly ActiveProjectContext $activeProjectContext) {}

    public function updateDefinition(Request $request, Projekt $projekt)
    {
        abort_unless((int) $request->user()->current_team_id === (int) $projekt->id, 403, 'Bitte wählen Sie dieses Projekt zuerst im Header aus.');
        $validated = $request->validate([
            'items' => ['present', 'array', 'max:100'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.label' => ['required', 'string', 'max:150'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.required' => ['required', 'boolean'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        DB::transaction(function () use ($projekt, $validated): void {
            $keptIds = [];
            foreach ($validated['items'] as $itemData) {
                $item = isset($itemData['id'])
                    ? $projekt->intakeChecklistItems()->whereKey($itemData['id'])->firstOrFail()
                    : new ProjectIntakeChecklistItem(['project_id' => $projekt->id]);
                $item->fill([
                    'label' => $itemData['label'], 'description' => $itemData['description'] ?? null,
                    'required' => (bool) $itemData['required'], 'active' => true,
                    'sort_order' => (int) $itemData['sort_order'],
                ])->save();
                $keptIds[] = $item->id;
            }
            $query = $projekt->intakeChecklistItems();
            if ($keptIds) $query->whereNotIn('id', $keptIds);
            $query->update(['active' => false]);
        });

        return response()->json(['message' => 'Aufnahmecheckliste wurde gespeichert.', 'items' => $this->projectItems($projekt)]);
    }

    public function updateCompletion(Request $request, ProjektHasPersonen $participation, ProjectIntakeChecklistItem $item)
    {
        $project = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($project, 409, 'Bitte wählen Sie zuerst ein aktives Projekt aus.');
        abort_unless((int) $participation->projekt_id === (int) $project->id, 404);
        abort_unless((int) $item->project_id === (int) $project->id && $item->active, 404);
        abort_unless(Personen::query()->teilnehmer()->visibleForUser($request->user())->whereKey($participation->personen_id)->exists(), 403);

        $completed = (bool) $request->validate(['completed' => ['required', 'boolean']])['completed'];
        $completion = ParticipationIntakeChecklistCompletion::query()->updateOrCreate(
            ['project_person_id' => $participation->id, 'checklist_item_id' => $item->id],
            ['completed' => $completed, 'completed_at' => $completed ? now() : null, 'completed_by_user_id' => $completed ? $request->user()->id : null]
        );

        return response()->json(['message' => 'Aufnahmestatus wurde gespeichert.', 'completion' => $completion->load('completedBy:id,name')]);
    }

    private function projectItems(Projekt $project)
    {
        return $project->intakeChecklistItems()->where('active', true)->orderBy('sort_order')->orderBy('id')->get();
    }
}
