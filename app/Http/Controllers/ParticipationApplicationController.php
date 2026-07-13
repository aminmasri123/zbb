<?php

namespace App\Http\Controllers;

use App\Http\Controllers\PortalJobController;
use App\Models\ParticipantApplication;
use App\Models\ParticipantApplicationStatusHistory;
use App\Models\Personen;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParticipationApplicationController extends Controller
{
    public function __construct(private readonly ActiveProjectContext $activeProjectContext) {}

    public function update(Request $request, ParticipantApplication $application)
    {
        $project = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($project, 409, 'Bitte wählen Sie zuerst ein aktives Projekt aus.');
        $application->load('participation');
        abort_unless((int) $application->participation?->projekt_id === (int) $project->id, 404);
        abort_unless(Personen::query()->teilnehmer()->visibleForUser($request->user())->whereKey($application->participation->personen_id)->exists(), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(PortalJobController::STATUSES)],
            'applied_at' => ['nullable','date'],
            'next_action_at' => ['nullable','date'],
            'notes' => ['nullable','string','max:3000'],
        ]);
        $oldStatus = $application->status;
        $application->update($data);
        if ($oldStatus !== $application->status) {
            ParticipantApplicationStatusHistory::query()->create([
                'application_id' => $application->id,
                'from_status' => $oldStatus,
                'to_status' => $application->status,
                'changed_by_user_id' => $request->user()->id,
                'changed_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Bewerbung wurde aktualisiert.', 'application' => $application->fresh()->load('statusHistory')]);
    }
}
