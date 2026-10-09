<?php

namespace App\Services\Bop;

use App\Models\GruppeHasPersonen;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SchoolAttendance
{
    public const DAYS = ['vorb', 'pa1', 'pa2', 'rolltag', 'bo1', 'bo2', 'bo3', 'bo4', 'bo5', 'bo6', 'bo7', 'bo8', 'bo9'];

    public function load(int $projectId, Collection $students): array
    {
        $stored = DB::table('bop_school_attendance')->where('projekt_id', $projectId)
            ->whereIn('student_id', $students->pluck('id'))->pluck('statuses', 'student_id');
        return $students->mapWithKeys(fn ($student) => [$student->id => array_replace(
            array_fill_keys(self::DAYS, 'absent'), json_decode($stored[$student->id] ?? '{}', true)
        )])->all();
    }

    public function save(int $projectId, array $statuses): void
    {
        DB::transaction(function () use ($projectId, $statuses) {
            foreach ($statuses as $studentId => $days) {
                DB::table('bop_school_attendance')->updateOrInsert(
                    ['projekt_id' => $projectId, 'student_id' => $studentId],
                    ['statuses' => json_encode($days), 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });
    }

    public function generate(int $projectId, int $schoolId, Collection $students): array
    {
        $entries = GruppeHasPersonen::with('gruppe.bereich')->whereIn('personen_id', $students->pluck('person_id'))
            ->whereHas('gruppe', fn ($query) => $query->where('projekt_id', $projectId)->where('partner_id', $schoolId))
            ->get()->groupBy('personen_id');
        $statuses = $this->load($projectId, $students);
        foreach ($students as $student) {
            if (! isset($entries[$student->person_id])) continue;
            // BOP: Werkstatttage und Rolltag werden für die Abrechnung vorbelegt.
            $days = array_fill_keys(self::DAYS, 'present');
            $days['vorb'] = $days['pa1'] = $days['pa2'] = 'absent';
            $paEntries = ($entries[$student->person_id] ?? collect())->filter(fn ($entry) => $entry->gruppe->isPotentialAnalysisGroup());
            $dates = $paEntries->filter(fn ($entry) => $entry->anwesenheitsstatuten_id && !preg_match('/fehl|krank|abwes|unentsch|entschuld/i', (string) $entry->status?->status))
                ->pluck('tag.datum')->filter()->unique()->sort()->values();
            $days['vorb'] = $dates->isNotEmpty() ? 'present' : 'absent';
            $days['pa1'] = $days['pa2'] = $dates->isNotEmpty() ? 'present' : 'absent';
            $statuses[$student->id] = $days;
        }
        $this->save($projectId, $statuses);
        return $statuses;
    }
}
