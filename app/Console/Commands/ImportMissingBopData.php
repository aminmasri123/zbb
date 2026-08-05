<?php

namespace App\Console\Commands;

use App\Services\Legacy\BopImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportMissingBopData extends Command
{
    protected $signature = 'bop:import-missing
        {--execute : Fehlende Legacy-Daten tatsaechlich in ZBB schreiben}
        {--archive-only : Nur das vollstaendige Legacy-Archiv fuellen, keine fachlichen Backfills ausfuehren}
        {--force : Sicherheitsabfrage im Execute-Modus ueberspringen}';

    protected $description = 'Archiviert alle BOP-Legacy-Tabellen und fuellt fehlende fachliche Matrix-Daten nach';

    public function handle(BopImportService $service): int
    {
        try {
            $inspection = $service->inspectMissingData();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('BOP-Legacy-Datenbank: '.$inspection['source_database']);
        $this->line('Legacy-Zeilen gesamt: '.$inspection['source_rows']);
        $this->line('Bereits archiviert: '.$inspection['archived_rows']);
        $this->line('Noch nicht archiviert: '.$inspection['missing_rows']);

        $missingTables = collect($inspection['tables'])
            ->filter(fn (array $row) => $row['missing_rows'] > 0)
            ->sortByDesc('missing_rows')
            ->values();

        if ($missingTables->isNotEmpty()) {
            $this->table(
                ['Tabelle', 'Quelle', 'Archiviert', 'Fehlt'],
                $missingTables->map(fn (array $row) => [
                    $row['table'],
                    $row['source_rows'],
                    $row['archived_rows'],
                    $row['missing_rows'],
                ])->all()
            );
        } else {
            $this->line('Alle Legacy-Tabellen sind bereits vollstaendig archiviert.');
        }

        if (! $this->option('execute')) {
            $this->info('Dry-Run abgeschlossen. Es wurden keine Daten veraendert.');
            $this->line('Zum Nachimport: php artisan bop:import-missing --execute');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Fehlende BOP-Daten jetzt nach ZBB importieren?')) {
            $this->warn('Nachimport abgebrochen.');

            return self::SUCCESS;
        }

        try {
            $summary = $service->importMissingData((bool) $this->option('archive-only'));
        } catch (Throwable $exception) {
            $this->error('Nachimport fehlgeschlagen: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Nachimport {$summary['run_id']} erfolgreich abgeschlossen.");
        $this->line("Legacy-Snapshots neu: {$summary['legacy_snapshots_created']}");
        $this->line("Legacy-Snapshots aktualisiert: {$summary['legacy_snapshots_updated']}");

        if (! $this->option('archive-only')) {
            $this->line("Teilnehmer-Schulzeilen aktualisiert: {$summary['participant_school_rows_updated']}");
            $this->line("Teilnehmer-Adressen importiert/ergaenzt: {$summary['participant_addresses_imported']}");
            $this->line("PA-Zusammenfassungen importiert: {$summary['pa_summaries_imported']}");
            $this->line("PA-Zusammenfassungen uebersprungen: {$summary['pa_summaries_skipped']}");
        }

        return self::SUCCESS;
    }
}
