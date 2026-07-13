<?php

namespace App\Http\Controllers;

use App\Models\ParticipantPortalMessage;
use App\Models\Personen;
use App\Models\ProjektHasPersonen;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ParticipantMessageController extends Controller
{
    public function __construct(private readonly ActiveProjectContext $activeProjectContext) {}

    public function portalIndex(Request $request)
    {
        $participations = ProjektHasPersonen::query()
            ->where('personen_id', $request->user()->person_id)
            ->with('projekt:id,name,portal_feature_settings')
            ->get()
            ->filter(fn ($participation) => $participation->projekt->portalFeatureEnabled('messaging'))
            ->values();

        $messages = ParticipantPortalMessage::query()
            ->whereIn('project_person_id', $participations->pluck('id'))
            ->with(['participation.projekt:id,name', 'sender:id,username,person_id', 'sender.person:id,vorname,nachname'])
            ->oldest()
            ->get();

        return Inertia::render('ParticipantPortal/Messages', [
            'participations' => $participations,
            'messages' => $messages,
        ]);
    }

    public function portalStore(Request $request)
    {
        $data = $request->validate([
            'project_person_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $participation = $this->portalParticipation($request, (int) $data['project_person_id']);

        $message = ParticipantPortalMessage::query()->create([
            'project_person_id' => $participation->id,
            'sender_user_id' => $request->user()->id,
            'sender_kind' => 'participant',
            'body' => trim($data['body']),
            'participant_read_at' => now(),
        ]);

        return response()->json([
            'message' => 'Nachricht wurde gesendet.',
            'item' => $this->loadMessage($message),
        ], 201);
    }

    public function portalRead(Request $request, ProjektHasPersonen $participation)
    {
        $participation = $this->portalParticipation($request, $participation->id);
        ParticipantPortalMessage::query()
            ->where('project_person_id', $participation->id)
            ->where('sender_kind', 'staff')
            ->whereNull('participant_read_at')
            ->update(['participant_read_at' => now()]);

        return response()->json(['message' => 'Nachrichten wurden als gelesen markiert.']);
    }

    public function staffStore(Request $request, ProjektHasPersonen $participation)
    {
        $participation = $this->staffParticipation($request, $participation);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $message = ParticipantPortalMessage::query()->create([
            'project_person_id' => $participation->id,
            'sender_user_id' => $request->user()->id,
            'sender_kind' => 'staff',
            'body' => trim($data['body']),
            'staff_read_at' => now(),
        ]);

        return response()->json([
            'message' => 'Nachricht wurde gesendet.',
            'item' => $this->loadMessage($message),
        ], 201);
    }

    public function staffRead(Request $request, ProjektHasPersonen $participation)
    {
        $participation = $this->staffParticipation($request, $participation);
        ParticipantPortalMessage::query()
            ->where('project_person_id', $participation->id)
            ->where('sender_kind', 'participant')
            ->whereNull('staff_read_at')
            ->update(['staff_read_at' => now()]);

        return response()->json(['message' => 'Nachrichten wurden als gelesen markiert.']);
    }

    private function portalParticipation(Request $request, int $id): ProjektHasPersonen
    {
        $participation = ProjektHasPersonen::query()
            ->whereKey($id)
            ->where('personen_id', $request->user()->person_id)
            ->with('projekt')
            ->firstOrFail();
        abort_unless($participation->projekt->portalFeatureEnabled('messaging'), 404);

        return $participation;
    }

    private function staffParticipation(Request $request, ProjektHasPersonen $participation): ProjektHasPersonen
    {
        $project = $this->activeProjectContext->currentAvailableFor($request->user());
        abort_unless($project && (int) $participation->projekt_id === (int) $project->id, 404);
        abort_unless($project->portalFeatureEnabled('messaging'), 404);
        abort_unless(
            Personen::query()->teilnehmer()->visibleForUser($request->user())->whereKey($participation->personen_id)->exists(),
            403
        );

        return $participation;
    }

    private function loadMessage(ParticipantPortalMessage $message): ParticipantPortalMessage
    {
        return $message->load(['sender:id,username,person_id', 'sender.person:id,vorname,nachname']);
    }
}
