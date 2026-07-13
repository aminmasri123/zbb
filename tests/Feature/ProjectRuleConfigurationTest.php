<?php

namespace Tests\Feature;

use App\Models\Anwesenheitsstatuten;
use App\Models\Berechtigungskategorie;
use App\Models\Bereich;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectRuleConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_rules_are_saved_directly_on_the_project(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'projekt.update');
        $project = Projekt::factory()->create();
        Anwesenheitsstatuten::query()->create([
            'status' => 'anwesend',
            'farben' => '#22c55e',
            'abkuerzung' => 'A',
        ]);

        $this->actingAs($user)->putJson(route('projekt.rules.update', $project), [
            'rules' => [
                'max_group_participants' => 12,
                'attendance_skip_weekends' => true,
                'attendance_default_status' => 'anwesend',
                'participant_birthdate_required' => true,
                'participant_min_age' => 16,
                'participant_max_age' => 25,
                'participation_initial_status' => 'angefragt',
            ],
        ])->assertOk()
            ->assertJsonPath('rules.max_group_participants', 12)
            ->assertJsonPath('rules.attendance_skip_weekends', true);

        $this->assertSame('anwesend', $project->fresh()->rule('attendance_default_status'));
        $this->assertSame(16, $project->fresh()->rule('participant_min_age'));
    }

    public function test_participant_birthdate_and_age_rules_are_enforced_for_active_project(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->givePermission($user, 'teilnehmer.store');
        $location = Standort::factory()->create();
        $project = Projekt::factory()->create([
            'rule_settings' => [
                'participant_birthdate_required' => true,
                'participant_min_age' => 18,
                'participant_max_age' => 30,
            ],
        ]);
        $this->assign($project, $user->person, $location);
        $user->update(['current_team_id' => $project->id]);
        $payload = [
            'vorname' => 'Regel',
            'nachname' => 'Teilnehmer',
            'geschlecht' => 'd',
            'standort' => $location->id,
        ];

        $this->actingAs($user)->postJson(route('teilnehmer.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('geburtsdatum');

        $payload['geburtsdatum'] = Carbon::today()->subYears(17)->format('Y-m-d');
        $this->actingAs($user)->postJson(route('teilnehmer.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('geburtsdatum');

        $payload['geburtsdatum'] = Carbon::today()->subYears(20)->format('Y-m-d');
        $this->actingAs($user)->postJson(route('teilnehmer.store'), $payload)
            ->assertCreated();

        $this->assertDatabaseHas('personens', [
            'vorname' => 'Regel',
            'nachname' => 'Teilnehmer',
            'geburtsdatum' => $payload['geburtsdatum'] . ' 00:00:00',
        ]);
    }

    public function test_group_assignment_enforces_project_capacity_and_attendance_rules(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'gruppeHasTeilnehmer.store');
        $location = Standort::factory()->create();
        $project = Projekt::factory()->create([
            'rule_settings' => [
                'max_group_participants' => 1,
                'attendance_skip_weekends' => true,
                'attendance_default_status' => 'anwesend',
            ],
        ]);
        $this->assign($project, $user->person, $location);
        $user->update(['current_team_id' => $project->id]);

        $status = Anwesenheitsstatuten::query()->create([
            'status' => 'anwesend',
            'farben' => '#22c55e',
            'abkuerzung' => 'A',
        ]);
        $bereich = Bereich::query()->create(['name' => 'Testbereich']);
        $room = Raeume::query()->create([
            'name' => 'Regelraum',
            'standort_id' => $location->id,
            'typ' => 'Seminarraum',
            'aktiv' => true,
        ]);
        $group = Gruppe::query()->create([
            'personen_id' => $user->person_id,
            'bereich_id' => $bereich->id,
            'projekt_id' => $project->id,
            'standort_id' => $location->id,
            'raum_id' => $room->id,
            'anfangsdatum' => '2027-01-08',
            'enddatum' => '2027-01-11',
            'startzeit' => '08:00',
            'endzeit' => '16:00',
        ]);
        $first = Personen::factory()->create(['typ' => 'teilnehmer']);
        $second = Personen::factory()->create(['typ' => 'teilnehmer']);
        $foreign = Personen::factory()->create(['typ' => 'teilnehmer']);
        $this->assign($project, $first, $location);
        $this->assign($project, $second, $location);

        $payload = [
            'gruppe_id' => $group->id,
            'teilnehmer' => [$first->id, $second->id],
            'startzeit' => '08:00',
            'endzeit' => '16:00',
            'startdatum' => '2027-01-08',
            'enddatum' => '2027-01-11',
        ];

        $this->actingAs($user)->postJson(route('gruppeHasTeilnehmer.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teilnehmer');

        $payload['teilnehmer'] = [$foreign->id];
        $this->actingAs($user)->postJson(route('gruppeHasTeilnehmer.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('teilnehmer');

        $payload['teilnehmer'] = [$first->id];
        $this->actingAs($user)->postJson(route('gruppeHasTeilnehmer.store'), $payload)
            ->assertOk();

        $entries = GruppeHasPersonen::query()->where('gruppe_id', $group->id)->get();
        $this->assertCount(2, $entries);
        $this->assertTrue($entries->every(fn ($entry) => (int) $entry->anwesenheitsstatuten_id === $status->id));
        $this->assertDatabaseMissing('tages', ['datum' => '2027-01-09']);
        $this->assertDatabaseMissing('tages', ['datum' => '2027-01-10']);
    }

    public function test_participant_import_uses_active_project_rules(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'teilnehmer.import');
        $location = Standort::factory()->create();
        $project = Projekt::factory()->create([
            'rule_settings' => ['participant_birthdate_required' => true],
        ]);
        $this->assign($project, $user->person, $location);
        $user->update(['current_team_id' => $project->id]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('B2', 'Allgemein');
        $sheet->fromArray(['Vorname', 'Nachname', 'Geschlecht', 'Geburtsdatum', 'Projekt_ID', 'Standort_ID'], null, 'A4');
        $sheet->fromArray(['Import', 'OhneDatum', 'divers', null, $project->id, $location->id], null, 'A5');
        $path = tempnam(sys_get_temp_dir(), 'matrix-import-') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        try {
            $this->actingAs($user)->postJson(route('teilnehmer.import'), [
                'file' => new UploadedFile($path, 'teilnehmer.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])->assertUnprocessable()
                ->assertJsonPath('error', true);

            $this->assertDatabaseMissing('personens', [
                'vorname' => 'Import',
                'nachname' => 'OhneDatum',
            ]);
        } finally {
            @unlink($path);
        }
    }

    private function assign(Projekt $project, Personen $person, Standort $location): void
    {
        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $person->id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);
    }

    private function givePermission(User $user, string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Projektregeln'],
            ['beschreibung' => '']
        );
        $permission = Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo($permission);
    }
}
