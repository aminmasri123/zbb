<?php

namespace App\Http\Controllers;

use App\Models\AppTask;
use App\Models\Personen;
use App\Models\ProjektHasPersonen;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParticipationTaskController extends Controller
{
    public function __construct(private readonly ActiveProjectContext $activeProjectContext) {}

    public function store(Request $request, ProjektHasPersonen $participation)
    {
        $project = $this->authorizeParticipation($request, $participation);
        $data = $this->validatedTaskData($request, $project->id);

        $task = AppTask::query()->create([
            ...$data,
            'owner_user_id' => $request->user()->id,
            'project_id' => $project->id,
            'project_person_id' => $participation->id,
            'team_id' => null,
            'visibility' => 'project',
            'started_at' => $data['status'] === 'progress' ? now() : null,
            'completed_at' => $data['status'] === 'done' ? now() : null,
        ]);

        return response()->json(['message' => 'Aufgabe wurde angelegt.', 'task' => $this->loadTask($task)], 201);
    }

    public function update(Request $request, AppTask $task)
    {
        $participation = $task->participation;
        abort_unless($participation, 404);
        $project = $this->authorizeParticipation($request, $participation);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        $data = $this->validatedTaskData($request, $project->id);

        $previousStatus = $task->status;
        $task->fill($data);
        if ($data['status'] === 'progress' && $previousStatus !== 'progress' && !$task->started_at) $task->started_at = now();
        $task->completed_at = $data['status'] === 'done' ? ($task->completed_at ?: now()) : null;
        $task->save();

        return response()->json(['message' => 'Aufgabe wurde aktualisiert.', 'task' => $this->loadTask($task)]);
    }

    public function destroy(Request $request, AppTask $task)
    {
        abort_unless($task->participation, 404);
        $this->authorizeParticipation($request, $task->participation);
        $task->delete();

        return response()->json(['message' => 'Aufgabe wurde gelöscht.']);
    }

    private function authorizeParticipation(Request $request, ProjektHasPersonen $participation)
    {
        $project = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($project, 409, 'Bitte wählen Sie zuerst ein aktives Projekt aus.');
        abort_unless((int) $participation->projekt_id === (int) $project->id, 404);
        abort_unless(Personen::query()->teilnehmer()->visibleForUser($request->user())->whereKey($participation->personen_id)->exists(), 403);

        return $project;
    }

    private function rules(int $projectId): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assignee_person_id' => ['nullable', 'integer', 'exists:personens,id'],
            'status' => ['required', Rule::in(['open', 'progress', 'done'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'due_at' => ['nullable', 'date'],
            'visible_to_participant' => ['sometimes', 'boolean'],
        ];
    }

    private function validatedTaskData(Request $request, int $projectId): array
    {
        $data = $request->validate($this->rules($projectId));

        if (!empty($data['assignee_person_id'])) {
            $validAssignee = Personen::query()
                ->mitarbeiter()
                ->whereKey($data['assignee_person_id'])
                ->whereHas('projekte', fn ($query) => $query->where('projekts.id', $projectId))
                ->exists();

            if (!$validAssignee) {
                throw ValidationException::withMessages([
                    'assignee_person_id' => 'Die verantwortliche Person muss als Mitarbeiter dem aktiven Projekt zugewiesen sein.',
                ]);
            }
        }

        return $data;
    }

    private function loadTask(AppTask $task): AppTask
    {
        return $task->load(['owner:id,username,email', 'assignee:id,vorname,nachname']);
    }
}
