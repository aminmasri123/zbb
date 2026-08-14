<?php

namespace Tests\Feature;

use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ParticipantAddressImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_address_is_imported_from_the_extended_template(): void
    {
        [$user, $project, $location] = $this->createProjectContext();
        $file = $this->createImportFile($project, $location, [
            'Musterstraße',
            '12a',
            '01234',
            'Musterstadt',
            'Deutschland',
            '2. Etage',
        ]);

        try {
            $this->actingAs($user)->postJson(route('teilnehmer.import'), [
                'file' => new UploadedFile(
                    $file,
                    'teilnehmer-mit-adresse.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ])->assertOk()
                ->assertJsonPath('success', true);

            $participant = Personen::query()
                ->where('vorname', 'Ada')
                ->where('nachname', 'Adressentest')
                ->firstOrFail();

            $this->assertDatabaseHas('adresses', [
                'model_type' => Personen::class,
                'model_id' => $participant->id,
                'strasse' => 'Musterstraße',
                'hausnummer' => '12a',
                'plz' => '01234',
                'stadt' => 'Musterstadt',
                'land' => 'Deutschland',
                'zusatzinfo' => '2. Etage',
            ]);
        } finally {
            @unlink($file);
        }
    }

    public function test_partial_address_aborts_the_import_without_creating_a_participant(): void
    {
        [$user, $project, $location] = $this->createProjectContext();
        $file = $this->createImportFile($project, $location, [
            'Unvollständige Straße',
            null,
            null,
            null,
            null,
            null,
        ]);

        try {
            $this->actingAs($user)->postJson(route('teilnehmer.import'), [
                'file' => new UploadedFile(
                    $file,
                    'teilnehmer-mit-unvollstaendiger-adresse.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ])->assertUnprocessable()
                ->assertJsonPath('error', true)
                ->assertJsonPath('message', 'Import abgebrochen. Bitte korrigiere die Fehler in der Excel-Datei.');

            $this->assertDatabaseMissing('personens', [
                'vorname' => 'Ada',
                'nachname' => 'Adressentest',
            ]);
            $this->assertDatabaseCount('adresses', 0);
        } finally {
            @unlink($file);
        }
    }

    public function test_existing_import_files_without_address_columns_still_work(): void
    {
        [$user, $project, $location] = $this->createProjectContext();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('B2', 'Allgemein');
        $sheet->fromArray([
            'Vorname',
            'Nachname',
            'Geschlecht',
            'Geburtsdatum',
            'Projekt_ID',
            'Standort_ID',
        ], null, 'A4');
        $sheet->fromArray([
            'Alter',
            'Import',
            'm',
            null,
            $project->id,
            $location->id,
        ], null, 'A5');
        $file = tempnam(sys_get_temp_dir(), 'participant-legacy-import-') . '.xlsx';
        (new Xlsx($spreadsheet))->save($file);

        try {
            $this->actingAs($user)->postJson(route('teilnehmer.import'), [
                'file' => new UploadedFile(
                    $file,
                    'alte-teilnehmer-vorlage.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ])->assertOk()
                ->assertJsonPath('success', true);

            $this->assertDatabaseHas('personens', [
                'vorname' => 'Alter',
                'nachname' => 'Import',
            ]);
            $this->assertDatabaseCount('adresses', 0);
        } finally {
            @unlink($file);
        }
    }

    private function createProjectContext(): array
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'teilnehmer.import');
        $location = Standort::factory()->create();
        $project = Projekt::factory()->create();

        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $user->person->id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);
        $user->update(['current_team_id' => $project->id]);

        return [$user, $project, $location];
    }

    private function createImportFile(Projekt $project, Standort $location, array $address): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('B2', 'Allgemein');
        $sheet->fromArray([
            'Vorname',
            'Nachname',
            'Geschlecht',
            'Geburtsdatum',
            'Projekt_ID',
            'Standort_ID',
            'Schule_id',
            'Schuljahr',
            'Teil',
            'Klasse',
            'Foerderschueler',
            'EEE',
            'Strasse',
            'Hausnummer',
            'PLZ',
            'Stadt',
            'Land',
            'Zusatzinfo',
        ], null, 'A4');
        $sheet->fromArray(array_merge([
            'Ada',
            'Adressentest',
            'weiblich',
            null,
            $project->id,
            $location->id,
            null,
            null,
            null,
            null,
            null,
            null,
        ], $address), null, 'A5');

        $path = tempnam(sys_get_temp_dir(), 'participant-address-import-') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
