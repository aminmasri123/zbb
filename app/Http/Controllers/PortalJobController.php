<?php

namespace App\Http\Controllers;

use App\Models\ParticipantApplication;
use App\Models\ParticipantApplicationStatusHistory;
use App\Models\ParticipantJobBookmark;
use App\Models\ParticipantJobRecommendation;
use App\Models\ProjektHasPersonen;
use App\Services\Jobs\BundesagenturJobSearchService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PortalJobController extends Controller
{
    public const STATUSES = ['draft', 'preparing', 'sent', 'response', 'interview', 'accepted', 'rejected', 'withdrawn'];

    public function index(Request $request)
    {
        $person = $request->user()->person;
        $participations = ProjektHasPersonen::query()
            ->where('personen_id', $person->id)
            ->with('projekt:id,name,portal_feature_settings')
            ->get()
            ->map(function ($participation) {
                $participation->setAttribute('portal_features', $participation->projekt->portalFeatureSettings());
                return $participation;
            })
            ->filter(fn ($participation) => $participation->portal_features['job_search'] || $participation->portal_features['application_management'])
            ->values();

        return Inertia::render('ParticipantPortal/Jobs', [
            'participations' => $participations,
            'bookmarks' => ParticipantJobBookmark::query()->where('person_id', $person->id)->latest()->get(),
            'applications' => ParticipantApplication::query()
                ->whereHas('participation', fn ($query) => $query->where('personen_id', $person->id))
                ->with(['participation.projekt:id,name', 'statusHistory', 'documents'])
                ->orderByRaw('next_action_at is null')
                ->orderBy('next_action_at')
                ->latest()
                ->get(),
            'applicationStatuses' => self::STATUSES,
            'applicationDocuments' => \App\Models\ParticipantPortalDocument::query()
                ->whereIn('project_person_id', $participations->pluck('id'))->where('status', 'approved')->where('visible_to_participant', true)
                ->orderBy('original_name')->get(),
            'recommendations' => ParticipantJobRecommendation::query()
                ->whereIn('project_person_id', $participations->pluck('id'))
                ->whereNull('dismissed_at')
                ->with(['participation.projekt:id,name', 'recommender:id,username,person_id', 'recommender.person:id,vorname,nachname'])
                ->latest('recommended_at')->get(),
        ]);
    }

    public function search(Request $request, BundesagenturJobSearchService $jobs)
    {
        $validated = $request->validate([
            'project_person_id' => ['required', 'integer'],
            'was' => ['nullable', 'string', 'max:120'],
            'wo' => ['nullable', 'string', 'max:120'],
            'umkreis' => ['nullable', 'integer', 'min:0', 'max:200'],
            'angebotsart' => ['nullable', Rule::in([1, 2, 4, 34])],
            'arbeitszeit' => ['nullable', 'string', 'max:30'],
            'veroeffentlichtseit' => ['nullable', 'integer', 'min:0', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $this->participation($request, $validated['project_person_id'], 'job_search');

        try {
            return response()->json($jobs->search($validated));
        } catch (ConnectionException|RequestException $exception) {
            report($exception);
            return response()->json(['message' => 'Die externe Jobsuche ist vorübergehend nicht erreichbar. Bitte versuchen Sie es später erneut.'], 503);
        }
    }

    public function storeBookmark(Request $request)
    {
        $data = $request->validate(['project_person_id' => ['required','integer'], ...$this->jobSnapshotRules()]);
        $this->participation($request, $data['project_person_id'], 'job_search');
        unset($data['project_person_id']);
        $bookmark = ParticipantJobBookmark::query()->updateOrCreate(
            ['person_id' => $request->user()->person_id, 'external_ref' => $data['external_ref']],
            [...$data, 'source' => 'ba_jobsuche']
        );
        return response()->json(['message' => 'Stelle wurde gemerkt.', 'bookmark' => $bookmark], 201);
    }

    public function destroyBookmark(Request $request, ParticipantJobBookmark $bookmark)
    {
        abort_unless((int) $bookmark->person_id === (int) $request->user()->person_id, 404);
        $bookmark->delete();
        return response()->json(['message' => 'Stelle wurde aus der Merkliste entfernt.']);
    }

    public function storeApplication(Request $request)
    {
        $data = $request->validate([
            'project_person_id' => ['required','integer'],
            ...$this->jobSnapshotRules(false),
            'next_action_at' => ['nullable','date'],
            'notes' => ['nullable','string','max:3000'],
        ]);
        $participation = $this->participation($request, $data['project_person_id'], 'application_management');
        if (!empty($data['external_ref']) && ParticipantApplication::query()
            ->where('project_person_id', $participation->id)
            ->where('external_ref', $data['external_ref'])
            ->exists()) {
            throw ValidationException::withMessages(['external_ref' => 'Für diese Stelle besteht in diesem Projekt bereits eine Bewerbung.']);
        }
        $application = ParticipantApplication::query()->create([
            ...$data,
            'project_person_id' => $participation->id,
            'created_by_user_id' => $request->user()->id,
            'status' => 'draft',
        ]);
        $this->recordStatus($application, null, 'draft', $request->user()->id);
        return response()->json(['message' => 'Bewerbung wurde angelegt.', 'application' => $application->load('participation.projekt:id,name')], 201);
    }

    public function updateApplication(Request $request, ParticipantApplication $application)
    {
        $participation = $this->participation($request, $application->project_person_id, 'application_management');
        abort_unless((int) $application->project_person_id === (int) $participation->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'applied_at' => ['nullable','date'],
            'next_action_at' => ['nullable','date'],
            'notes' => ['nullable','string','max:3000'],
        ]);
        $oldStatus = $application->status;
        $application->update($data);
        if ($oldStatus !== $application->status) $this->recordStatus($application, $oldStatus, $application->status, $request->user()->id);

        return response()->json(['message' => 'Bewerbung wurde aktualisiert.', 'application' => $application->fresh()->load(['participation.projekt:id,name','statusHistory'])]);
    }

    private function participation(Request $request, int $id, string $feature): ProjektHasPersonen
    {
        $participation = ProjektHasPersonen::query()
            ->whereKey($id)
            ->where('personen_id', $request->user()->person_id)
            ->with('projekt')
            ->firstOrFail();
        abort_unless($participation->projekt->portalFeatureEnabled($feature), 404);
        return $participation;
    }

    private function jobSnapshotRules(bool $externalRefRequired = true): array
    {
        return [
            'external_ref' => [$externalRefRequired ? 'required' : 'nullable','string','max:150'],
            'title' => ['required','string','max:255'],
            'employer' => ['nullable','string','max:255'],
            'location' => ['nullable','string','max:255'],
            'source_url' => ['nullable','url','max:2048'],
            'published_at' => ['nullable','date'],
        ];
    }

    private function recordStatus(ParticipantApplication $application, ?string $from, string $to, int $userId): void
    {
        ParticipantApplicationStatusHistory::query()->create([
            'application_id' => $application->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by_user_id' => $userId,
            'changed_at' => now(),
        ]);
    }
}
