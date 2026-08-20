<?php

namespace App\Http\Controllers;

use App\Models\AccessDoor;
use App\Models\AccessFloorPlan;
use App\Models\AccessProfile;
use App\Models\AccessRequest as AccessRequestModel;
use App\Models\AccessRequestEvent;
use App\Models\Personen;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AccessManagementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canManageMasterData = $user->hasStoredPermission('zutritt.stammdaten.manage');
        $canProcessAll = $canManageMasterData
            || $user->hasStoredPermission('zutritt.antrag.approve')
            || $user->hasStoredPermission('zutritt.aktivierung.update');

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

        $floorPlans = $canManageMasterData
            ? AccessFloorPlan::query()
                ->with([
                    'standort:id,name',
                    'roomPlacements.room:id,standort_id,name,raumnummer,etage',
                    'doorPlacements.door:id,standort_id,room_from_id,room_to_id,name,code',
                ])
                ->orderBy('standort_id')
                ->orderBy('floor_label')
                ->get()
                ->map(fn (AccessFloorPlan $floorPlan) => [
                    'id' => $floorPlan->id,
                    'standort_id' => $floorPlan->standort_id,
                    'standort' => $floorPlan->standort,
                    'floor_label' => $floorPlan->floor_label,
                    'name' => $floorPlan->name,
                    'image_url' => route('zutritt.grundrisse.image', $floorPlan),
                    'image_width' => $floorPlan->image_width,
                    'image_height' => $floorPlan->image_height,
                    'rooms' => $floorPlan->roomPlacements->map(fn ($placement) => [
                        'room_id' => $placement->raum_id,
                        'x_percent' => $placement->x_percent,
                        'y_percent' => $placement->y_percent,
                        'width_percent' => $placement->width_percent,
                        'height_percent' => $placement->height_percent,
                        'rotation_degrees' => $placement->rotation_degrees,
                        'room' => $placement->room,
                    ])->values(),
                    'doors' => $floorPlan->doorPlacements->map(fn ($placement) => [
                        'door_id' => $placement->access_door_id,
                        'x_percent' => $placement->x_percent,
                        'y_percent' => $placement->y_percent,
                        'rotation_degrees' => $placement->rotation_degrees,
                        'door' => $placement->door,
                    ])->values(),
                ])->values()
            : collect();

        return Inertia::render('AccessManagement/Index', [
            'accessRequests' => $requests,
            'profiles' => $profiles,
            'floorPlans' => $floorPlans,
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
                ? Raeume::query()->orderBy('etage')->orderBy('raumnummer')->orderBy('name')->get(['id', 'standort_id', 'name', 'raumnummer', 'etage'])
                : [],
            'currentUserId' => $user->id,
            'accessPermissions' => [
                'canCreateRequest' => $user->hasStoredPermission('zutritt.antrag.store'),
                'canApprove' => $user->hasStoredPermission('zutritt.antrag.approve'),
                'canActivate' => $user->hasStoredPermission('zutritt.aktivierung.update'),
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

    public function storeFloorPlan(Request $request)
    {
        $this->authorizePermission($request, 'zutritt.stammdaten.manage');

        $request->merge([
            'floor_label' => trim((string) $request->input('floor_label')),
            'name' => trim((string) $request->input('name')),
        ]);

        $validated = $request->validate([
            'standort_id' => ['required', 'integer', 'exists:standorts,id'],
            'floor_label' => [
                'required',
                'string',
                'max:80',
                Rule::unique('access_floor_plans')->where(fn ($query) => $query
                    ->where('standort_id', $request->integer('standort_id'))),
            ],
            'name' => ['required', 'string', 'max:160'],
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'floor_label.unique' => 'Für diesen Standort und diese Etage existiert bereits ein Grundriss.',
        ]);

        $file = $request->file('image');
        $dimensions = @getimagesize($file->getRealPath()) ?: null;
        $path = $file->store('access-management/floor-plans', 'local');

        if (! $path) {
            throw ValidationException::withMessages([
                'image' => 'Der Grundriss konnte nicht gespeichert werden.',
            ]);
        }

        try {
            AccessFloorPlan::create([
                'standort_id' => $validated['standort_id'],
                'floor_label' => trim($validated['floor_label']),
                'name' => trim($validated['name']),
                'image_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'image_width' => $dimensions[0] ?? null,
                'image_height' => $dimensions[1] ?? null,
                'active' => true,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', '2D-Grundriss wurde angelegt. Räume und Türen können jetzt platziert werden.');
    }

    public function floorPlanImage(Request $request, AccessFloorPlan $accessFloorPlan)
    {
        $this->authorizePermission($request, 'zutritt.stammdaten.manage');
        abort_unless(Storage::disk('local')->exists($accessFloorPlan->image_path), 404);

        return Storage::disk('local')->response(
            $accessFloorPlan->image_path,
            $accessFloorPlan->original_name,
            [
                'Content-Type' => $accessFloorPlan->mime_type,
                'Content-Disposition' => 'inline; filename="grundriss-'.$accessFloorPlan->id.'"',
                'Cache-Control' => 'private, max-age=300',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function updateFloorPlanLayout(Request $request, AccessFloorPlan $accessFloorPlan)
    {
        $this->authorizePermission($request, 'zutritt.stammdaten.manage');

        $validated = $request->validate([
            'rooms' => ['present', 'array'],
            'rooms.*.room_id' => ['required', 'integer', 'distinct', 'exists:raeumes,id'],
            'rooms.*.x_percent' => ['required', 'numeric'],
            'rooms.*.y_percent' => ['required', 'numeric'],
            'rooms.*.width_percent' => ['required', 'numeric', 'min:2', 'max:100'],
            'rooms.*.height_percent' => ['required', 'numeric', 'min:2', 'max:100'],
            'rooms.*.rotation_degrees' => ['required', 'numeric', 'min:0', 'max:359.99'],
            'doors' => ['present', 'array'],
            'doors.*.door_id' => ['required', 'integer', 'distinct', 'exists:access_doors,id'],
            'doors.*.x_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'doors.*.y_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'doors.*.rotation_degrees' => ['required', 'numeric', 'min:0', 'max:359.99'],
        ]);

        $roomIds = collect($validated['rooms'])->pluck('room_id')->map(fn ($id) => (int) $id);
        $validRoomCount = Raeume::query()
            ->where('standort_id', $accessFloorPlan->standort_id)
            ->where(function ($query) use ($accessFloorPlan) {
                $query->whereNull('etage')
                    ->orWhere('etage', $accessFloorPlan->floor_label);
            })
            ->whereIn('id', $roomIds)
            ->count();

        if ($validRoomCount !== $roomIds->count()) {
            throw ValidationException::withMessages([
                'rooms' => 'Alle Räume müssen zum Standort und zur Etage des Grundrisses gehören.',
            ]);
        }

        $heightToWidthRatio = ($accessFloorPlan->image_width && $accessFloorPlan->image_height)
            ? $accessFloorPlan->image_height / $accessFloorPlan->image_width
            : 1;

        foreach ($validated['rooms'] as $index => $room) {
            $width = (float) $room['width_percent'];
            $height = (float) $room['height_percent'];
            $radians = deg2rad((float) $room['rotation_degrees']);
            $absoluteCosine = abs(cos($radians));
            $absoluteSine = abs(sin($radians));
            $rotatedWidth = $absoluteCosine * $width + $absoluteSine * $height * $heightToWidthRatio;
            $rotatedHeight = $absoluteSine * $width / $heightToWidthRatio + $absoluteCosine * $height;
            $centerX = (float) $room['x_percent'] + $width / 2;
            $centerY = (float) $room['y_percent'] + $height / 2;
            $tolerance = 0.01;

            if ($centerX - $rotatedWidth / 2 < -$tolerance
                || $centerX + $rotatedWidth / 2 > 100 + $tolerance
                || $centerY - $rotatedHeight / 2 < -$tolerance
                || $centerY + $rotatedHeight / 2 > 100 + $tolerance) {
                throw ValidationException::withMessages([
                    "rooms.$index" => 'Die gedrehte Raumfläche muss vollständig innerhalb des Grundrisses liegen.',
                ]);
            }
        }

        $doorIds = collect($validated['doors'])->pluck('door_id')->map(fn ($id) => (int) $id);
        $validDoorCount = AccessDoor::query()
            ->where('standort_id', $accessFloorPlan->standort_id)
            ->whereIn('id', $doorIds)
            ->count();

        if ($validDoorCount !== $doorIds->count()) {
            throw ValidationException::withMessages([
                'doors' => 'Alle Türen auf dem Plan müssen zum Standort des Grundrisses gehören.',
            ]);
        }

        DB::transaction(function () use ($accessFloorPlan, $validated) {
            $now = now();
            $accessFloorPlan->roomPlacements()->delete();
            $accessFloorPlan->doorPlacements()->delete();

            if ($validated['rooms'] !== []) {
                DB::table('access_floor_plan_rooms')->insert(collect($validated['rooms'])->map(fn ($room) => [
                    'access_floor_plan_id' => $accessFloorPlan->id,
                    'raum_id' => $room['room_id'],
                    'x_percent' => $room['x_percent'],
                    'y_percent' => $room['y_percent'],
                    'width_percent' => $room['width_percent'],
                    'height_percent' => $room['height_percent'],
                    'rotation_degrees' => $room['rotation_degrees'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }

            if ($validated['doors'] !== []) {
                DB::table('access_floor_plan_doors')->insert(collect($validated['doors'])->map(fn ($door) => [
                    'access_floor_plan_id' => $accessFloorPlan->id,
                    'access_door_id' => $door['door_id'],
                    'x_percent' => $door['x_percent'],
                    'y_percent' => $door['y_percent'],
                    'rotation_degrees' => $door['rotation_degrees'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }
        });

        return back()->with('success', '2D-Anordnung wurde gespeichert.');
    }

    public function destroyFloorPlan(Request $request, AccessFloorPlan $accessFloorPlan)
    {
        $this->authorizePermission($request, 'zutritt.stammdaten.manage');

        $path = $accessFloorPlan->image_path;
        $accessFloorPlan->delete();
        Storage::disk('local')->delete($path);

        return back()->with('success', '2D-Grundriss wurde entfernt. Räume und Türen bleiben erhalten.');
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
        $canRequestForOthers = $user->hasStoredPermission('zutritt.stammdaten.manage')
            || $user->hasStoredPermission('zutritt.antrag.approve')
            || $user->hasStoredPermission('zutritt.aktivierung.update');

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
        abort_unless($request->user()?->hasStoredPermission($permission), 403);
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
