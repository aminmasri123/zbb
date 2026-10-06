<?php

namespace App\Services\Bop;

use App\Models\Projekt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ParticipantAreaReport
{
    public function build(Projekt $project, string $type): array
    {
        $entries = DB::table('personen_ist_schuelers as students')
            ->join('partners', 'partners.id', '=', 'students.schule_id')
            ->join('projekt_has_partners as schools', function ($join) use ($project) {
                $join->on('schools.partner_id', '=', 'partners.id')->where('schools.projekt_id', $project->id);
            })
            ->join('gruppe_has_personens as attendance', 'attendance.personen_id', '=', 'students.person_id')
            ->join('gruppes', 'gruppes.id', '=', 'attendance.gruppe_id')
            ->where('gruppes.projekt_id', $project->id)
            ->select('students.person_id', 'students.schule_id', 'partners.name', 'students.schuljahr', 'gruppes.bereich_id', 'gruppes.anfangsdatum')
            ->distinct()->get();
        $years = $entries->map(fn ($entry) => (int) substr($entry->schuljahr, 0, 4))->filter()->unique()->sortDesc()->values();
        $areas = $project->bereiche()->orderBy('bereiches.id')->get(['bereiches.id', 'bereiches.name', 'bereiches.code']);
        $schools = [];
        $counts = [];
        foreach ($entries as $entry) {
            $year = (int) substr($entry->schuljahr, 0, 4);
            $date = Carbon::parse($entry->anfangsdatum);
            $end = $type === 'ende' ? ($year + 1).'-08-31' : $year.'-12-31';
            if (! $date->betweenIncluded(Carbon::parse($year.'-01-01'), Carbon::parse($end)->endOfDay())) continue;
            $schools[$entry->schule_id] = ['id' => $entry->schule_id, 'name' => $entry->name];
            $counts[$entry->schule_id][$entry->bereich_id][$year][$entry->person_id] = true;
        }
        ksort($schools);

        return ['type' => $type, 'years' => $years, 'areas' => $areas, 'schools' => collect($schools)->map(function ($school) use ($counts) {
            $school['counts'] = collect($counts[$school['id']])->map(fn ($years) => collect($years)->map(fn ($people) => count($people)));
            return $school;
        })->values()];
    }
}
