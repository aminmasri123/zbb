<?php

namespace Tests\Feature;

use App\Models\Anwesenheitsstatuten;
use App\Models\Berechtigungskategorie;
use App\Models\Bereich;
use App\Models\Dokumente;
use App\Models\Gruppe;
use App\Models\GruppeHasPersonen;
use App\Models\Personen;
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
        $word = new PhpWord();
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
