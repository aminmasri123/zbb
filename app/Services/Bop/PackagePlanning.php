<?php

namespace App\Services\Bop;

use App\Models\BopRun;
use App\Models\PersonenIstSchueler;

class PackagePlanning
{
    public function phase(int $project, int $school, string $year, string $part, string $type)
    {
        $normalized = trim(preg_replace('/^Teil\s*/i', '', $part));
        $run = BopRun::with('phases.participants')->where('projekt_id', $project)
            ->where('partner_id', $school)->forSchuljahr($year)
            ->whereIn('teil', [$part, $normalized, 'Teil '.$normalized, '_all'])
            ->orderByRaw('CASE WHEN teil = ? THEN 0 WHEN teil = ? THEN 2 ELSE 1 END', [$part, '_all'])
            ->first();

        return $run?->phases->firstWhere('phase_type', $type);
    }

    public function dates($phase, int $project, int $school, string $year, string $part): array
    {
        if (!$phase) return [];
        $dates = collect($phase->dates ?? []);
        $assignments = collect($phase->part_date_assignments ?? [])
            ->mapWithKeys(fn ($dates, $key) => [trim(preg_replace('/^Teil\s*/i', '', (string) $key)) => $dates]);
        if ($assignments->isNotEmpty()) {
            $dates = $dates->intersect($assignments->get(trim(preg_replace('/^Teil\s*/i', '', $part)), []));
        }
        if (!empty($phase->class_date_assignments)) {
            $classes = PersonenIstSchueler::forExportProject($project)->filterSchueler($school, $year, $part)->pluck('klasse');
            $dates = $dates->intersect($classes->flatMap(fn ($class) => $phase->class_date_assignments[$class] ?? []));
        }

        return $dates->filter()->unique()->sort()->values()->all();
    }

    public function includes($phase, $student, ?string $date): bool
    {
        if (!$phase) return true;
        if ($phase->scope_type === 'participants' && !$phase->participants->contains('personen_ist_schueler_id', $student->id)) return false;
        if ($phase->scope_type === 'classes' && !in_array($student->klasse, $phase->selected_classes ?? [], true)) return false;
        return !$date || empty($phase->class_date_assignments)
            || in_array($date, $phase->class_date_assignments[$student->klasse] ?? [], true);
    }
}
