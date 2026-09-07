<?php

namespace Tests\Feature;

use App\Models\Anwesenheitsstatuten;
use App\Models\Berechtigungskategorie;
use App\Models\Bereich;
use App\Models\Dokumente;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Partner;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Raeume;
use App\Models\Standort;
use App\Models\Tage;
use App\Models\User;
use App\Models\Zeiten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use ZipArchive;

class GroupDynamicDocumentValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_export_reports_requested_data_for_each_participant_before_creating_files(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create([
            'name' => 'BvB Reha',
            'feature_settings' => ['group_management' => true],
        ]);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $location = Standort::factory()->create();
        $area = Bereich::query()->create(['name' => 'BvB Reha']);
        $room = Raeume::query()->create([
            'name' => 'Seminarraum',
            'standort_id' => $location->id,
            'typ' => 'Seminarraum',
            'aktiv' => true,
        ]);
        $group = Gruppe::query()->create([
            'personen_id' => $user->person_id,
            'bereich_id' => $area->id,
            'projekt_id' => $project->id,
            'standort_id' => $location->id,
            'raum_id' => $room->id,
        ]);
        $participant = Personen::factory()->create([
            'typ' => 'teilnehmer',
            'vorname' => 'Ohne',
            'nachname' => 'Kontaktdaten',
            'geburtsdatum' => null,
        ]);
        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $participant->id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);
        $day = Tage::query()->create(['datum' => '2026-09-01', 'wochentag' => 'Dienstag']);
        $time = Zeiten::query()->create(['startzeit' => '08:00', 'endzeit' => '16:00']);
        $status = Anwesenheitsstatuten::query()->create([
            'status' => 'anwesend',
            'farben' => '#22c55e',
            'abkuerzung' => 'A',
        ]);
        GruppeHasPersonen::query()->create([
            'personen_id' => $participant->id,
            'user_id' => $user->person_id,
            'gruppe_id' => $group->id,
            'tage_id' => $day->id,
            'zeitgeplant_id' => $time->id,
            'zeittatsaechlich_id' => $time->id,
            'anwesenheitsstatuten_id' => $status->id,
        ]);

        $permission = $this->permission('dokumente.export.group-completeness');
        $user->givePermissionTo($permission);
        $document = Dokumente::query()->create([
            'name' => 'Teilnehmerliste vollständig',
            'typ' => 'word',
            'kontext' => 'gruppe',
            'einsatzbereich' => 'gruppe',
            'ausgabeformate' => ['docx'],
            'dateipfad' => '/app/temp/group-document-completeness.docx',
            'aktiv' => true,
            'export_permission' => $permission->name,
            'gruppen_export_modus' => 'eine_datei',
        ]);
        $project->dokumente()->attach($document->id, [
            'gruppen_export' => true,
            'serienbrief' => true,
            'sort_order' => 0,
        ]);

        $templatePath = storage_path('app/temp/group-document-completeness.docx');
        $word = new PhpWord;
        $word->addSection()->addText('${vorname} ${nachname} ${geburtsdatum} ${email} ${telefon}');
        WordIOFactory::createWriter($word, 'Word2007')->save($templatePath);

        try {
            $this->actingAs($user)
                ->from(route('gruppe.index'))
                ->get(route('gruppe.export.serienbrief', [
                    'gruppe' => $group,
                    'dokument' => $document,
                    'format' => 'docx',
                ]))
                ->assertRedirect(route('gruppe.index'))
                ->assertSessionHas('error', function (string $message): bool {
                    return str_contains($message, 'Teilnehmer Ohne Kontaktdaten')
                        && str_contains($message, 'Geburtsdatum')
                        && str_contains($message, 'E-Mail')
                        && str_contains($message, 'Telefon');
                });
        } finally {
            @unlink($templatePath);
        }
    }

    public static function bopTemplateCases(): array
    {
        return ['Hausordnung' => [false], 'BOP-Auswertung' => [true]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bopTemplateCases')]
    public function test_bop_templates_use_only_the_current_participants_data(bool $evaluation): void
    {
        $user = User::factory()->create();
        $role = \App\Models\Role::create(['name' => 'BOP-Exporttest', 'guard_name' => 'web', 'color' => '#123456']);
        \App\Models\RoleDataAccessSetting::create(['role_id' => $role->id, 'team_scope' => 'own_projects', 'participant_scope' => 'own_projects']);
        $user->assignRole($role);
        $project = Projekt::factory()->create([
            'name' => 'BOP',
            'feature_settings' => ['group_management' => true],
        ]);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $location = Standort::factory()->create();
        $partner = Partner::query()->create(['name' => 'Testschule']);
        $area = Bereich::query()->create(['name' => 'Hauswirtschaft']);
        $room = Raeume::query()->create([
            'name' => 'Lehrküche',
            'standort_id' => $location->id,
            'typ' => 'Werkstatt',
            'aktiv' => true,
        ]);
        $group = Gruppe::query()->create([
            'personen_id' => $user->person_id,
            'bereich_id' => $area->id,
            'projekt_id' => $project->id,
            'partner_id' => $partner->id,
            'standort_id' => $location->id,
            'raum_id' => $room->id,
        ]);
        $day = Tage::query()->create(['datum' => '2026-09-01', 'wochentag' => 'Dienstag']);
        $time = Zeiten::query()->create(['startzeit' => '08:00', 'endzeit' => '14:00']);
        $status = Anwesenheitsstatuten::query()->create([
            'status' => 'anwesend',
            'farben' => '#22c55e',
            'abkuerzung' => 'A',
        ]);

        foreach ([['Anna', 'Erste', '7.1'], ['Ben', 'Zweite', '7.2']] as [$vorname, $nachname, $klasse]) {
            $participant = Personen::factory()->create([
                'typ' => 'teilnehmer',
                'vorname' => $vorname,
                'nachname' => $nachname,
            ]);
            ProjektHasPersonen::query()->create([
                'projekt_id' => $project->id,
                'personen_id' => $participant->id,
                'standort_id' => $location->id,
                'status' => 'aktiv',
            ]);
            PersonenIstSchueler::query()->create([
                'person_id' => $participant->id,
                'klasse' => $klasse,
                'schuljahr' => '2026',
                'teil' => '1',
                'schule_id' => $partner->id,
            ]);
            GruppeHasPersonen::query()->create([
                'personen_id' => $participant->id,
                'user_id' => $user->person_id,
                'gruppe_id' => $group->id,
                'tage_id' => $day->id,
                'zeitgeplant_id' => $time->id,
                'zeittatsaechlich_id' => $time->id,
                'anwesenheitsstatuten_id' => $status->id,
            ]);
            if ($evaluation) {
                \App\Models\BerufsorientierungBewertung::create([
                    'gruppe_id' => $group->id, 'personen_id' => $participant->id, 'user_id' => $user->id,
                    'kriterium' => 'einhaltung_der_regeln', 'kriterium_label' => 'Arbeitszeitregeln',
                    'bewertung' => $vorname === 'Anna' ? 5 : 1,
                ]);
            }
        }

        $permission = $this->permission('dokumente.export.bop-hausordnung-class');
        $user->givePermissionTo($permission);
        $document = Dokumente::query()->create([
            'name' => $evaluation ? 'Auswertungsbogen BOP' : 'Hausordnung BOP',
            'typ' => 'word',
            'kontext' => 'gruppe',
            'einsatzbereich' => 'gruppe',
            'ausgabeformate' => ['docx'],
            'dateipfad' => '/app/temp/test-bop-template.docx',
            'aktiv' => true,
            'export_permission' => $permission->name,
            'gruppen_export_modus' => 'einzelne_dateien',
        ]);
        $project->dokumente()->attach($document->id, [
            'gruppen_export' => true,
            'serienbrief' => true,
            'sort_order' => 0,
        ]);

        $templatePath = storage_path('app/temp/test-bop-template.docx');
        $word = new PhpWord;
        $word->addSection()->addText($evaluation
            ? '${nachname}: ${klasse}; ${schule}; ${anleiter}; 5=${a-5}; 1=${a-1}; unbewertet=${k-5}'
            : '${nachname}: ${klassen}');
        WordIOFactory::createWriter($word, 'Word2007')->save($templatePath);

        try {
            $response = $this->actingAs($user)->get(route('gruppe.export.serienbrief', [
                'gruppe' => $group,
                'dokument' => $document,
                'format' => 'docx',
            ]));

            $response->assertOk();
            if (!$evaluation) $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            $outputPath = $response->baseResponse->getFile()->getPathname();
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($outputPath) === true);
            $xml = '';
            if ($evaluation) {
                $this->assertSame(2, $zip->numFiles);
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $nestedPath = tempnam(sys_get_temp_dir(), 'bop-test-');
                    file_put_contents($nestedPath, $zip->getFromIndex($i));
                    $nested = new ZipArchive;
                    $this->assertTrue($nested->open($nestedPath) === true);
                    $xml .= $nested->getFromName('word/document.xml');
                    $nested->close();
                    unlink($nestedPath);
                }
            } else {
                $xml = (string) $zip->getFromName('word/document.xml');
            }
            $zip->close();

            $this->assertStringContainsString('Erste: 7.1', $xml);
            $this->assertStringContainsString('Zweite: 7.2', $xml);
            $this->assertStringNotContainsString('7.1 + 7.2', $xml);
            if ($evaluation) {
                $text = html_entity_decode(strip_tags($xml));
                $this->assertMatchesRegularExpression('/Erste: 7.1; Testschule; [^;]+; 5=X; 1=; unbewertet=/', $text);
                $this->assertMatchesRegularExpression('/Zweite: 7.2; Testschule; [^;]+; 5=; 1=X; unbewertet=/', $text);
                $this->assertStringNotContainsString('${', $xml);
                $document->update(['ausgabeformate' => ['docx', 'pdf']]);
                $pdfResponse = $this->get(route('gruppe.export.serienbrief', ['gruppe' => $group, 'dokument' => $document, 'format' => 'pdf']))->assertOk();
                $pdf = (new \Smalot\PdfParser\Parser)->parseContent($pdfResponse->getContent());
                $this->assertCount(2, $pdf->getPages());
                foreach ($pdf->getPages() as $page) {
                    $this->assertMatchesRegularExpression('/1\.\s+Einhaltung/', $page->getText());
                    $this->assertMatchesRegularExpression('/11\.\s+Einschätzung/', $page->getText());
                    $this->assertDoesNotMatchRegularExpression('/12\.\s+Einhaltung/', $page->getText());
                }
                \App\Models\RoleDataAccessSetting::where('role_id', $role->id)->update(['participant_scope' => 'none']);
                $this->get(route('gruppe.export.serienbrief', ['gruppe' => $group, 'dokument' => $document, 'format' => 'docx']))->assertForbidden();
            }
        } finally {
            @unlink($templatePath);
        }
    }

    private function permission(string $name): Permission
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Dokumentenexporte'],
            ['beschreibung' => '']
        );
        $permission = Permission::query()->create([
            'name' => $name,
            'guard_name' => 'web',
            'berechtigungskategorie_id' => $category->id,
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permission;
    }
}
