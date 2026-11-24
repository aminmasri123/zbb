<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class BopPartnerPackageExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_rolltag_package_contains_only_the_selected_project_school_year_and_part(): void
    {
        $user = User::factory()->create();
        foreach (['kooperationspartner.index', 'projekt.update', 'dokumente.index', 'dokumente.schule.export', 'gruppe.bop.export.namensschilder', 'anwesenheit.abrechnung'] as $permission) {
            $this->grantTestPermission($user, $permission);
        }
        $project = Projekt::factory()->create(['name' => 'BOP']);
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
            $this->assertSame(3, $zip->numFiles);

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
        $this->assertSame(['Namensschilder'], $project->refresh()->rule_settings['bop_partner_packages']['vorbereitung-pa']['documents']);

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
    }
}
