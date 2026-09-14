<?php

namespace Tests\Feature;

use App\Models\ParticipantCareerDocument;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Standort;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Modules\ModuleStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParticipantCareerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_does_not_save_and_only_confirmation_creates_an_editable_copy(): void
    {
        [$user] = $this->context();
        Storage::fake('local');
        $existing = ParticipantCareerDocument::create(['person_id' => $user->person_id, 'title' => 'Bestehende Basis', 'type' => 'resume', 'template_key' => 'classic-navy-sans', 'content' => ['summary' => 'Original']]);
        $preview = $this->actingAs($user)->postJson(route('participant-portal.career-studio.import.preview'), ['type' => 'resume', 'file' => $this->pdf("Mina Muster\nBerufserfahrung\n01.2020 - heute Verkäuferin\nMuster GmbH\nKundenberatung\nSprachen\nDeutsch B2")])
            ->assertOk()->assertJsonPath('document.type', 'resume')->assertJsonPath('page_count', 1);
        $this->assertStringContainsString('Kundenberatung', $preview->json('source_text'));
        $entries = $preview->json('document.content.entries');
        $experience = collect($entries)->firstWhere('section', 'Berufserfahrung');
        $this->assertSame('01.2020 - heute', $experience['period']);
        $this->assertSame('Verkäuferin', $experience['title']);
        $this->assertDatabaseCount('participant_career_documents', 1);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $draft = $preview->json('document');
        $this->postJson(route('participant-portal.career-studio.import.confirm'), $draft)->assertUnprocessable()->assertJsonValidationErrors('reviewed');
        $draft['content']['full_name'] = 'Mina Muster';
        $draft['content']['summary'] = 'Geprüft und korrigiert';
        $saved = $this->postJson(route('participant-portal.career-studio.import.confirm'), [...$draft, 'reviewed' => true, 'person_id' => 999999, 'id' => $existing->id])->assertCreated()->json('document');
        $this->assertSame($user->person_id, $saved['person_id']);
        $this->assertSame('Geprüft und korrigiert', $saved['content']['summary']);
        $this->assertSame(['summary' => 'Original'], $existing->fresh()->content);
        $this->assertDatabaseCount('participant_career_documents', 2);
        $this->get(route('participant-portal.career-studio.download', $saved['id']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->postJson(route('participant-portal.career-studio.duplicate', $saved['id']))->assertCreated();
        $this->assertDatabaseCount('participant_career_documents', 3);
    }

    public function test_cover_letter_preserves_body_and_unassigned_header_for_review(): void
    {
        [$user] = $this->context();
        $preview = $this->actingAs($user)->postJson(route('participant-portal.career-studio.import.preview'), ['type' => 'cover_letter', 'file' => $this->pdf("Mina Muster\nBeispiel GmbH\nBewerbung als Verkäuferin\nSehr geehrte Damen und Herren,\n\nIch bringe Erfahrung mit.\n\nMit freundlichen Grüßen\nMina Muster")])->assertOk();
        $this->assertSame('Bewerbung als Verkäuferin', $preview->json('document.content.subject'));
        $this->assertStringContainsString('Beispiel GmbH', $preview->json('document.content.recipient'));
        $this->assertStringContainsString('Ich bringe Erfahrung mit.', $preview->json('document.content.body'));
        $this->assertStringContainsString('Mit freundlichen Grüßen', $preview->json('document.content.body'));
        $this->assertDatabaseCount('participant_career_documents', 0);
    }

    public function test_invalid_files_and_types_are_rejected_and_empty_pages_are_flagged(): void
    {
        [$user] = $this->context();
        $url = route('participant-portal.career-studio.import.preview');
        $this->actingAs($user)->postJson($url, ['type' => 'resume', 'file' => UploadedFile::fake()->createWithContent('fake.pdf', 'not a PDF')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($url, ['type' => 'resume', 'file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($url, ['type' => 'cover_page', 'file' => $this->pdf('Text')])->assertUnprocessable()->assertJsonValidationErrors('type');
        $preview = $this->postJson($url, ['type' => 'resume', 'file' => $this->pdf('')])->assertOk();
        $this->assertStringContainsString('kein Text erkannt', implode(' ', $preview->json('warnings')));
        $this->assertDatabaseCount('participant_career_documents', 0);
    }

    public function test_staff_can_import_for_visible_participant_and_edit_the_result(): void
    {
        [$portal, $project, $location] = $this->context();
        $staff = $this->staff($project, $location);
        $person = $portal->person;
        $this->actingAs($staff)->get(route('teilnehmer.career-studio.index', $person))->assertOk();
        $preview = $this->postJson(route('teilnehmer.career-studio.import.preview', $person), ['type' => 'resume', 'file' => $this->pdf("Mina Muster\nSprachen\nDeutsch B2")])->assertOk();
        $this->assertDatabaseCount('participant_career_documents', 0);
        $draft = $preview->json('document');
        $saved = $this->postJson(route('teilnehmer.career-studio.import.confirm', $person), [...$draft, 'reviewed' => true])->assertCreated()->json('document');
        $this->assertSame($person->id, $saved['person_id']);
        $this->assertSame($staff->id, $saved['created_by_user_id']);
        $draft['title'] = 'Für Stelle angepasst';
        $this->putJson(route('teilnehmer.career-studio.update', $saved['id']), $draft)->assertOk()->assertJsonPath('document.title', 'Für Stelle angepasst');
        $this->postJson(route('teilnehmer.career-studio.duplicate', $saved['id']))->assertCreated();
        $this->get(route('teilnehmer.career-studio.download', $saved['id']))->assertOk();
        $this->actingAs($portal)->get(route('participant-portal.career-studio.index'))->assertOk();
    }

    public function test_import_respects_portal_feature_and_foreign_documents_remain_hidden(): void
    {
        [$portal, $project] = $this->context();
        $other = Personen::factory()->create(['typ' => 'teilnehmer']);
        $foreign = ParticipantCareerDocument::create(['person_id' => $other->id, 'title' => 'Fremd', 'type' => 'resume', 'template_key' => 'classic-navy-sans', 'content' => []]);
        $this->actingAs($portal)->get(route('participant-portal.career-studio.download', $foreign))->assertNotFound();
        $this->postJson(route('participant-portal.career-studio.duplicate', $foreign))->assertNotFound();
        $project->update(['portal_feature_settings' => ['application_management' => false]]);
        $this->postJson(route('participant-portal.career-studio.import.preview'), ['type' => 'resume', 'file' => $this->pdf('Text')])->assertNotFound();
        $this->postJson(route('participant-portal.career-studio.import.confirm'), ['reviewed' => true])->assertNotFound();
        $this->assertDatabaseCount('participant_career_documents', 1);
    }

    public function test_staff_cannot_import_without_permission_or_for_another_project(): void
    {
        [$portal, $project, $location] = $this->context();
        $staff = $this->staff($project, $location);
        $other = Personen::factory()->create(['typ' => 'teilnehmer']);
        $url = route('teilnehmer.career-studio.import.preview', $other);
        $this->actingAs($staff)->postJson($url, ['type' => 'resume', 'file' => $this->pdf('Fremd')])->assertNotFound();
        $foreign = ParticipantCareerDocument::create(['person_id' => $other->id, 'title' => 'Fremd', 'type' => 'resume', 'template_key' => 'classic-navy-sans', 'content' => []]);
        $this->get(route('teilnehmer.career-studio.download', $foreign))->assertNotFound();
        $staff->revokePermissionTo('teilnehmer.update');
        $this->postJson(route('teilnehmer.career-studio.import.preview', $portal->person), ['type' => 'resume', 'file' => $this->pdf('Text')])->assertForbidden();
        $this->assertDatabaseCount('participant_career_documents', 1);
    }

    private function pdf(string $text): UploadedFile
    {
        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(180, 6, iconv('UTF-8', 'Windows-1252', $text));

        return UploadedFile::fake()->createWithContent('Meine Bewerbung.pdf', $pdf->Output('S'));
    }

    private function context(): array
    {
        $person = Personen::factory()->create(['typ' => 'teilnehmer', 'aktiv' => true]);
        $user = User::factory()->create(['person_id' => $person->id]);
        app(ModuleStateResolver::class)->set(SystemModule::where('key', 'participant_portal')->firstOrFail(), true, null, $user->id);
        $project = Projekt::factory()->create(['feature_settings' => ['participant_portal' => true, 'participant_management' => true], 'portal_feature_settings' => ['profile' => true, 'application_management' => true]]);
        $location = Standort::factory()->create();
        ProjektHasPersonen::create(['projekt_id' => $project->id, 'personen_id' => $person->id, 'standort_id' => $location->id, 'status' => 'aktiv']);

        return [$user, $project, $location];
    }

    private function staff(Projekt $project, Standort $location): User
    {
        $staff = User::factory()->create();
        $this->grantTestPermission($staff, 'teilnehmer.update');
        $role = Role::create(['name' => 'Import-Mitarbeiter', 'guard_name' => 'web', 'color' => '#123456']);
        RoleDataAccessSetting::create(['role_id' => $role->id, 'team_scope' => 'own_projects', 'participant_scope' => 'all']);
        $staff->assignRole($role);
        ProjektHasPersonen::create(['projekt_id' => $project->id, 'personen_id' => $staff->person_id, 'standort_id' => $location->id, 'status' => 'aktiv']);
        $staff->update(['current_team_id' => $project->id]);

        return $staff;
    }
}
