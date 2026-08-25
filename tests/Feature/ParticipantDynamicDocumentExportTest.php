<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Dokumente;
use App\Models\DokumentKategorie;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Standort;
use App\Models\User;
use App\Models\Zeitraum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use ZipArchive;

class ParticipantDynamicDocumentExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_page_lists_authorized_direct_and_category_templates_only(): void
    {
        [$user, $project, $participant] = $this->participantContext();

        $direct = $this->document('Direkte Einladung', 'teilnehmer', 'dokumente.export.participant-direct');
        $project->dokumente()->attach($direct->id);
        $this->givePermission($user, $direct->export_permission);

        $category = DokumentKategorie::query()->create(['name' => 'BvB Reha']);
        $project->dokumentKategorien()->attach($category->id);
        $categoryDocument = $this->document('Kategorie Einladung', 'teilnehmer', 'dokumente.export.participant-category');
        $category->dokumente()->attach($categoryDocument->id);
        $this->givePermission($user, $categoryDocument->export_permission);

        $groupDocument = $this->document('Nur Gruppe', 'gruppe', 'dokumente.export.participant-group');
        $project->dokumente()->attach($groupDocument->id);
        $this->givePermission($user, $groupDocument->export_permission);

        $unauthorized = $this->document('Ohne Berechtigung', 'teilnehmer', 'dokumente.export.participant-forbidden');
        $project->dokumente()->attach($unauthorized->id);

        $this->actingAs($user)
            ->get(route('teilnehmer.edit', $participant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teilnehmer/Edit')
                ->has('dokumente', 2)
                ->where('dokumente.0.name', 'Direkte Einladung')
                ->where('dokumente.1.name', 'Kategorie Einladung'));
    }

    public function test_authorized_participant_pdf_template_can_be_downloaded(): void
    {
        [$user, $project, $participant] = $this->participantContext();
        $document = $this->document('Individuelle Einladung', 'teilnehmer', 'dokumente.export.participant-pdf');
        $project->dokumente()->attach($document->id);
        $this->givePermission($user, $document->export_permission);

        $path = storage_path('app/temp/participant-document-test.pdf');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, '%PDF-1.4 test');
        $document->update(['dateipfad' => '/app/temp/participant-document-test.pdf']);

        try {
            $this->actingAs($user)
                ->get(route('teilnehmer.document.export', [
                    'personen' => $participant,
                    'dokument' => $document,
                    'format' => 'pdf',
                ]))
                ->assertOk()
                ->assertDownload('Individuelle_Einladung_Ada_Lovelace.pdf');
        } finally {
            @unlink($path);
        }
    }

    public function test_participant_export_rejects_a_group_template(): void
    {
        [$user, $project, $participant] = $this->participantContext();
        $document = $this->document('Nur Gruppe', 'gruppe', 'dokumente.export.participant-wrong-target');
        $project->dokumente()->attach($document->id);
        $this->givePermission($user, $document->export_permission);

        $this->actingAs($user)
            ->get(route('teilnehmer.document.export', [
                'personen' => $participant,
                'dokument' => $document,
                'format' => 'pdf',
            ]))
            ->assertNotFound();
    }

    public function test_participant_word_template_fills_participant_placeholders(): void
    {
        [$user, $project, $participant] = $this->participantContext();
        $supervisor = Personen::factory()->create([
            'typ' => 'mitarbeiter',
            'geschlecht' => 'm',
            'vorname' => 'Max',
            'nachname' => 'Mentor',
        ]);
        $participation = ProjektHasPersonen::query()
            ->where('projekt_id', $project->id)
            ->where('personen_id', $participant->id)
            ->firstOrFail();
        $participation->meta()->create(['betreuer_id' => $supervisor->id]);
        Zeitraum::query()->create([
            'starttermin' => '2026-09-15',
            'startzeit' => '09:30',
            'model_type' => ProjektHasPersonen::class,
            'model_id' => $participation->id,
        ]);
        $document = $this->document('Persönlicher Brief', 'teilnehmer', 'dokumente.export.participant-word');
        $document->update([
            'typ' => 'word',
            'ausgabeformate' => ['docx'],
        ]);
        $project->dokumente()->attach($document->id);
        $this->givePermission($user, $document->export_permission);

        $templatePath = storage_path('app/temp/participant-document-test.docx');
        $word = new PhpWord();
        $word->addSection()->addText(
            '${vorname} ${nachname} – ${projekt} – ${termin_datum} – ${termin_uhrzeit} – ${betreuer_anrede_dativ} ${betreuer_nachname}'
        );
        WordIOFactory::createWriter($word, 'Word2007')->save($templatePath);
        $document->update(['dateipfad' => '/app/temp/participant-document-test.docx']);

        $outputPath = null;

        try {
            $response = $this->actingAs($user)
                ->get(route('teilnehmer.document.export', [
                    'personen' => $participant,
                    'dokument' => $document,
                    'format' => 'docx',
                ]))
                ->assertOk()
                ->assertDownload('Personlicher_Brief_Ada_Lovelace.docx');

            $outputPath = $response->baseResponse->getFile()->getPathname();
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($outputPath) === true);
            $documentXml = $zip->getFromName('word/document.xml');
            $zip->close();

            $this->assertStringContainsString('Ada Lovelace', $documentXml);
            $this->assertStringContainsString('BvB Reha', $documentXml);
            $this->assertStringContainsString('15.09.2026', $documentXml);
            $this->assertStringContainsString('09:30', $documentXml);
            $this->assertStringContainsString('Herrn Mentor', $documentXml);
        } finally {
            @unlink($templatePath);
            if ($outputPath && $outputPath !== $templatePath) {
                @unlink($outputPath);
            }
        }
    }

    public function test_participant_export_reports_missing_appointment_fields_used_by_template(): void
    {
        [$user, $project, $participant] = $this->participantContext();
        $document = $this->document('Terminbrief', 'teilnehmer', 'dokumente.export.participant-missing-fields');
        $document->update([
            'typ' => 'word',
            'ausgabeformate' => ['docx'],
        ]);
        $project->dokumente()->attach($document->id);
        $this->givePermission($user, $document->export_permission);

        $templatePath = storage_path('app/temp/participant-document-missing-fields.docx');
        $word = new PhpWord();
        $word->addSection()->addText(
            '${termin_datum} ${termin_uhrzeit} ${betreuer_anrede_dativ} ${betreuer_nachname}'
        );
        WordIOFactory::createWriter($word, 'Word2007')->save($templatePath);
        $document->update(['dateipfad' => '/app/temp/participant-document-missing-fields.docx']);

        try {
            $this->actingAs($user)
                ->from(route('teilnehmer.edit', $participant))
                ->get(route('teilnehmer.document.export', [
                    'personen' => $participant,
                    'dokument' => $document,
                    'format' => 'docx',
                ]))
                ->assertRedirect(route('teilnehmer.edit', $participant))
                ->assertSessionHas('error', function (string $message): bool {
                    return str_contains($message, 'Termin-Datum')
                        && str_contains($message, 'Termin-Uhrzeit')
                        && str_contains($message, 'Betreuer');
                });
        } finally {
            @unlink($templatePath);
        }
    }

    public function test_participant_excel_template_fills_participant_placeholders(): void
    {
        [$user, $project, $participant] = $this->participantContext();
        $document = $this->document('Teilnehmerdaten', 'teilnehmer', 'dokumente.export.participant-excel');
        $document->update([
            'typ' => 'excel',
            'ausgabeformate' => ['xlsx'],
        ]);
        $project->dokumente()->attach($document->id);
        $this->givePermission($user, $document->export_permission);

        $templatePath = storage_path('app/temp/participant-document-test.xlsx');
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setCellValue('A1', '${vorname} ${nachname}');
        $spreadsheet->getActiveSheet()->setCellValue('B1', '${projekt}');
        SpreadsheetIOFactory::createWriter($spreadsheet, 'Xlsx')->save($templatePath);
        $document->update(['dateipfad' => '/app/temp/participant-document-test.xlsx']);

        $outputPath = null;

        try {
            $response = $this->actingAs($user)
                ->get(route('teilnehmer.document.export', [
                    'personen' => $participant,
                    'dokument' => $document,
                    'format' => 'xlsx',
                ]))
                ->assertOk()
                ->assertDownload('Teilnehmerdaten_Ada_Lovelace.xlsx');

            $outputPath = $response->baseResponse->getFile()->getPathname();
            $exported = SpreadsheetIOFactory::load($outputPath);

            $this->assertSame('Ada Lovelace', $exported->getActiveSheet()->getCell('A1')->getValue());
            $this->assertSame('BvB Reha', $exported->getActiveSheet()->getCell('B1')->getValue());
        } finally {
            @unlink($templatePath);
            if ($outputPath && $outputPath !== $templatePath) {
                @unlink($outputPath);
            }
        }
    }

    private function participantContext(): array
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'teilnehmer.update');

        $role = Role::query()->create([
            'name' => 'Teilnehmerexport-' . uniqid(),
            'guard_name' => 'web',
            'color' => '#123456',
        ]);
        RoleDataAccessSetting::query()->create([
            'role_id' => $role->id,
            'team_scope' => 'own_projects',
            'participant_scope' => 'all',
        ]);
        $user->assignRole($role);

        $location = Standort::factory()->create();
        $project = Projekt::factory()->create([
            'name' => 'BvB Reha',
            'feature_settings' => ['participant_management' => true],
        ]);
        $participant = Personen::factory()->create([
            'typ' => 'teilnehmer',
            'vorname' => 'Ada',
            'nachname' => 'Lovelace',
        ]);

        $this->assign($project, $user->person, $location);
        $this->assign($project, $participant, $location);
        $user->update(['current_team_id' => $project->id]);

        return [$user, $project, $participant];
    }

    private function document(string $name, string $target, string $permission): Dokumente
    {
        $this->ensurePermission($permission);

        return Dokumente::query()->create([
            'name' => $name,
            'typ' => 'pdf',
            'kontext' => 'teilnehmer',
            'einsatzbereich' => $target,
            'ausgabeformate' => ['pdf'],
            'dateipfad' => '/app/temp/not-created.pdf',
            'aktiv' => true,
            'export_permission' => $permission,
        ]);
    }

    private function assign(Projekt $project, Personen $person, Standort $location): ProjektHasPersonen
    {
        return ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $person->id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);
    }

    private function givePermission(User $user, string $name): void
    {
        $this->ensurePermission($name);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo($name);
    }

    private function ensurePermission(string $name): void
    {
        $categoryId = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Dokumentenexporte'],
            ['beschreibung' => '']
        )->id;

        Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $categoryId, 'beschreibung' => null]
        );
    }
}
