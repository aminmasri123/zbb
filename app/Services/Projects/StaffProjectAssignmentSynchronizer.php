<?php

namespace App\Services\Projects;

use App\Models\Personen;
use App\Models\ProjektHasPersonen;
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
                    $matches->each(function (ProjektHasPersonen $assignment) use ($keptIds) {
                        if ($assignment->status !== 'aktiv') {
                            $assignment->update(['status' => 'aktiv']);
                        }

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

                $keptIds->push($created->id);
            }

            $obsolete = ProjektHasPersonen::query()->where('personen_id', $person->id);

            if ($keptIds->isNotEmpty()) {
                $obsolete->whereNotIn('id', $keptIds->all());
            }

            $obsolete->get()->each->delete();
        });
    }

    private function desiredAssignments(array $assignments): Collection
    {
        return collect($assignments)
            ->flatMap(function (array $assignment) {
                $projectId = isset($assignment['projekt_id']) ? (int) $assignment['projekt_id'] : 0;

                return collect($assignment['standort_ids'] ?? [])
                    ->filter(fn ($locationId) => $projectId > 0 && (int) $locationId > 0)
                    ->map(fn ($locationId) => [
                        'projekt_id' => $projectId,
                        'standort_id' => (int) $locationId,
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

    private function key(int $projectId, ?int $locationId): string
    {
        return $projectId . ':' . ($locationId ?? 'null');
    }
}
