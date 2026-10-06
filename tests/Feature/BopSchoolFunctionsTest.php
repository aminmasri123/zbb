<?php

namespace Tests\Feature;

use App\Models\Anwesenheitsstatuten;
use App\Models\Bereich;
use App\Models\BerufsorientierungBewertung;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\Tage;
use App\Models\User;
use App\Models\Zeiten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class BopSchoolFunctionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_area_counts_deduplicate_attendance_and_use_bop_report_dates(): void
    {
        [$user, $group, $project, $school] = $this->context();
        $project->bereiche()->attach($group->bereich_id);
        $student = PersonenIstSchueler::firstOrFail();
        $source = GruppeHasPersonen::firstOrFail();
        $future = $group->replicate();
        $future->anfangsdatum = '2027-08-31';
        $future->enddatum = '2027-08-31';
        $future->save();
        $copy = $source->replicate();
        $copy->gruppe_id = $future->id;
        $copy->save();
        $source->delete();
        $service = app(\App\Services\Bop\ParticipantAreaReport::class);
        $interim = $service->build($project, 'zwischen');
        $final = $service->build($project, 'ende');
        $this->assertSame(1, $interim['schools'][0]['counts'][$group->bereich_id][2026]);
        $this->assertSame(2, $final['schools'][0]['counts'][$group->bereich_id][2026]);
        $copy2 = $copy->replicate();
        $copy2->save();
        $this->assertSame(2, $service->build($project, 'ende')['schools'][0]['counts'][$group->bereich_id][2026]);
        $this->grantTestPermission($user, 'kooperationspartner.index');
        $this->actingAs($user)->get(route('partner.index'))->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('bopAreaReport', null)->etc());
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');
        $this->get(route('partner.index', ['bericht_typ' => 'ende']))->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('bopAreaReport.type', 'ende')
                ->where('bopAreaReport.schools.0.counts.'.$group->bereich_id.'.2026', 2)->etc());
    }

    public function test_attendance_is_saved_reloaded_and_exported_with_project_boundaries(): void
    {
        [$user, $group, $project, $school] = $this->context();
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');
        $this->actingAs($user);
        $params = ['schulId' => $school->id, 'schuljahr' => '2026/2027', 'teil' => 'Teil 1'];
        $this->postJson(route('generieren.updatedaten.teilnehmer', $params))->assertOk();
        $students = PersonenIstSchueler::all();
        $service = app(\App\Services\Bop\SchoolAttendance::class);
        $statuses = $service->load($project->id, $students);
        $statuses[$students[0]->id]['bo1'] = 'absent';
        $this->putJson(route('bop.school-attendance.store', $params), ['statuses' => $statuses])->assertOk();
        $this->get(route('index-anpassung-anwesenheitsdaten', $params))->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('BOP/Anwesenheitsdaten')
                ->where('initialStatuses.'.$students[0]->id.'.bo1', 'absent')->etc());
        $this->assertSame('absent', $service->load($project->id, $students)[$students[0]->id]['bo1']);
        $this->assertSame('absent', $service->load(Projekt::factory()->create()->id, $students)[$students[0]->id]['rolltag']);
        $response = $this->post(route('export.anwesenheitsdaten.schule.excel', $params))->assertOk();
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($response->baseResponse->getFile()->getPathname());
        $this->assertSame(19, $book->getActiveSheet()->getCell('B5')->getValue());
        @unlink($response->baseResponse->getFile()->getPathname());
        $this->putJson(route('bop.school-attendance.store', $params), ['statuses' => [999999 => $statuses[$students[0]->id]]])->assertUnprocessable();
        $ministry = $this->get(route('export.teilnehmerlisteDesProjekttraegers', array_merge($params, ['starttermin' => '2026-09-01', 'endtermin' => '2026-09-10'])))->assertOk();
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($ministry->baseResponse->getFile()->getPathname());
        $this->assertSame('Ja', $book->getActiveSheet()->getCell('M6')->getValue());
        $this->assertStringContainsString('10 Tage', $book->getActiveSheet()->getCell('K6')->getValue());
        @unlink($ministry->baseResponse->getFile()->getPathname());
    }

    public function test_participant_excel_keeps_class_school_number_and_support_flag(): void
    {
        [$user, $group, $project, $school] = $this->context();
        $this->grantTestPermission($user, 'teilnehmer.liste.export');
        $this->actingAs($user);
        $student = PersonenIstSchueler::firstOrFail();
        $school->update(['schulnummer' => '01234']);
        $student->update(['klasse' => '07.1', 'foerderschueler' => true]);
        $response = $this->get(route('export.teilnehmerliste.schule.excel', ['schuleId' => $school->id, 'schuljahr' => '2026/2027', 'teil' => 'Teil 1']))->assertOk();
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($response->baseResponse->getFile()->getPathname());
        $rows = $book->getActiveSheet()->toArray();
        $this->assertSame('Schulnummer', $rows[0][4]);
        $row = collect($rows)->first(fn ($row) => $row[3] === '07.1');
        $this->assertSame('Ja', $row[5]);
        $this->assertSame('01234', $row[4]);
        @unlink($response->baseResponse->getFile()->getPathname());
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $partner = Partner::query()->create(['name' => 'Gemeinschaftsschule Test']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $role = \App\Models\Role::firstOrCreate(['name' => 'BOP-Exporttest', 'guard_name' => 'web'], ['color' => '#123456']);
        \App\Models\RoleDataAccessSetting::updateOrCreate(['role_id' => $role->id], ['team_scope' => 'own_projects', 'participant_scope' => 'own_projects']);
        $user->assignRole($role);
        $project->partners()->attach($partner->id);
        $this->grantTestPermission($user, 'gruppe.bop.export.auswertungsbogen-bop');

        $area = Bereich::query()->create(['name' => 'IT- und Mediengestaltung']);
        $staff = $user->person;
        $staff->update(['typ' => 'mitarbeiter']);
        $location = Standort::factory()->create();
        $room = Raeume::query()->create(['name' => 'IT-Raum', 'standort_id' => $location->id, 'typ' => 'Werkstatt']);
        $group = Gruppe::query()->create([
            'personen_id' => $staff->id,
            'bereich_id' => $area->id,
            'projekt_id' => $project->id,
            'partner_id' => $partner->id,
            'standort_id' => $location->id,
            'raum_id' => $room->id,
            'anfangsdatum' => '2026-09-01',
            'enddatum' => '2026-09-03',
            'bemerkung' => 'BOP Einteilung Schule '.$partner->id.' Schuljahr 2026/2027 Teil Teil 1 Runde 1',
        ]);
        $day = Tage::query()->create(['datum' => '2026-09-01', 'wochentag' => 'Dienstag']);
        $time = Zeiten::query()->create(['startzeit' => '08:00', 'endzeit' => '14:00']);
        $status = Anwesenheitsstatuten::query()->create(['status' => 'anwesend', 'abkuerzung' => 'A', 'farben' => '#22c55e']);

        foreach ([['Mia', 'Muster', '7.1'], ['Noah', 'Beispiel', '7.2']] as [$firstName, $lastName, $class]) {
            $person = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => $firstName, 'nachname' => $lastName]);
            PersonenIstSchueler::query()->create([
                'person_id' => $person->id,
                'schule_id' => $partner->id,
                'schuljahr' => '2026/2027',
                'teil' => 'Teil 1',
                'klasse' => $class,
            ]);
            $project->teilnehmer()->attach($person->id);
            GruppeHasPersonen::query()->create([
                'personen_id' => $person->id,
                'user_id' => $user->id,
                'gruppe_id' => $group->id,
                'tage_id' => $day->id,
                'zeitgeplant_id' => $time->id,
                'zeittatsaechlich_id' => $time->id,
                'anwesenheitsstatuten_id' => $status->id,
            ]);
        }

        return [$user, $group, $project, $partner];
    }
}
