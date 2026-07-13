<?php

namespace App\Http\Controllers;

use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Personen;
use App\Models\Tage;
use App\Models\Zeiten;
use App\Services\Projects\ActiveProjectContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnwesenheitController extends Controller
{
    public function __construct(private readonly ActiveProjectContext $activeProjectContext)
    {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'anwesenheitsstatuten_id' => ['required', 'integer', 'exists:anwesenheitsstatutens,id'],
            'tag' => ['required', 'date', 'exists:tages,datum'],
            'startzeit' => ['required', 'date_format:H:i'],
            'endzeit' => ['required', 'date_format:H:i', 'after:startzeit'],
            'tatstartTime' => ['nullable', 'date_format:H:i'],
            'tatendTime' => ['nullable', 'date_format:H:i', 'after:tatstartTime'],
            'personen_id' => ['required', 'integer', 'exists:personens,id'],
            'bemerkung' => ['nullable', 'string', 'max:255'],
            'gruppe_id' => ['nullable', 'required_without:bereich_id', 'integer', 'exists:gruppes,id'],
            'bereich_id' => ['nullable', 'required_without:gruppe_id', 'integer', 'exists:gruppes,id'],
        ]);

        $groupId = (int) ($validated['gruppe_id'] ?? $validated['bereich_id']);
        $this->authorizeParticipant((int) $validated['personen_id']);
        $this->authorizedGroup($groupId);

        DB::transaction(function () use ($validated, $groupId): void {
            $tagId = (int) Tage::where('datum', $validated['tag'])->value('id');
            $plannedTimeId = $this->timeId($validated['startzeit'], $validated['endzeit']);
            $actualTimeId = $this->timeId($validated['tatstartTime'] ?? null, $validated['tatendTime'] ?? null);

            GruppeHasPersonen::updateOrCreate(
                [
                    'personen_id' => $validated['personen_id'],
                    'gruppe_id' => $groupId,
                    'tage_id' => $tagId,
                ],
                [
                    'zeitgeplant_id' => $plannedTimeId,
                    'zeittatsaechlich_id' => $actualTimeId,
                    'anwesenheitsstatuten_id' => $validated['anwesenheitsstatuten_id'],
                    'user_id' => request()->user()?->person_id,
                    'bemerkung' => $validated['bemerkung'] ?? null,
                ]
            );
        });

        return redirect()->back()->with('success', 'Anwesenheit gespeichert.');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:gruppe_has_personens,id'],
            'personen_id' => ['required', 'integer', 'exists:personens,id'],
            'anwesenheitsstatuten_id' => ['required', 'integer', 'exists:anwesenheitsstatutens,id'],
            'tag' => ['required', 'date', 'exists:tages,datum'],
            'startzeit' => ['nullable', 'date_format:H:i'],
            'endzeit' => ['nullable', 'date_format:H:i', 'after:startzeit'],
            'tatstartTime' => ['nullable', 'date_format:H:i'],
            'tatendTime' => ['nullable', 'date_format:H:i', 'after:tatstartTime'],
            'bemerkung' => ['nullable', 'string', 'max:255'],
            'gruppe_id' => ['nullable', 'integer', 'exists:gruppes,id'],
            'bereich_id' => ['nullable', 'integer', 'exists:gruppes,id'],
        ]);

        $this->authorizeParticipant((int) $validated['personen_id']);
        $tagId = (int) Tage::where('datum', $validated['tag'])->value('id');
        $requestedGroupId = $validated['gruppe_id'] ?? $validated['bereich_id'] ?? null;

        $attendance = ! empty($validated['id'])
            ? GruppeHasPersonen::query()->findOrFail($validated['id'])
            : GruppeHasPersonen::query()
                ->where('personen_id', $validated['personen_id'])
                ->where('tage_id', $tagId)
                ->when($requestedGroupId, fn ($query) => $query->where('gruppe_id', $requestedGroupId))
                ->first();

        if ($attendance) {
            abort_unless((int) $attendance->personen_id === (int) $validated['personen_id'], 403);
        }

        $groupId = $requestedGroupId ?: $attendance?->gruppe_id;
        abort_unless($groupId, 422, 'Bitte waehlen Sie eine Gruppe aus.');
        $this->authorizedGroup((int) $groupId);

        $plannedTimeId = $attendance?->zeitgeplant_id;
        if (! empty($validated['startzeit']) && ! empty($validated['endzeit'])) {
            $plannedTimeId = $this->timeId($validated['startzeit'], $validated['endzeit']);
        }

        $actualTimeId = $attendance?->zeittatsaechlich_id;
        if (! empty($validated['tatstartTime']) && ! empty($validated['tatendTime'])) {
            $actualTimeId = $this->timeId($validated['tatstartTime'], $validated['tatendTime']);
        }

        GruppeHasPersonen::updateOrCreate(
            ['id' => $attendance?->id],
            [
                'personen_id' => $validated['personen_id'],
                'gruppe_id' => $groupId,
                'tage_id' => $tagId,
                'zeitgeplant_id' => $plannedTimeId,
                'zeittatsaechlich_id' => $actualTimeId,
                'anwesenheitsstatuten_id' => $validated['anwesenheitsstatuten_id'],
                'bemerkung' => $validated['bemerkung'] ?? null,
                'user_id' => request()->user()?->person_id,
            ]
        );

        return redirect()->back()->with('success', $attendance ? 'Anwesenheit aktualisiert.' : 'Anwesenheit hinzugefuegt.');
    }

    public function destroy(int $id)
    {
        $attendance = GruppeHasPersonen::query()->findOrFail($id);
        $this->authorizeParticipant((int) $attendance->personen_id);

        if ($attendance->gruppe_id) {
            $this->authorizedGroup((int) $attendance->gruppe_id);
        }

        $attendance->delete();

        return response()->json(['message' => 'Anwesenheit erfolgreich geloescht.']);
    }

    private function authorizeParticipant(int $participantId): void
    {
        $user = request()->user();
        $project = $user ? $this->activeProjectContext->currentAvailableFor($user) : null;
        abort_unless($user && $project, 409, 'Bitte waehlen Sie zuerst ein aktives Projekt aus.');

        $isVisible = Personen::query()
            ->teilnehmer()
            ->visibleForUser($user)
            ->whereKey($participantId)
            ->whereHas('projekte', fn ($query) => $query->where('projekts.id', $project->id))
            ->exists();

        abort_unless($isVisible, 403);
    }

    private function authorizedGroup(int $groupId): Gruppe
    {
        $user = request()->user();
        $project = $user ? $this->activeProjectContext->currentAvailableFor($user) : null;
        abort_unless($project, 409, 'Bitte waehlen Sie zuerst ein aktives Projekt aus.');

        return Gruppe::query()
            ->whereKey($groupId)
            ->where('projekt_id', $project->id)
            ->firstOrFail();
    }

    private function timeId(?string $start, ?string $end): ?int
    {
        if (! $start || ! $end) {
            return null;
        }

        return Zeiten::firstOrCreate(['startzeit' => $start, 'endzeit' => $end])->id;
    }
}
