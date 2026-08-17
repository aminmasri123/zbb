<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Personen;
use App\Models\PersonenHasBildungsmassnahmen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EducationMeasureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_measure_has_contact_follow_up_status_history_and_archiving(): void
    {
        [$user, $project, $participant] = $this->context();
        $payload = ['teilnehmer_id' => $participant->id, 'typ' => 'Praktikum', 'traeger' => 'Muster GmbH', 'contact_name' => 'Frau Beispiel', 'contact_email' => 'kontakt@example.org', 'contact_phone' => '030 123', 'start' => '2026-08-01', 'end' => '2026-08-31', 'weekly_hours' => 35, 'next_follow_up_at' => '2026-08-15', 'objective' => 'Berufsfeld erproben', 'bemerkung' => 'Erstgespräch geführt', 'status' => 'geplant'];
        $response = $this->actingAs($user)->postJson(route('teilnehmer.praktikum.store'), $payload)->assertCreated()->assertJsonPath('data.contact_name', 'Frau Beispiel')->assertJsonCount(1, 'data.status_history');
        $measure = PersonenHasBildungsmassnahmen::findOrFail($response->json('data.id'));

        $this->actingAs($user)->get(route('teilnehmer.edit', $participant))->assertOk();

        $this->actingAs($user)->putJson(route('teilnehmer.praktikum.update', $measure), [...$payload, 'status' => 'laufend', 'status_note' => 'Angetreten'])->assertOk()->assertJsonCount(2, 'data.status_history');
        $this->actingAs($user)->putJson(route('teilnehmer.praktikum.update', $measure), [...$payload, 'status' => 'abgeschlossen', 'result' => null])->assertUnprocessable()->assertJsonValidationErrors('result');
        $this->actingAs($user)->putJson(route('teilnehmer.praktikum.update', $measure), [...$payload, 'status' => 'abgeschlossen', 'result' => 'Ziel erreicht', 'status_note' => 'Abschlussgespräch erfolgt'])->assertOk();
        $this->actingAs($user)->putJson(route('teilnehmer.praktikum.update', $measure), [...$payload, 'status' => 'laufend'])->assertUnprocessable();

        $this->actingAs($user)->deleteJson(route('teilnehmer.praktikum.destroy', $measure))->assertOk();
        $this->assertNotNull($measure->fresh()->archived_at);
        $this->assertDatabaseCount('education_measure_status_history', 3);
    }

    public function test_foreign_project_measure_cannot_be_changed_or_archived(): void
    {
        [$user, $project, $participant, $location] = $this->context();
        $foreign = Projekt::factory()->create();
        $foreignParticipation = $this->assign($foreign, $participant, $location);
        $measure = PersonenHasBildungsmassnahmen::query()->create(['person_id' => $participant->id, 'projekt_person_id' => $foreignParticipation->id, 'typ' => 'Praktikum', 'start' => '2026-08-01', 'end' => '2026-08-31', 'status' => 'geplant']);
        $this->actingAs($user)->putJson(route('teilnehmer.praktikum.update', $measure), ['typ' => 'Praktikum', 'start' => '2026-08-01', 'end' => '2026-08-31', 'status' => 'laufend'])->assertNotFound();
        $this->actingAs($user)->deleteJson(route('teilnehmer.praktikum.destroy', $measure))->assertNotFound();
    }

    public function test_internal_internship_uses_host_project_and_assigned_supervisor(): void
    {
        [$user, $project, $participant, $location] = $this->context();
        $hostProject = Projekt::factory()->create(['name' => 'Digitalwerkstatt', 'aktiv' => true]);
        $supervisor = Personen::factory()->create(['typ' => 'mitarbeiter', 'vorname' => 'Amin', 'nachname' => 'Masri']);
        $this->assign($hostProject, $supervisor, $location);

        $response = $this->actingAs($user)->postJson(route('teilnehmer.praktikum.store'), [
            'teilnehmer_id' => $participant->id,
            'typ' => 'Praktikum',
            'placement_type' => 'internal',
            'host_project_id' => $hostProject->id,
            'supervisor_person_id' => $supervisor->id,
            'host_address' => 'Ernst-Abbe-Straße 9, 66115 Saarbrücken',
            'department' => 'Mediengestaltung',
            'internship_kind' => 'orientation',
            'occupation' => 'Mediengestalter:in',
            'activities' => "Mitarbeit an Medienprojekten\nVorbereitung von Arbeitsmaterialien",
            'assessment' => 'Die vereinbarten Aufgaben wurden zuverlässig durchgeführt.',
            'start' => '2026-08-01',
            'end' => '2026-08-31',
            'status' => 'geplant',
        ])->assertCreated();

        $response->assertJsonPath('data.placement_type', 'internal')
            ->assertJsonPath('data.traeger', 'Digitalwerkstatt')
            ->assertJsonPath('data.host_project.name', 'Digitalwerkstatt')
            ->assertJsonPath('data.supervisor.nachname', 'Masri');

        // Historische Mehrfachzuordnungen dürfen dieselbe Betreuungsperson
        // in der Auswahlliste nicht doppelt anzeigen.
        $this->assign($hostProject, $supervisor, $location);

        $this->actingAs($user)->get(route('internships.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Praktikum/Index')
                ->where('stats.internal', 1)
                ->has('internships.data', 1)
                ->where('internships.data.0.host_project.name', 'Digitalwerkstatt')
                ->where('hostProjects', function ($projects) use ($hostProject, $supervisor) {
                    $project = collect($projects)->firstWhere('id', $hostProject->id);

                    return collect($project['mitarbeiter'] ?? [])
                        ->where('id', $supervisor->id)
                        ->count() === 1;
                }));
    }

    public function test_internal_supervisor_must_belong_to_host_project(): void
    {
        [$user, $project, $participant] = $this->context();
        $hostProject = Projekt::factory()->create(['aktiv' => true]);
        $unassignedSupervisor = Personen::factory()->create(['typ' => 'mitarbeiter']);

        $this->actingAs($user)->postJson(route('teilnehmer.praktikum.store'), [
            'teilnehmer_id' => $participant->id,
            'typ' => 'Praktikum',
            'placement_type' => 'internal',
            'host_project_id' => $hostProject->id,
            'supervisor_person_id' => $unassignedSupervisor->id,
            'start' => '2026-08-01',
            'end' => '2026-08-31',
            'status' => 'geplant',
        ])->assertUnprocessable();
    }

    public function test_intern_can_be_created_from_bop_project_without_school_assignment(): void
    {
        [$user, $project, $existingParticipant, $location] = $this->context();
        $project->update(['name' => 'BOP Saar']);

        $response = $this->actingAs($user)->post(route('internships.store'), [
            'vorname' => 'Nora',
            'nachname' => 'Praktikum',
            'geschlecht' => 'w',
            'geburtsdatum' => '2004-05-06',
            'standort_id' => $location->id,
            'strasse' => 'Beispielstraße',
            'hausnummer' => '12',
            'plz' => '66111',
            'stadt' => 'Saarbrücken',
            'land' => 'Deutschland',
            'placement_type' => 'external',
            'traeger' => 'Praxisbetrieb GmbH',
            'host_address' => 'Werkstraße 2, 66115 Saarbrücken',
            'start' => '2026-09-01',
            'end' => '2026-09-30',
            'weekly_hours' => 35,
            'status' => 'geplant',
        ]);

        $participant = Personen::query()->where('vorname', 'Nora')->where('nachname', 'Praktikum')->firstOrFail();
        $response->assertRedirect(route('teilnehmer.edit', $participant));
        $this->assertDatabaseHas('projekt_has_personens', [
            'projekt_id' => $project->id,
            'personen_id' => $participant->id,
            'standort_id' => $location->id,
        ]);
        $this->assertDatabaseMissing('personen_ist_schuelers', ['person_id' => $participant->id]);
        $this->assertDatabaseHas('personen_has_bildungsmassnahmens', [
            'person_id' => $participant->id,
            'typ' => 'Praktikum',
            'placement_type' => 'external',
            'traeger' => 'Praxisbetrieb GmbH',
        ]);
    }

    public function test_contract_and_completed_certificate_can_be_downloaded(): void
    {
        [$user, $project, $participant, $location] = $this->context();
        $participant->update(['geburtsdatum' => '2001-02-03', 'geschlecht' => 'w']);
        $participant->adresses()->create([
            'strasse' => 'Musterstraße',
            'hausnummer' => '4',
            'plz' => '66111',
            'stadt' => 'Saarbrücken',
        ]);
        $measure = PersonenHasBildungsmassnahmen::query()->create([
            'person_id' => $participant->id,
            'projekt_person_id' => ProjektHasPersonen::query()->where('projekt_id', $project->id)->where('personen_id', $participant->id)->value('id'),
            'typ' => 'Praktikum',
            'placement_type' => 'external',
            'traeger' => 'Muster GmbH',
            'host_address' => 'Werkstraße 2, 66115 Saarbrücken',
            'contact_name' => 'Frau Beispiel',
            'contact_email' => 'kontakt@example.org',
            'contact_phone' => '0681 123456',
            'start' => '2026-08-01',
            'end' => '2026-08-31',
            'weekly_hours' => 35,
            'activities' => "Kennenlernen der betrieblichen Abläufe\nMitarbeit im Tagesgeschäft",
            'assessment' => "Die vereinbarten Aufgaben wurden zuverlässig durchgeführt.\nDas Verhalten gegenüber dem Team war respektvoll.",
            'result' => 'Erfolgreich abgeschlossen',
            'status' => 'abgeschlossen',
        ]);

        $this->actingAs($user)->get(route('teilnehmer.praktikum.contract', $measure))
            ->assertOk()
            ->assertDownload();
        $this->actingAs($user)->get(route('teilnehmer.praktikum.certificate', $measure))
            ->assertOk()
            ->assertDownload();
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $this->grant($user);
        $location = Standort::factory()->create();
        $project = Projekt::factory()->create();
        $this->assign($project, $user->person, $location);
        $user->update(['current_team_id' => $project->id]);
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $this->assign($project, $participant, $location);

        return [$user, $project, $participant, $location];
    }

    private function assign(Projekt $project, Personen $person, Standort $location): ProjektHasPersonen
    {
        return ProjektHasPersonen::query()->create(['projekt_id' => $project->id, 'personen_id' => $person->id, 'standort_id' => $location->id, 'status' => 'aktiv']);
    }

    private function grant(User $user): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(['name' => 'Praktikumsverlauf'], ['beschreibung' => '']);
        Permission::query()->updateOrCreate(['name' => 'teilnehmer.update', 'guard_name' => 'web'], ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]);
        Permission::query()->updateOrCreate(['name' => 'teilnehmer.index', 'guard_name' => 'web'], ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::query()->create(['name' => 'EducationMeasure-'.uniqid(), 'guard_name' => 'web', 'color' => '#123456']);
        RoleDataAccessSetting::query()->create(['role_id' => $role->id, 'team_scope' => 'own_projects', 'participant_scope' => 'all']);
        $user->assignRole($role);
        $user->givePermissionTo(['teilnehmer.update', 'teilnehmer.index']);
    }
}
