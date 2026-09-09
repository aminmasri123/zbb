<?php

namespace App\Console\Commands;

use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Services\Participants\ImportedSchoolQualification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackfillImportedSchoolQualifications extends Command
{
    protected $signature = 'participants:backfill-school-qualifications {project} {--apply}';
    protected $description = 'Übernimmt vorhandene BA-Importangaben in Schule/Beruf; standardmäßig nur Vorschau.';

    public function handle(ImportedSchoolQualification $importer): int
    {
        $project = Projekt::findOrFail($this->argument('project'));
        $rows = ProjektHasPersonen::where('projekt_id', $project->id)->get()
            ->filter(fn ($row) => filled($row->import_entry_data['school_qualification_at_entry'] ?? null));
        $this->info($project->name.': '.$rows->count().' Importangaben');
        foreach ($rows->groupBy(fn ($row) => $row->import_entry_data['school_qualification_at_entry']) as $label => $group) {
            $this->line($label.': '.$group->count());
        }
        if (! $this->option('apply')) {
            return self::SUCCESS;
        }

        $backup = 'private/backups/school-qualifications-'.$project->id.'-'.now()->format('Ymd-His-u').'.json';
        $snapshot = [
            'qualifications' => DB::table('abschluesses')->get(),
            'participant_qualifications' => DB::table('personen_has_abschluesses')->whereIn('person_id', $rows->pluck('personen_id'))->get(),
            'import_entries' => $rows->map->only(['id', 'personen_id', 'import_entry_data'])->values(),
        ];
        if (! Storage::disk('local')->put($backup, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
            $this->error('Sicherung fehlgeschlagen. Keine Änderungen vorgenommen.');
            return self::FAILURE;
        }
        $created = DB::transaction(function () use ($rows, $importer) {
            $count = 0;
            foreach ($rows as $row) {
                $count += (int) $importer->store($row->personen_id, $row->import_entry_data['school_qualification_at_entry']);
            }
            return $count;
        });
        $this->info('Ergänzt: '.$created.'; bereits vorhanden oder nicht übernehmbar: '.($rows->count() - $created));
        $this->line('Sicherung: '.Storage::disk('local')->path($backup));
        return self::SUCCESS;
    }
}
