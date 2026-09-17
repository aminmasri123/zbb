<?php

namespace App\Services\Bop;

use App\Models\BereichsauswahlSetting;
use App\Models\Projekt;
use Illuminate\Support\Collection;

class SelectableAreas
{
    public function eligible(Projekt $project): Collection
    {
        return $project->bereiche->reject(function ($area) {
            foreach ([$area->name, $area->code] as $label) {
                $key = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $label));
                if (in_array($key, ['pa', 'potenzialanalyse', 'potentialanalyse', 'rolltag'], true)) {
                    return true;
                }
            }

            return false;
        })->sortBy('name')->values();
    }

    public function forSetting(Projekt $project, ?BereichsauswahlSetting $setting): Collection
    {
        $areas = $this->eligible($project);

        // NULL retains the project defaults; an explicit list belongs to this school context.
        return $setting?->bereich_ids === null
            ? $areas
            : $areas->whereIn('id', $setting->bereich_ids)->values();
    }

    public function forContext(Projekt $project, int $partnerId, string $year, string $part): Collection
    {
        $setting = BereichsauswahlSetting::query()
            ->forContext($project->id, $partnerId, $year, $part)
            ->preferConfigured()->first();

        return $this->forSetting($project, $setting);
    }

    public function selectionWarnings(Collection $students, Collection $areas): array
    {
        $allowed = $areas->pluck('id');

        return $students->filter(fn ($student) => collect(range(1, 4))->contains(
            fn ($field) => ($id = $student->bereichsauswahl?->{'bereich_id'.$field}) && ! $allowed->contains($id)
        ))->map(fn ($student) => [
            'id' => $student->id,
            'name' => trim(($student->person?->nachname ?? '').', '.($student->person?->vorname ?? ''), ' ,'),
        ])->values()->all();
    }
}
