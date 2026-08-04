<?php

namespace App\Services\Projects;

use App\Models\BereichHasPersonen;
use App\Models\Personen;
use App\Models\ProjektHasPersonen;
use App\Models\RaumHasPersonen;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StaffProjectAssignmentSynchronizer
{
    public function sync(Personen $person, array $assignments): void
    {
        if ($person->typ !== 'mitarbeiter') {
            throw new InvalidArgumentException('Projekt-/Standortzuweisungen dieses Dienstes sind nur fuer Mitarbeitende bestimmt.');
        }

        $desired = $this->desiredAssignments($assignments);

        DB::transaction(function () use ($person, $desired) {
            $existing = ProjektHasPersonen::query()
                ->where('personen_id', $person->id)
                ->get()
                ->groupBy(fn (ProjektHasPersonen $assignment) => $this->key(
                    $assignment->projekt_id,
                    $assignment->standort_id
                ));

            $keptIds = collect();

            foreach ($desired as $item) {
                $matches = $existing->get($item['key'], collect());

                if ($matches->isNotEmpty()) {
                    $matches->each(function (ProjektHasPersonen $assignment) use ($keptIds, $item) {
                        if ($assignment->status !== 'aktiv') {
                            $assignment->update(['status' => 'aktiv']);
                        }

                        $this->syncBereicheForAssignment(
                            $assignment,
                            $item['bereich_ids'],
                            $item['default_bereich_id'],
                        );

                        $this->syncRoomAssignmentsForAssignment($assignment, $item['room_assignments']);
                        $keptIds->push($assignment->id);
                    });

                    continue;
                }

                $created = ProjektHasPersonen::query()->create([
                    'personen_id' => $person->id,
                    'projekt_id' => $item['projekt_id'],
                    'standort_id' => $item['standort_id'],
                    'status' => 'aktiv',
                ]);

                $this->syncBereicheForAssignment(
                    $created,
                    $item['bereich_ids'],
                    $item['default_bereich_id'],
                );

                $this->syncRoomAssignmentsForAssignment($created, $item['room_assignments']);
                $keptIds->push($created->id);
            }

            $obsolete = ProjektHasPersonen::query()->where('personen_id', $person->id);

            if ($keptIds->isNotEmpty()) {
                $obsolete->whereNotIn('id', $keptIds->all());
            }

            $obsolete->get()->each->delete();
        });
    }

    public function syncBereicheForAssignment(ProjektHasPersonen $assignment, array $bereichIds, ?int $defaultBereichId): void
    {
        $payload = $this->normalizeBereichIdsForProject(
            (int) $assignment->projekt_id,
            $bereichIds,
            $defaultBereichId,
        );

        if (empty($payload['ids'])) {
            BereichHasPersonen::query()
                ->where('projekt_has_personen_id', $assignment->id)
                ->delete();

            return;
        }

        BereichHasPersonen::query()
            ->where('projekt_has_personen_id', $assignment->id)
            ->whereNotIn('bereich_id', $payload['ids'])
            ->delete();

        foreach ($payload['ids'] as $bereichId) {
            BereichHasPersonen::query()->updateOrCreate(
                [
                    'projekt_has_personen_id' => $assignment->id,
                    'bereich_id' => $bereichId,
                ],
                [
                    'is_default' => $payload['default_id'] !== null
                        && (int) $bereichId === (int) $payload['default_id'],
                ],
            );
        }
    }

    public function syncRaeumeForAssignment(ProjektHasPersonen $assignment, string $assignmentType, array $raumIds, ?int $defaultRaumId): void
    {
        if (! in_array($assignmentType, RaumHasPersonen::assignmentTypes(), true)) {
            throw new InvalidArgumentException('Unbekannte Raum-Zuweisungsart.');
        }

        $payload = $this->normalizeRaumIdsForProject(
            (int) $assignment->projekt_id,
            $raumIds,
            $defaultRaumId,
        );

        if (empty($payload['ids'])) {
            RaumHasPersonen::query()
                ->where('projekt_has_personen_id', $assignment->id)
                ->where('assignment_type', $assignmentType)
                ->delete();

            return;
        }

        RaumHasPersonen::query()
            ->where('projekt_has_personen_id', $assignment->id)
            ->where('assignment_type', $assignmentType)
            ->whereNotIn('raum_id', $payload['ids'])
            ->delete();

        foreach ($payload['ids'] as $raumId) {
            RaumHasPersonen::query()->updateOrCreate(
                [
                    'projekt_has_personen_id' => $assignment->id,
                    'raum_id' => $raumId,
                    'assignment_type' => $assignmentType,
                ],
                [
                    'is_default' => $payload['default_id'] !== null
                        && (int) $raumId === (int) $payload['default_id'],
                ],
            );
        }
    }

    private function desiredAssignments(array $assignments): Collection
    {
        return collect($assignments)
            ->flatMap(function (array $assignment) {
                $projectId = isset($assignment['projekt_id']) ? (int) $assignment['projekt_id'] : 0;
                $bereiche = $this->areaPayloadForProject($projectId, $assignment);
                $roomAssignments = [
                    RaumHasPersonen::TYPE_BUERO => $this->roomPayloadForProject(
                        $projectId,
                        $assignment,
                        RaumHasPersonen::TYPE_BUERO,
                    ),
                    RaumHasPersonen::TYPE_ARBEITSBEREICH => $this->roomPayloadForProject(
                        $projectId,
                        $assignment,
                        RaumHasPersonen::TYPE_ARBEITSBEREICH,
                    ),
                ];

                return collect($assignment['standort_ids'] ?? [])
                    ->filter(fn ($locationId) => $projectId > 0 && (int) $locationId > 0)
                    ->map(fn ($locationId) => [
                        'projekt_id' => $projectId,
                        'standort_id' => (int) $locationId,
                        'bereich_ids' => $bereiche['ids'],
                        'default_bereich_id' => $bereiche['default_id'],
                        'room_assignments' => $roomAssignments,
                    ]);
            })
            ->unique(fn (array $assignment) => $this->key(
                $assignment['projekt_id'],
                $assignment['standort_id']
            ))
            ->map(fn (array $assignment) => [
                ...$assignment,
                'key' => $this->key($assignment['projekt_id'], $assignment['standort_id']),
            ])
            ->values();
    }

    private function areaPayloadForProject(int $projectId, array $assignment): array
    {
        $bereichIds = collect($assignment['bereich_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $defaultBereichId = isset($assignment['default_bereich_id'])
            ? (int) $assignment['default_bereich_id']
            : null;

        if ($defaultBereichId && ! $bereichIds->contains($defaultBereichId)) {
            $bereichIds->push($defaultBereichId);
        }

        return $this->normalizeBereichIdsForProject($projectId, $bereichIds->all(), $defaultBereichId);
    }

    private function roomPayloadForProject(int $projectId, array $assignment, string $assignmentType): array
    {
        $idsKey = $assignmentType === RaumHasPersonen::TYPE_BUERO
            ? 'buero_raum_ids'
            : 'arbeitsbereich_raum_ids';
        $defaultKey = $assignmentType === RaumHasPersonen::TYPE_BUERO
            ? 'default_buero_raum_id'
            : 'default_arbeitsbereich_raum_id';

        $raumIds = collect($assignment[$idsKey] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $defaultRaumId = isset($assignment[$defaultKey])
            ? (int) $assignment[$defaultKey]
            : null;

        if ($defaultRaumId && ! $raumIds->contains($defaultRaumId)) {
            $raumIds->push($defaultRaumId);
        }

        return $this->normalizeRaumIdsForProject($projectId, $raumIds->all(), $defaultRaumId);
    }

    private function normalizeBereichIdsForProject(int $projectId, array $bereichIds, ?int $defaultBereichId): array
    {
        if ($projectId <= 0) {
            return ['ids' => [], 'default_id' => null];
        }

        $allowedIds = DB::table('projekt_has_bereiches')
            ->where('projekt_id', $projectId)
            ->pluck('bereich_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $ids = collect($bereichIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $allowedIds->contains($id))
            ->unique()
            ->values();

        $defaultId = $defaultBereichId && $ids->contains((int) $defaultBereichId)
            ? (int) $defaultBereichId
            : null;

        return [
            'ids' => $ids->all(),
            'default_id' => $defaultId,
        ];
    }

    private function normalizeRaumIdsForProject(int $projectId, array $raumIds, ?int $defaultRaumId): array
    {
        if ($projectId <= 0) {
            return ['ids' => [], 'default_id' => null];
        }

        $allowedIds = DB::table('projekt_has_raeumes')
            ->where('projekt_id', $projectId)
            ->pluck('raum_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $ids = collect($raumIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $allowedIds->contains($id))
            ->unique()
            ->values();

        $defaultId = $defaultRaumId && $ids->contains((int) $defaultRaumId)
            ? (int) $defaultRaumId
            : null;

        return [
            'ids' => $ids->all(),
            'default_id' => $defaultId,
        ];
    }

    private function syncRoomAssignmentsForAssignment(ProjektHasPersonen $assignment, array $roomAssignments): void
    {
        foreach (RaumHasPersonen::assignmentTypes() as $assignmentType) {
            $payload = $roomAssignments[$assignmentType] ?? ['ids' => [], 'default_id' => null];

            $this->syncRaeumeForAssignment(
                $assignment,
                $assignmentType,
                $payload['ids'] ?? [],
                $payload['default_id'] ?? null,
            );
        }
    }

    private function key(int $projectId, ?int $locationId): string
    {
        return $projectId . ':' . ($locationId ?? 'null');
    }
}
