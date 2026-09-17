<?php

namespace Tests\Feature;

use App\Models\Anwesenheitsstatuten;
use App\Models\Bereich;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\PoboCertificatePrintSetting;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Tage;
use App\Models\User;
use App\Models\Zeiten;
use App\Services\Bop\PoboCertificateExportService;
use App\Services\Documents\OfficeToPdfConverter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;
use ZipArchive;
use PhpOffice\PhpWord\TemplateProcessor;

class PoboSchoolCertificateExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_uses_rolltag_area_and_last_workshop_day_for_every_student(): void
    {
        [$user, $partner] = $this->context();

        $this->actingAs($user)->getJson(route('export.zertifikat.schule.pobo.preview', [
            'idSchule' => $partner->id,
            'schuljahr' => '2026-2027',
            'teil' => 'Teil 1',
        ]))->assertOk()->assertJsonPath('period.from', '2026-09-04')
            ->assertJsonPath('period.to', '2026-09-11')
            ->assertJsonPath('period.source', 'rolltag_area')
            ->assertJsonPath('eligible_participants', 2)
            ->assertJsonCount(2, 'participants');
    }

    public function test_certificate_template_positions_follow_the_printed_area_order(): void
    {
        foreach (['Zertifikat_Maske_POBO.docx', 'Zertifikat_Maske_POBO_klein_text.docx'] as $filename) {
            $processor = new TemplateProcessor(storage_path('vorlage/projekte/bop/word/'.$filename));
            $variables = collect($processor->getVariables());

            $this->assertSame(
                ['hauswirtschaft', 'metall', 'holz', 'IT', 'verkauf', 'kosmetik', 'elektro', 'farbe'],
                $variables->filter(fn (string $variable) => in_array($variable, [
                    'hauswirtschaft', 'metall', 'holz', 'IT', 'verkauf', 'kosmetik', 'elektro', 'farbe',
                ], true))->values()->all()
            );
        }
    }

    public function test_authorized_user_can_save_shared_word_and_pdf_calibration(): void
    {
        [$user, $partner] = $this->context();
        $this->grantTestPermission($user, 'dokumente.update');

        $this->actingAs($user)->putJson(route('export.zertifikat.schule.pobo.settings.update'), [
            'horizontal_offset_mm' => 2.5,
            'vertical_offset_mm' => -1,
            'row_spacing_offset_mm' => 0.5,
            'cross_font_size_pt' => 14,
        ])->assertOk()
            ->assertJsonPath('print_settings.horizontal_offset_mm', 2.5)
            ->assertJsonPath('print_settings.vertical_offset_mm', -1)
            ->assertJsonPath('print_settings.row_spacing_offset_mm', 0.5)
            ->assertJsonPath('print_settings.cross_font_size_pt', 14);

        $settings = PoboCertificatePrintSetting::current();
        $this->assertSame(2.5, (float) $settings->horizontal_offset_mm);
        $this->assertSame($user->id, $settings->updated_by);

        $destination = storage_path('framework/testing/pobo-calibration-'.uniqid());
        try {
            $documents = app(PoboCertificateExportService::class)->createSchoolDocuments(
                $partner->id,
                '2026-2027',
                'Teil 1',
                Projekt::query()->where('name', 'BOP')->firstOrFail(),
                $destination,
            );
            $certificate = $documents->firstWhere('type', 'certificate');
            $document = new ZipArchive;
            $this->assertTrue($document->open($certificate['path']) === true);
            $xml = new \DOMDocument;
            $xml->loadXML((string) $document->getFromName('word/document.xml'));
            $document->close();
            $xpath = new \DOMXPath($xml);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $firstMarkerLeft = (int) $xpath->evaluate('string((//w:body/w:p[normalize-space(string(.))="X"]/w:pPr/w:ind/@w:left)[1])');

            $this->assertGreaterThan(4956, $firstMarkerLeft);
        } finally {
            File::deleteDirectory($destination);
        }
    }

    public function test_certificate_crosses_match_all_workshop_areas_and_ignore_rolltag(): void
    {
        [$user, $partner] = $this->context();
        $this->actingAs($user);
        $project = Projekt::query()->where('name', 'BOP')->firstOrFail();
        $destination = storage_path('framework/testing/pobo-area-markers-'.uniqid());

        try {
            $documents = app(PoboCertificateExportService::class)->createSchoolDocuments(
                $partner->id,
                '2026-2027',
                'Teil 1',
                $project,
                $destination,
            );
            $values = $documents->firstWhere('type', 'certificate')['values'];

            foreach (['hauswirtschaft', 'metall', 'holz', 'IT', 'verkauf', 'kosmetik', 'elektro', 'farbe'] as $marker) {
                $this->assertSame('X', $values[$marker], "Das Kreuz für {$marker} fehlt.");
            }
            $this->assertArrayNotHasKey('rolltag', $values);
        } finally {
            File::deleteDirectory($destination);
        }
    }

    public function test_school_word_export_uses_original_templates_and_fills_every_participant(): void
    {
        [$user, $partner] = $this->context();

        $response = $this->actingAs($user)->get(route('export.zertifikat.schule.pobo', [
            'idSchule' => $partner->id,
            'schuljahr' => '2026-2027',
            'teil' => 'Teil 1',
            'von' => '2026-09-01',
            'bis' => '2026-09-18',
        ]))->assertOk();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $this->assertSame(2, $zip->numFiles);
        $files = collect(range(0, $zip->numFiles - 1))->map(fn (int $index) => $zip->getNameIndex($index));
        $this->assertTrue($files->contains(fn (string $name) => str_starts_with($name, 'Zertifikat_') && str_contains($name, 'Erste_Anna')));
        $this->assertTrue($files->contains(fn (string $name) => str_starts_with($name, 'Teilnahmebescheinigung_') && str_contains($name, 'Zweite_Ben')));

        $contents = $files->mapWithKeys(function (string $name) use ($zip) {
            $temporary = tempnam(sys_get_temp_dir(), 'pobo-certificate-');
            file_put_contents($temporary, $zip->getFromName($name));
            $document = new ZipArchive;
            $this->assertTrue($document->open($temporary) === true);
            $xml = (string) $document->getFromName('word/document.xml');
            $document->close();
            @unlink($temporary);

            return [$name => html_entity_decode(strip_tags($xml))];
        });
        $zip->close();

        $certificate = $contents->first(fn (string $text, string $name) => str_starts_with($name, 'Zertifikat_'));
        $participation = $contents->first(fn (string $text, string $name) => str_starts_with($name, 'Teilnahmebescheinigung_'));
        $this->assertStringContainsString('Anna', $certificate);
        $this->assertStringContainsString('Erste', $certificate);
        $this->assertStringContainsString('01.09.2026', $certificate);
        $this->assertStringContainsString('18.09.2026', $certificate);
        $this->assertStringContainsString('Ben', $participation);
        $this->assertStringContainsString('Zweite', $participation);
        $this->assertStringContainsString('01.09.2026', $participation);
        $this->assertStringContainsString('18.09.2026', $participation);
        $this->assertStringNotContainsString('${', $contents->implode('\n'));
    }

    public function test_school_pdf_export_converts_the_filled_original_word_documents(): void
    {
        [$user, $partner] = $this->context();
        $convertedNames = [];
        $converter = Mockery::mock(OfficeToPdfConverter::class);
        $converter->shouldReceive('convert')->twice()->andReturnUsing(function (string $documentPath, string $directory) use (&$convertedNames) {
            $document = new ZipArchive;
            $this->assertTrue($document->open($documentPath) === true);
            $text = html_entity_decode(strip_tags((string) $document->getFromName('word/document.xml')));
            $document->close();
            $this->assertStringNotContainsString('${', $text);
            $name = str_contains($text, 'Anna') ? 'Anna Erste' : 'Ben Zweite';
            $convertedNames[] = $name;
            $path = $directory.DIRECTORY_SEPARATOR.pathinfo($documentPath, PATHINFO_FILENAME).'.pdf';
            File::put($path, Pdf::loadHTML('<html><body>'.$name.'</body></html>')->output());

            return $path;
        });
        $this->app->instance(OfficeToPdfConverter::class, $converter);

        $response = $this->actingAs($user)->get(route('export.zertifikat.schule.pobo.pdf', [
            'schuleId' => $partner->id,
            'schuljahr' => '2026-2027',
            'teil' => 'Teil 1',
        ]))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->assertEqualsCanonicalizing(['Anna Erste', 'Ben Zweite'], $convertedNames);
        $merged = new Fpdi;
        $this->assertSame(2, $merged->setSourceFile($response->baseResponse->getFile()->getPathname()));
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'POBO-Zertifikatexport', 'guard_name' => 'web', 'color' => '#123456']);
        RoleDataAccessSetting::create([
            'role_id' => $role->id,
            'team_scope' => 'own_projects',
            'participant_scope' => 'own_projects',
        ]);
        $user->assignRole($role);
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $partner = Partner::query()->create(['name' => 'Testschule']);
        $user->projekte()->attach($project->id);
        $project->partners()->attach($partner->id);
        $user->update(['current_team_id' => $project->id]);
        $this->grantTestPermission($user, 'dokumente.schule.export');

        $people = collect([
            Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Anna', 'nachname' => 'Erste']),
            Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Ben', 'nachname' => 'Zweite']),
        ]);
        foreach ($people as $index => $person) {
            PersonenIstSchueler::query()->create([
                'person_id' => $person->id,
                'schule_id' => $partner->id,
                'schuljahr' => '2026/2027',
                'teil' => 'Teil 1',
                'klasse' => $index === 0 ? '8a' : '8b',
            ]);
            ProjektHasPersonen::query()->create([
                'projekt_id' => $project->id,
                'personen_id' => $person->id,
                'status' => 'aktiv',
            ]);
        }

        $present = Anwesenheitsstatuten::query()->create([
            'status' => 'anwesend', 'abkuerzung' => 'A', 'farben' => '#22c55e',
        ]);
        $planned = Zeiten::query()->create(['startzeit' => '08:00', 'endzeit' => '14:00']);
        $staff = Personen::factory()->create(['typ' => 'mitarbeiter']);
        $areas = [
            ['name' => 'Rolltag', 'dates' => ['2026-09-04']],
            ['name' => 'Holztechnik', 'dates' => ['2026-09-07', '2026-09-08', '2026-09-09']],
            ['name' => 'Metalltechnik', 'dates' => ['2026-09-10', '2026-09-11']],
            ['name' => 'Hauswirtschaft', 'dates' => ['2026-09-11']],
            ['name' => 'IT-und Mediengestaltung', 'dates' => ['2026-09-11']],
            ['name' => 'Verkauf und Wirtschaft', 'dates' => ['2026-09-11']],
            ['name' => 'Friseur/Kosmetik/Körperpflege', 'dates' => ['2026-09-11']],
            ['name' => 'Elektro', 'dates' => ['2026-09-11']],
            ['name' => 'Maler und Lackierer', 'dates' => ['2026-09-11']],
        ];
        foreach ($areas as $areaData) {
            $area = Bereich::query()->create(['name' => $areaData['name']]);
            $group = Gruppe::query()->create([
                'personen_id' => $staff->id,
                'projekt_id' => $project->id,
                'partner_id' => $partner->id,
                'bereich_id' => $area->id,
                'anfangsdatum' => $areaData['dates'][0],
                'enddatum' => end($areaData['dates']),
            ]);
            foreach ($areaData['dates'] as $date) {
                $weekday = match (Carbon::parse($date)->dayOfWeekIso) {
                    1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag',
                    6 => 'Samstag', default => 'Sonntag',
                };
                $day = Tage::query()->firstOrCreate(['datum' => $date], ['wochentag' => $weekday]);
                foreach ($people as $index => $person) {
                    $secondParticipantPresent = in_array($date, ['2026-09-04', '2026-09-07', '2026-09-08', '2026-09-11'], true);
                    if ($index === 1 && ! $secondParticipantPresent) {
                        continue;
                    }
                    GruppeHasPersonen::query()->create([
                        'personen_id' => $person->id,
                        'user_id' => $user->id,
                        'gruppe_id' => $group->id,
                        'tage_id' => $day->id,
                        'zeitgeplant_id' => $planned->id,
                        'zeittatsaechlich_id' => $planned->id,
                        'anwesenheitsstatuten_id' => $present->id,
                    ]);
                }
            }
        }

        return [$user, $partner];
    }
}
