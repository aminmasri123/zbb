<?php

namespace Tests\Feature;

use App\Models\InternshipEmailTemplate;
use App\Models\Personen;
use App\Models\PersonenHasBildungsmassnahmen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InternshipEmailWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_configure_all_templates_and_replace_an_attachment(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $this->grantTestPermission($admin, 'berechtigung.update');

        $this->actingAs($admin)
            ->get(route('internship-email-templates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Einstellung/InternshipEmailTemplates/Index')
                ->has('templates', 3)
                ->where('templates.0.key', 'initial'));

        $this->actingAs($admin)->post(route('internship-email-templates.update', 'reminder_1'), [
            'subject' => 'Bitte antworten: {{teilnehmer_name}}',
            'body' => 'Erinnerung für {{betrieb}} von {{absender_email}}',
            'attachment' => UploadedFile::fake()->create('Merkblatt.pdf', 150, 'application/pdf'),
        ])->assertRedirect();

        $template = InternshipEmailTemplate::query()->where('key', 'reminder_1')->firstOrFail();
        $this->assertSame('Bitte antworten: {{teilnehmer_name}}', $template->subject);
        $this->assertSame('Merkblatt.pdf', $template->attachment_original_name);
        Storage::disk('local')->assertExists($template->attachment_path);

        $this->actingAs($admin)
            ->get(route('internship-email-templates.attachment.download', $template))
            ->assertOk()
            ->assertDownload('Merkblatt.pdf');
    }

    public function test_staff_can_prepare_initial_and_both_reminder_emails_for_outlook(): void
    {
        [$staff, $project, $participant, $participation] = $this->context();
        $measure = PersonenHasBildungsmassnahmen::query()->create([
            'person_id' => $participant->id,
            'projekt_person_id' => $participation->id,
            'typ' => 'Praktikum',
            'placement_type' => 'external',
            'traeger' => 'Muster GmbH',
            'contact_name' => 'Frau Beispiel',
            'contact_email' => 'praktikum@example.org',
            'start' => '2026-09-01',
            'end' => '2026-09-30',
            'status' => 'geplant',
        ]);

        InternshipEmailTemplate::query()->where('key', 'initial')->update([
            'subject' => '{{teilnehmer_name}} bei {{betrieb}}',
            'body' => '{{teilnehmer_vorname}}|{{teilnehmer_nachname}}|{{ansprechpartner}}|{{startdatum}}|{{enddatum}}|{{absender_name}}|{{absender_email}}',
        ]);

        $this->actingAs($staff)
            ->postJson(route('teilnehmer.praktikum.email.prepare', $measure), ['template_key' => 'initial'])
            ->assertOk()
            ->assertJsonPath('recipient', 'praktikum@example.org')
            ->assertJsonPath('sender_email', 'sachbearbeitung@zbb-saar.de')
            ->assertJsonPath('subject', 'Tina Teilnehmer bei Muster GmbH')
            ->assertJsonPath('body', 'Tina|Teilnehmer|Frau Beispiel|01.09.2026|30.09.2026|Max Muster|sachbearbeitung@zbb-saar.de');

        foreach (['reminder_1', 'reminder_2'] as $templateKey) {
            $this->actingAs($staff)
                ->postJson(route('teilnehmer.praktikum.email.prepare', $measure), ['template_key' => $templateKey])
                ->assertOk()
                ->assertJsonPath('recipient', 'praktikum@example.org');
        }
    }

    public function test_email_preparation_requires_external_internship_with_recipient_in_current_project(): void
    {
        [$staff, $project, $participant, $participation, $location] = $this->context();
        $internal = PersonenHasBildungsmassnahmen::query()->create([
            'person_id' => $participant->id,
            'projekt_person_id' => $participation->id,
            'typ' => 'Praktikum',
            'placement_type' => 'internal',
            'traeger' => $project->name,
            'start' => '2026-09-01',
            'end' => '2026-09-30',
            'status' => 'geplant',
        ]);

        $this->actingAs($staff)
            ->postJson(route('teilnehmer.praktikum.email.prepare', $internal), ['template_key' => 'initial'])
            ->assertUnprocessable();

        $foreignProject = Projekt::factory()->create();
        $foreignParticipation = $this->assign($foreignProject, $participant, $location);
        $foreign = PersonenHasBildungsmassnahmen::query()->create([
            'person_id' => $participant->id,
            'projekt_person_id' => $foreignParticipation->id,
            'typ' => 'Praktikum',
            'placement_type' => 'external',
            'traeger' => 'Fremdbetrieb',
            'contact_email' => 'fremd@example.org',
            'start' => '2026-09-01',
            'end' => '2026-09-30',
            'status' => 'geplant',
        ]);

        $this->actingAs($staff)
            ->postJson(route('teilnehmer.praktikum.email.prepare', $foreign), ['template_key' => 'initial'])
            ->assertNotFound();
    }

    private function context(): array
    {
        $staffPerson = Personen::factory()->create([
            'typ' => 'mitarbeiter',
            'vorname' => 'Max',
            'nachname' => 'Muster',
        ]);
        $staff = User::factory()->create([
            'person_id' => $staffPerson->id,
            'email' => 'sachbearbeitung@zbb-saar.de',
            'username' => 'Max Muster',
        ]);
        $this->grantTestPermission($staff, 'teilnehmer.update');
        $role = Role::query()->create([
            'name' => 'Praktikums-E-Mail-Test-'.uniqid(),
            'guard_name' => 'web',
            'color' => '#123456',
        ]);
        RoleDataAccessSetting::query()->create([
            'role_id' => $role->id,
            'team_scope' => 'own_projects',
            'participant_scope' => 'own_projects',
        ]);
        $staff->assignRole($role);
        $location = Standort::factory()->create();
        $project = Projekt::factory()->create();
        $this->assign($project, $staffPerson, $location);
        $staff->update(['current_team_id' => $project->id]);
        $participant = Personen::factory()->create([
            'typ' => 'teilnehmer',
            'vorname' => 'Tina',
            'nachname' => 'Teilnehmer',
        ]);
        $participation = $this->assign($project, $participant, $location);

        return [$staff, $project, $participant, $participation, $location];
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
}
