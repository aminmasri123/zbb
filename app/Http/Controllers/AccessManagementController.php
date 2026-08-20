<?php

namespace App\Http\Controllers;

use App\Models\AccessDoor;
use App\Models\AccessProfile;
use App\Models\AccessRequest as AccessRequestModel;
use App\Models\AccessRequestEvent;
use App\Models\Personen;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AccessManagementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canManageMasterData = $user->can('zutritt.stammdaten.manage');
        $canProcessAll = $canManageMasterData
            || $user->can('zutritt.antrag.approve')
            || $user->can('zutritt.aktivierung.update');

        $requests = AccessRequestModel::query()
            ->with([
                'requestedFor:id,vorname,nachname',
                'requestedBy:id,username,person_id',
                'requestedBy.person:id,vorname,nachname',
                'approvedBy:id,username,person_id',
                'approvedBy.person:id,vorname,nachname',
                'activatedBy:id,username,person_id',
                'activatedBy.person:id,vorname,nachname',
                'revokedBy:id,username,person_id',
                'revokedBy.person:id,vorname,nachname',
                'events.actor:id,username,person_id',
                'events.actor.person:id,vorname,nachname',
            ])
            ->when(! $canProcessAll, fn ($query) => $query->where(function ($query) use ($user) {
                $query->where('requested_by_user_id', $user->id)
                    ->orWhere('requested_for_person_id', $user->person_id);
            }))
            ->latest('submitted_at')
            ->get();

        $profiles = AccessProfile::query()
            ->with(['doors' => fn ($query) => $query->orderBy('name')])
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        $persons = $canProcessAll
            ? Personen::query()
                ->where('aktiv', true)
                ->where('typ', 'mitarbeiter')
                ->orderBy('nachname')
                ->orderBy('vorname')
                ->get(['id', 'vorname', 'nachname'])
            : Personen::query()
                ->whereKey($user->person_id)
                ->get(['id', 'vorname', 'nachname']);

        return Inertia::render('AccessManagement/Index', [
            'accessRequests' => $requests,
            'profiles' => $profiles,
            'doors' => $canManageMasterData
                ? AccessDoor::query()
                    ->with(['standort:id,name', 'roomFrom:id,name,raumnummer', 'roomTo:id,name,raumnummer'])
                    ->orderByDesc('active')
                    ->orderBy('name')
                    ->get()
                : [],
            'persons' => $persons,
            'locations' => $canManageMasterData
                ? Standort::query()->orderBy('name')->get(['id', 'name'])
                : [],
            'rooms' => $canManageMasterData
                ? Raeume::query()->orderBy('name')->get(['id', 'standort_id', 'name', 'raumnummer'])
                : [],
            'currentUserId' => $user->id,
            'accessPermissions' => [
                'canCreateRequest' => $user->can('zutritt.antrag.store'),
                'canApprove' => $user->can('zutritt.antrag.approve'),
                'canActivate' => $user->can('zutritt.aktivierung.update'),
                'canManageMasterData' => $canManageMasterData,
            ],
        ]);
    }

    public function storeDoor(Request $request)
    {
        $this->authorizePermission($request, 'zutritt.stammdaten.manage');

        $validated = $request->validate([
            'standort_id' => ['required', 'integer', 'exists:standorts,id'],
            'room_from_id' => ['nullable', 'integer', 'exists:raeumes,id'],
            'room_to_id' => ['nullable', 'integer', 'different:room_from_id', 'exists:raeumes,id'],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:80', 'unique:access_doors,code'],
            'external_reference' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach (['room_from_id', 'room_to_id'] as $field) {
            if (! empty($validated[$field])) {
                $belongsToLocation = Raeume::query()
                    ->whereKey($validated[$field])
                    ->where('standort_id', $validated['standort_id'])
                    ->exists();

                if (! $belongsToLocation) {
                    throw ValidationException::withMessages([
                        $field => 'Der ausgewaehlte Raum gehoert nicht zum Standort der Tuer.',
                    ]);
                }
            }
        }

        AccessDoor::create($validated + ['active' => true]);

        return back()->with('success', 'Tuer wurde angelegt.');
    }

    public function storeProfile(Request $request)
    {
        $this->authorizePermission($request, 'zutritt.stammdaten.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160', 'unique:access_profiles,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            'door_ids' => ['required', 'array', 'min:1'],
            'door_ids.*' => ['integer', Rule::exists('access_doors', 'id')->where('active', true)],
        ]);

        DB::transaction(function () use ($validated) {
            $profile = AccessProfile::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'active' => true,
            ]);
            $profile->doors()->sync(array_values(array_unique($validated['door_ids'])));
        });

        return back()->with('success', 'Zutrittsprofil wurde angelegt.');
    }

    public function storeRequest(Request $request)
    {
        $this->authorizePermission($request, 'zutritt.antrag.store');

        $validated = $request->validate([
            'requested_for_person_id' => [
                'required',
                'integer',
                Rule::exists('personens', 'id')->where(fn ($query) => $query
                    ->where('aktiv', true)
                    ->where('typ', 'mitarbeiter')),
            ],
            'access_profile_id' => [
                'required',
                'integer',
                Rule::exists('access_profiles', 'id')->where('active', true),
            ],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
            'reason' => ['required', 'string', 'max:3000'],
        ]);

        $user = $request->user();
        $canRequestForOthers = $user->can('zutritt.stammdaten.manage')
            || $user->can('zutritt.antrag.approve')
            || $user->can('zutritt.aktivierung.update');

        abort_unless(
            $canRequestForOthers || (int) $validated['requested_for_person_id'] === (int) $user->person_id,
            403
        );

        $person = Personen::query()->findOrFail($validated['requested_for_person_id']);
        $profile = AccessProfile::query()->with(['doors.standort:id,name'])->findOrFail($validated['access_profile_id']);
        $actorName = $this->userName($user);

        DB::transaction(function () use ($validated, $person, $profile, $user, $actorName) {
            $accessRequest = AccessRequestModel::create([
                'requested_for_person_id' => $person->id,
                'requested_for_name' => trim($person->vorname.' '.$person->nachname),
                'requested_by_user_id' => $user->id,
                'requested_by_name' => $actorName,
                'access_profile_id' => $profile->id,
                'profile_snapshot' => [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'doors' => $profile->doors->map(fn (AccessDoor $door) => [
                        'id' => $door->id,
                        'name' => $door->name,
                        'code' => $door->code,
                        'location' => $door->standort?->name,
                    ])->values()->all(),
                ],
                'valid_from' => $validated['valid_from'],
                'valid_until' => $validated['valid_until'],
                'reason' => $validated['reason'],
                'status' => AccessRequestModel::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

            $this->recordEvent(
                $accessRequest,
                $user,
                'submitted',
                null,
                AccessRequestModel::STATUS_SUBMITTED,
                'Zutrittsantrag eingereicht.'
            );
        });

        return back()->with('success', 'Zutrittsantrag wurde eingereicht.');
    }

    public function decide(Request $request, AccessRequestModel $accessRequest)
    {
        $this->authorizePermission($request, 'zutritt.antrag.approve');

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'comment' => ['nullable', 'string', 'max:3000', 'required_if:decision,reject'],
        ]);

        abort_if((int) $accessRequest->requested_by_user_id === (int) $request->user()->id, 422, 'Eigene Antraege koennen nicht selbst genehmigt werden.');

        DB::transaction(function () use ($request, $accessRequest, $validated) {
            $locked = AccessRequestModel::query()->whereKey($accessRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== AccessRequestModel::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'decision' => 'Dieser Antrag wurde bereits bearbeitet.',
                ]);
            }

            $targetStatus = $validated['decision'] === 'approve'
                ? AccessRequestModel::STATUS_APPROVED
                : AccessRequestModel::STATUS_REJECTED;

            $locked->update([
                'status' => $targetStatus,
                'approved_by_user_id' => $request->user()->id,
                'approved_at' => now(),
                'decision_note' => $validated['comment'] ?? null,
            ]);

            $this->recordEvent(
                $locked,
                $request->user(),
                $validated['decision'] === 'approve' ? 'approved' : 'rejected',
                AccessRequestModel::STATUS_SUBMITTED,
                $targetStatus,
                $validated['comment'] ?? null
            );
        });

        return back()->with('success', $validated['decision'] === 'approve' ? 'Antrag wurde genehmigt.' : 'Antrag wurde abgelehnt.');
    }

    public function activate(Request $request, AccessRequestModel $accessRequest)
    {
        $this->authorizePermission($request, 'zutritt.aktivierung.update');

        $validated = $request->validate([
            'technical_reference' => ['required', 'string', 'max:255'],
            'activation_note' => ['nullable', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($request, $accessRequest, $validated) {
            $locked = AccessRequestModel::query()->whereKey($accessRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== AccessRequestModel::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'technical_reference' => 'Nur ein genehmigter Antrag kann technisch aktiviert werden.',
                ]);
            }

            abort_if((int) $locked->approved_by_user_id === (int) $request->user()->id, 422, 'Genehmigung und technische Aktivierung muessen durch unterschiedliche Benutzer erfolgen.');

            if ($locked->valid_until->isPast()) {
                throw ValidationException::withMessages([
                    'technical_reference' => 'Der beantragte Gueltigkeitszeitraum ist bereits abgelaufen.',
                ]);
            }

            $locked->update([
                'status' => AccessRequestModel::STATUS_PROVISIONED,
                'activated_by_user_id' => $request->user()->id,
                'activated_at' => now(),
                'technical_reference' => $validated['technical_reference'],
                'activation_note' => $validated['activation_note'] ?? null,
            ]);

            $this->recordEvent(
                $locked,
                $request->user(),
                'manually_provisioned',
                AccessRequestModel::STATUS_APPROVED,
                AccessRequestModel::STATUS_PROVISIONED,
                $validated['activation_note'] ?? null,
                ['technical_reference' => $validated['technical_reference']]
            );
        });

        return back()->with('success', 'Die manuelle technische Aktivierung wurde dokumentiert.');
    }

    public function revoke(Request $request, AccessRequestModel $accessRequest)
    {
        $this->authorizePermission($request, 'zutritt.aktivierung.update');

        $validated = $request->validate([
            'revocation_note' => ['required', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($request, $accessRequest, $validated) {
            $locked = AccessRequestModel::query()->whereKey($accessRequest->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [AccessRequestModel::STATUS_APPROVED, AccessRequestModel::STATUS_PROVISIONED], true)) {
                throw ValidationException::withMessages([
                    'revocation_note' => 'Dieser Antrag kann in seinem aktuellen Status nicht entzogen werden.',
                ]);
            }

            $previousStatus = $locked->status;
            $locked->update([
                'status' => AccessRequestModel::STATUS_REVOKED,
                'revoked_by_user_id' => $request->user()->id,
                'revoked_at' => now(),
                'revocation_note' => $validated['revocation_note'],
            ]);

            $this->recordEvent(
                $locked,
                $request->user(),
                'revoked',
                $previousStatus,
                AccessRequestModel::STATUS_REVOKED,
                $validated['revocation_note']
            );
        });

        return back()->with('success', 'Der Entzug wurde dokumentiert. Die technische Sperrung muss manuell ausgefuehrt werden.');
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission), 403);
    }

    private function recordEvent(
        AccessRequestModel $accessRequest,
        User $actor,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $comment = null,
        ?array $context = null
    ): void {
        AccessRequestEvent::create([
            'access_request_id' => $accessRequest->id,
            'actor_user_id' => $actor->id,
            'actor_name' => $this->userName($actor),
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'comment' => $comment,
            'context' => $context,
            'created_at' => now(),
        ]);
    }

    private function userName(User $user): string
    {
        $user->loadMissing('person:id,vorname,nachname');
        $personName = trim(($user->person?->vorname ?? '').' '.($user->person?->nachname ?? ''));

        return $personName !== '' ? $personName : ($user->username ?: $user->email);
    }
}
