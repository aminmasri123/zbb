<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Dokumente;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\User;
use App\Services\Bop\PaPreparationAttendanceTemplateExportService;
use App\Services\Bop\BibbAttendanceWordTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class BopPartnerPackageExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_rolltag_download_uses_the_project_and_the_planned_groups_for_the_selected_date(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $school = Partner::create(['name' => 'Rolltagschule']);
        $project->partners()->attach($school);
        $project->teilnehmer()->attach($user->person_id, ['status' => 'aktiv']);
        $user->update(['current_team_id' => $project->id]);
        $this->actingAs($user->fresh());

        $run = \App\Models\BopRun::create([
            'projekt_id' => $project->id, 'partner_id' => $school->id,
            'schuljahr' => '2026/2027', 'teil' => '1', 'status' => 'planning',
        ]);
        $phase = $run->phases()->create([
            'phase_type' => 'roll_day', 'scope_type' => 'classes', 'group_mode' => 'balanced',
            'dates' => ['2026-11-24', '2026-11-25'], 'selected_classes' => ['7.1', '7.2'],
            'class_date_assignments' => ['7.1' => ['2026-11-25'], '7.2' => ['2026-11-24']],
        ]);
        foreach ([
            ['7.1', 'Anna', true], ['7.2', 'Ben', true], ['7.1', 'Fremdprojekt', false],
        ] as [$class, $name, $member]) {
            $person = Personen::factory()->create(['vorname' => $name, 'nachname' => 'Muster']);
            if ($member) {
                $project->teilnehmer()->attach($person, ['status' => 'aktiv']);
            }
            foreach ($name === 'Anna' ? ['2026', '2026/2027'] : ['2026'] as $year) {
                $student = PersonenIstSchueler::create([
                    'person_id' => $person->id, 'schule_id' => $school->id,
                    'schuljahr' => $year, 'teil' => '1', 'klasse' => $class,
                ]);
                $phase->participants()->create([
                    'personen_ist_schueler_id' => $student->id, 'class_name' => $class, 'group_key' => 'Gruppe 2',
                ]);
            }
        }

        $selection = ['partnerID' => $school->id, 'schuljahr' => '2026', 'teil' => '1', 'dialog' => 1];
        $route = 'anwesenheitsliste.BoTag1.export';
        $this->getJson(route($route, $selection + ['klasse' => 'exportAlleKlassenZip', 'preview' => 1]))
            ->assertOk()->assertJsonPath('count', 2)->assertJsonPath('counts_by_date.2026-11-25', 1)
            ->assertJsonFragment(['7.1' => ['2026-11-25']])->assertJsonPath('has_groups', true);
        $this->getJson(route($route, $selection + ['klasse' => '7.1', 'termin' => '2026-11-24']))
            ->assertUnprocessable();

        $response = $this->getJson(route($route, $selection + ['klasse' => 'exportAlleKlassen', 'termin' => '2026-11-25']))
            ->assertOk()->assertDownload('Rolltag_Gruppen.zip');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $overview = tempnam(storage_path(), 'rolltag-test-');
        try {
            $this->assertSame(2, $zip->numFiles);
            $this->assertStringStartsWith('PK', $zip->getFromName('Anwesenheitsliste_1_gruppe_2.xlsx'));
            file_put_contents($overview, $zip->getFromName('Einteilung.xlsx'));
            $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($overview);
            $this->assertSame(['Gruppe 2', '7.1', 'Muster, Anna'], $book->getActiveSheet()->rangeToArray('A4:C4')[0]);
            $this->assertSame(4, $book->getActiveSheet()->getHighestDataRow());
            $book->disconnectWorksheets();
        } finally {
            $zip->close();
            unlink($overview);
            unlink($path);
        }
    }

    public function test_packages_request_missing_dates_and_prefer_the_selected_parts_planning(): void
    {
        $user = User::factory()->create();
        foreach (['kooperationspartner.index', 'anwesenheit.abrechnung'] as $permission) $this->grantTestPermission($user, $permission);
        $project = Projekt::factory()->create(['name' => 'BOP', 'rule_settings' => ['bop_partner_packages' => [
            'vorbereitung-pa' => ['documents' => ['Anwesenheitsliste Vorbereitung PA']],
        ]]]);
        $this->attachPaPreparationTemplate($project);
        $this->attachBibbA3Template($project);
        $school = Partner::create(['name' => 'Planungsschule']);
        $project->partners()->attach($school);
        $project->teilnehmer()->attach($user->person_id, ['status' => 'aktiv']);
        $user->update(['current_team_id' => $project->id]);
        $person = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Plan', 'nachname' => 'Test']);
        $project->teilnehmer()->attach($person, ['status' => 'aktiv']);
        PersonenIstSchueler::create(['person_id' => $person->id, 'schule_id' => $school->id, 'schuljahr' => '2026', 'teil' => '1', 'klasse' => '7.1']);
        $this->actingAs($user->fresh());
        $this->get(route('partner.index'))->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('bopPackages.1.documents', ['Anwesenheitsliste Rolltag', 'Anwesenheitsliste BO/BIBB DIN A3'])
                ->where('bopPackages.1.documentOptions', ['Anwesenheitsliste Rolltag', 'Anwesenheitsliste BO/BIBB DIN A3'])
                ->where('bopPackages.1.unavailableReason', null)
                ->where('bopPackages.2.documents', [])
                ->etc());
        $url = route('partner.bop.packages.download', ['partner' => $school->id, 'package' => 'vorbereitung-pa']);
        $selection = ['schuljahr' => '2026', 'teil' => '1'];
        $this->postJson($url, $selection + ['preview' => true])->assertOk()->assertJsonPath('dates', [])->assertJsonPath('needsDate', true);
        $this->postJson($url, $selection)->assertStatus(422);
        $fallback = $this->postJson($url, $selection + ['termin' => '2026-11-24'])->assertOk()->assertDownload();
        unlink($fallback->baseResponse->getFile()->getPathname());

        $run = \App\Models\BopRun::create(['projekt_id' => $project->id, 'partner_id' => $school->id, 'schuljahr' => '2026/2027', 'teil' => '_all', 'status' => 'planning']);
        foreach (['pa_preparation', 'roll_day', 'wt_feedback'] as $type) {
            $run->phases()->create(['phase_type' => $type, 'scope_type' => 'school', 'group_mode' => 'none',
                'dates' => ['2026-11-24', '2026-11-25'],
                'part_date_assignments' => ['1' => ['2026-11-24'], '2' => ['2026-11-25']],
            ]);
        }
        foreach (['vorbereitung-pa', 'rolltag'] as $package) {
            $this->postJson(route('partner.bop.packages.download', ['partner' => $school->id, 'package' => $package]), $selection + ['preview' => true])
                ->assertOk()->assertJsonPath('dates', ['2026-11-24']);
        }
        $response = $this->postJson($url, $selection + ['termin' => '2026-11-25'])->assertOk()->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($path);
        $xlsx = tempnam(sys_get_temp_dir(), 'pa-planning');
        file_put_contents($xlsx, $zip->getFromName('Anwesenheitsliste Vorbereitung PA.xlsx'));
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsx);
        $this->assertSame(['2026-11-24'], $book->getSheetNames());
        $book->disconnectWorksheets();
        $zip->close();
        unlink($xlsx);
        unlink($path);
        $roll = $this->postJson(route('partner.bop.packages.download', ['partner' => $school->id, 'package' => 'rolltag']), $selection)
            ->assertOk()->assertDownload();
        $path = $roll->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        try {
            $this->assertSame(2, $zip->numFiles);
            $this->assertStringStartsWith('Anwesenheitsliste Rolltag/', $zip->getNameIndex(0));
            $this->assertNotFalse($zip->locateName('Anwesenheitsliste BO-BIBB DIN A3.docx'));
        } finally {
            $zip->close();
            unlink($path);
        }
        $this->postJson(route('partner.bop.packages.download', ['partner' => $school->id, 'package' => 'feedbackgespraech']), $selection)
            ->assertForbidden();
    }

    public function test_rolltag_package_contains_only_the_selected_project_school_year_and_part(): void
    {
        $user = User::factory()->create();
        foreach (['kooperationspartner.index', 'projekt.update', 'dokumente.index', 'dokumente.schule.export', 'gruppe.bop.export.namensschilder', 'anwesenheit.abrechnung'] as $permission) {
            $this->grantTestPermission($user, $permission);
        }
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $this->attachPaPreparationTemplate($project);
        $this->attachBibbA3Template($project);
        $school = Partner::create(['name' => 'Testschule']);
        $project->partners()->attach($school);
        $project->teilnehmer()->attach($user->person_id, ['status' => 'aktiv']);
        $user->update(['current_team_id' => $project->id]);

        foreach ([
            ['Anna', '2026/2027', '1', true],
            ['Fremdprojekt', '2026/2027', '1', false],
            ['AndererTeil', '2026/2027', '2', true],
            ['AnderesJahr', '2025/2026', '1', true],
        ] as [$name, $year, $part, $inProject]) {
            $person = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => $name, 'nachname' => 'Muster']);
            if ($inProject) {
                $project->teilnehmer()->attach($person, ['status' => 'aktiv']);
            }
            PersonenIstSchueler::create([
                'person_id' => $person->id, 'schule_id' => $school->id,
                'schuljahr' => $year, 'teil' => $part, 'klasse' => '7.1',
            ]);
        }

        $this->actingAs($user->fresh())->get(route('dokumente.index'))->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Dokumente/Index')
                ->has('bopProjects.0.packages', 3)
                ->where('bopProjects.0.current', true)
                ->where('bopProjects.0.packages.2.name', 'Feedbackgespräch')
                ->etc());
        $this->getJson(route('partner.bop.packages.schools'))
            ->assertOk()->assertJsonPath('schools.0.name', 'Testschule')
            ->assertJsonCount(2, 'schools.0.years.2026');
        $response = $this->postJson(route('partner.bop.packages.download', [
            'partner' => $school->id, 'package' => 'rolltag',
        ]), ['schuljahr' => '2026', 'teil' => '1', 'termin' => '2026-11-24']);
        $response->assertOk()->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        try {
            $this->assertStringStartsWith('%PDF-', $zip->getFromName('BOP Hausordnung.pdf'));
            $this->assertStringStartsWith('PK', $zip->getFromIndex(1));
            $this->assertStringStartsWith('Anwesenheitsliste Rolltag/', $zip->getNameIndex(1));
            $this->assertNotFalse($zip->locateName('Anwesenheitsliste BO-BIBB DIN A3.docx'));
            $this->assertSame(4, $zip->numFiles);

            $badgePath = tempnam(sys_get_temp_dir(), 'badge-');
            file_put_contents($badgePath, $zip->getFromName('Namensschilder.docx'));
            $badges = new ZipArchive;
            try {
                $this->assertTrue($badges->open($badgePath));
                $xml = $badges->getFromName('word/document.xml');
                $this->assertStringContainsString('Anna Muster', $xml);
                foreach (['Fremdprojekt', 'AndererTeil', 'AnderesJahr'] as $excluded) {
                    $this->assertStringNotContainsString($excluded, $xml);
                }
            } finally {
                $badges->close();
                unlink($badgePath);
            }
        } finally {
            $zip->close();
            unlink($path);
        }

        $this->putJson(route('partner.bop.packages.update', ['package' => 'vorbereitung-pa']), [
            'name' => 'PA mit Namensschildern',
            'documents' => ['Namensschilder'],
        ])->assertOk()->assertJsonPath('packages.0.name', 'PA mit Namensschildern');
        $this->assertSame(['Namensschilder', 'Elterneinverständniserklärung'],
            $project->refresh()->rule_settings['bop_partner_packages']['vorbereitung-pa']['documents']);

        $short = $this->postJson(route('partner.bop.packages.download', [
            'partner' => $school->id, 'package' => 'vorbereitung-pa',
        ]), ['schuljahr' => '2026', 'teil' => '1', 'sortierung' => 'nachname']);
        $short->assertOk()->assertDownload();
        $shortPath = $short->baseResponse->getFile()->getPathname();
        $this->assertTrue($zip->open($shortPath));
        try {
            $this->assertSame(1, $zip->numFiles);
            $this->assertSame('Namensschilder.docx', $zip->getNameIndex(0));
        } finally {
            $zip->close();
            unlink($shortPath);
        }
        $run = \App\Models\BopRun::create([
            'projekt_id' => $project->id, 'partner_id' => $school->id,
            'schuljahr' => '2026/2027', 'teil' => '_all', 'status' => 'planning',
        ]);
        $run->phases()->create([
            'phase_type' => 'pa_preparation', 'scope_type' => 'school',
            'dates' => ['2026-11-24', '2026-11-25'],
            'group_mode' => 'none',
        ]);
        $this->putJson(route('partner.bop.packages.update', ['package' => 'vorbereitung-pa']), [
            'name' => 'Vorbereitung PA', 'documents' => ['Anwesenheitsliste Vorbereitung PA'],
        ])->assertOk();
        $planned = $this->postJson(route('partner.bop.packages.download', [
            'partner' => $school->id, 'package' => 'vorbereitung-pa',
        ]), ['schuljahr' => '2026', 'teil' => '1']);
        $planned->assertOk()->assertDownload();
        $plannedPath = $planned->baseResponse->getFile()->getPathname();
        $this->assertTrue($zip->open($plannedPath));
        $wordPath = tempnam(sys_get_temp_dir(), 'planned-pa-');
        try {
            file_put_contents($wordPath, $zip->getFromName('Anwesenheitsliste Vorbereitung PA.xlsx'));
            $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($wordPath);
            $this->assertSame(['2026-11-24', '2026-11-25'], $book->getSheetNames());
            $cells = json_encode($book->getSheet(0)->toArray());
            $this->assertStringContainsString('Anna', $cells);
            $this->assertStringNotContainsString('Fremdprojekt', $cells);
            $book->disconnectWorksheets();
        } finally {
            $zip->close();
            unlink($plannedPath);
            unlink($wordPath);
        }
        $second = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Ben', 'nachname' => 'Beispiel']);
        $project->teilnehmer()->attach($second, ['status' => 'aktiv']);
        PersonenIstSchueler::create([
            'person_id' => $second->id, 'schule_id' => $school->id,
            'schuljahr' => '2026/2027', 'teil' => '1', 'klasse' => '7.2',
        ]);
        $consent = \App\Models\Dokumente::where('name', 'Elterneinverständniserklärung BOP')->firstOrFail();
        $project->dokumente()->attach($consent);
        $this->putJson(route('partner.bop.packages.update', ['package' => 'vorbereitung-pa']), [
            'name' => 'Vorbereitung PA', 'documents' => [$consent->name],
        ])->assertOk();
        $response = $this->postJson(route('partner.bop.packages.download', [
            'partner' => $school->id, 'package' => 'vorbereitung-pa',
        ]), ['schuljahr' => '2026', 'teil' => '1']);
        $response->assertOk()->assertDownload();
        $archivePath = $response->baseResponse->getFile()->getPathname();
        $this->assertTrue($zip->open($archivePath));
        $docPath = tempnam(sys_get_temp_dir(), 'consent-');
        try {
            $this->assertSame(1, $zip->numFiles);
            file_put_contents($docPath, $zip->getFromName('Elterneinverständniserklärung BOP.docx'));
            $docZip = new ZipArchive;
            $this->assertTrue($docZip->open($docPath));
            $xml = $docZip->getFromName('word/document.xml');
            $this->assertStringContainsString('Anna Muster', $xml);
            $this->assertStringContainsString('Testschule', $xml);
            $this->assertStringContainsString('Ben Beispiel', $xml);
            $this->assertStringContainsString('7.2', $xml);
            $this->assertStringContainsString('7.1', $xml);
            $this->assertStringNotContainsString('Fremdprojekt', $xml);
            $this->assertStringNotContainsString('${', $xml);
            $docZip->close();
        } finally {
            $zip->close();
            unlink($archivePath);
            unlink($docPath);
        }
    }

    private function attachPaPreparationTemplate(Projekt $project): void
    {
        $template = Dokumente::query()
            ->where('export_permission', PaPreparationAttendanceTemplateExportService::EXPORT_PERMISSION)
            ->where('typ', 'excel')
            ->firstOrFail();

        $project->dokumente()->syncWithoutDetaching([
            $template->id => ['gruppen_export' => false, 'serienbrief' => false, 'sort_order' => 0],
        ]);
    }

    private function attachBibbA3Template(Projekt $project): void
    {
        $template = Dokumente::query()
            ->where('export_permission', BibbAttendanceWordTemplate::A3_EXPORT_PERMISSION)
            ->firstOrFail();

        $project->dokumente()->syncWithoutDetaching([
            $template->id => ['gruppen_export' => true, 'serienbrief' => true, 'sort_order' => 0],
        ]);
    }
}
