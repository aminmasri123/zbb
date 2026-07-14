<?php

namespace App\Console\Commands;

use App\Services\Legacy\BopImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportBopDatabase extends Command
{
    protected $signature = 'bop:import
        {--execute : Daten tatsaechlich in ZBB schreiben}
        {--force : Sicherheitsabfrage im Execute-Modus ueberspringen}';

    protected $description = 'Analysiert oder importiert BOP-Stammdaten kontrolliert und idempotent nach ZBB';

    public function handle(BopImportService $service): int
    {
        try {
            $inspection = $service->inspect();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Pruefung', 'Wert'], [
            ['Quelldatenbank', $inspection['source_database']],
            ['Schulen', $inspection['schools']],
            ['Teilnehmer', $inspection['participants']],
            ['Bereiche', $inspection['areas']],
            ['Gruppen', $inspection['groups']],
            ['Gruppenzuordnungen', $inspection['group_memberships']],
            ['Anwesenheitszeilen rekonstruierbar', $inspection['attendance_rows_reconstructable']],
            ['Anwesenheitswerte ausserhalb Gruppenzeitraum', $inspection['attendance_conflicts']],
            ['Dubletten-Kandidaten', $inspection['participant_duplicates']],
            ['Standort vorhanden', $inspection['location_exists'] ? 'ja' : 'nein (wird angelegt)'],
            ['Partnerschaftstyp vorhanden', $inspection['partnership_type_exists'] ? 'ja' : 'nein'],
            ['Zielprojekt vorhanden', $inspection['project_exists'] ? 'ja' : 'nein'],
        ]);

        if (! $this->option('execute')) {
            $this->info('Dry-Run abgeschlossen. Es wurden keine Daten veraendert.');
            $this->line('Zum Import: php artisan bop:import --execute');

            return self::SUCCESS;
        }

        if ($inspection['attendance_conflicts'] > 0) {
            $this->warn("{$inspection['attendance_conflicts']} Anwesenheitspositionen liegen ausserhalb des Gruppenzeitraums. Sie werden nicht als datierte Anwesenheit angelegt, bleiben aber im Legacy-Snapshot erhalten.");
        }

        if (! $this->option('force') && ! $this->confirm('BOP-Stammdaten jetzt nach ZBB importieren?')) {
            $this->warn('Import abgebrochen.');

            return self::SUCCESS;
        }

        try {
            $summary = $service->import();
        } catch (Throwable $exception) {
            $this->error('Import fehlgeschlagen: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Importlauf {$summary['run_id']} erfolgreich abgeschlossen.");
        $this->line("Schulen verarbeitet: {$summary['schools_imported']}");
        $this->line("Schul-Ansprechpartner verarbeitet: {$summary['school_contacts_imported']}");
        $this->line("Teilnehmer verarbeitet: {$summary['participants_imported']}");
        $this->line("Bereiche verarbeitet: {$summary['areas_imported']}");
        $this->line("Gruppen verarbeitet: {$summary['groups_imported']}");
        $this->line("Anwesenheitszeilen verarbeitet: {$summary['attendance_rows_imported']}");
        $this->line("Anwesenheitskonflikte: {$summary['attendance_conflicts']}");
        $this->line("Bereichswahlen verarbeitet: {$summary['selections_imported']}");
        $this->line("Bereichseinteilungen verarbeitet: {$summary['assignments_imported']}");
        $this->line("PA-Kompetenzwerte verarbeitet: {$summary['pa_ratings_imported']}");
        $this->line("PA-Uebungsergebnisse verarbeitet: {$summary['pa_exercise_results_imported']}");
        $this->line("BO-Bewertungen verarbeitet: {$summary['bo_ratings_imported']}");

        return self::SUCCESS;
    }
}
