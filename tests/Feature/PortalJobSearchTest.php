<?php

namespace Tests\Feature;

use App\Models\ParticipantApplication;
use App\Models\Berechtigungskategorie;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Standort;
use App\Models\SystemModule;
use App\Models\User;
use App\Services\Jobs\BundesagenturJobSearchService;
use App\Services\Modules\ModuleStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PortalJobSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_bookmark_and_application_flow_are_bound_to_own_enabled_participation(): void
    {
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $user = User::factory()->create(['person_id' => $participant->id]);
        app(ModuleStateResolver::class)->set(SystemModule::where('key', 'participant_portal')->firstOrFail(), true, null, $user->id);
        $location = Standort::factory()->create();
        $project = Projekt::factory()->create([
            'portal_feature_settings' => ['job_search' => true, 'application_management' => true],
        ]);
        $participation = $this->assign($project, $participant, $location);

        Http::fake([
            'https://rest.arbeitsagentur.de/*' => Http::response([
                'maxErgebnisse' => 1,
                'stellenangebote' => [[
                    'refnr' => '10000-TEST-S',
                    'titel' => 'Gärtner/in',
                    'arbeitgeber' => 'Musterbetrieb GmbH',
                    'arbeitsort' => ['plz' => '66111', 'ort' => 'Saarbrücken'],
                    'aktuelleVeroeffentlichungsdatum' => '2026-07-10',
                    'externeUrl' => 'https://example.test/job',
                ]],
            ], 200),
        ]);

        $search = $this->actingAs($user)->getJson(route('participant-portal.jobs.search', [
            'project_person_id' => $participation->id,
            'was' => 'Gärtner',
            'wo' => 'Saarbrücken',
            'umkreis' => 25,
        ]))->assertOk()
            ->assertJsonPath('items.0.external_ref', '10000-TEST-S')
            ->assertJsonPath('items.0.location', '66111 Saarbrücken');

        Http::assertSent(fn ($request) => $request->hasHeader('X-API-Key', 'jobboerse-jobsuche') && $request['was'] === 'Gärtner');
        $job = $search->json('items.0');

        $this->postJson(route('participant-portal.jobs.bookmarks.store'), [
            'project_person_id' => $participation->id,
            ...$job,
        ])->assertCreated()->assertJsonPath('bookmark.external_ref', '10000-TEST-S');

        $applicationResponse = $this->postJson(route('participant-portal.applications.store'), [
            'project_person_id' => $participation->id,
            ...$job,
            'next_action_at' => '2026-07-15',
            'notes' => 'Unterlagen vorbereiten',
        ])->assertCreated()->assertJsonPath('application.status', 'draft');

        $application = ParticipantApplication::findOrFail($applicationResponse->json('application.id'));
        $this->assertDatabaseHas('participant_application_status_histories', [
            'application_id' => $application->id,
            'from_status' => null,
            'to_status' => 'draft',
            'changed_by_user_id' => $user->id,
        ]);

        $this->postJson(route('participant-portal.applications.store'), [
            'project_person_id' => $participation->id,
            ...$job,
        ])->assertUnprocessable()->assertJsonValidationErrors('external_ref');

        $this->putJson(route('participant-portal.applications.update', $application), [
            'status' => 'sent',
            'applied_at' => '2026-07-12',
            'next_action_at' => '2026-07-20',
            'notes' => 'Bewerbung versendet',
        ])->assertOk()->assertJsonPath('application.status', 'sent');

        $this->assertDatabaseHas('participant_application_status_histories', [
            'application_id' => $application->id,
            'from_status' => 'draft',
            'to_status' => 'sent',
        ]);

        $staff = User::factory()->create();
        $this->grantStaffAccess($staff);
        $this->assign($project, $staff->person, $location);
        $staff->update(['current_team_id' => $project->id]);
        $this->actingAs($staff)->putJson(route('teilnehmer.applications.update', $application), [
            'status' => 'interview',
            'applied_at' => '2026-07-12',
            'next_action_at' => '2026-07-25',
            'notes' => 'Vorstellungsgespräch vereinbart',
        ])->assertOk()->assertJsonPath('application.status', 'interview');

        $this->assertDatabaseHas('participant_application_status_histories', [
            'application_id' => $application->id,
            'from_status' => 'sent',
            'to_status' => 'interview',
            'changed_by_user_id' => $staff->id,
        ]);

        $foreignParticipant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $foreignParticipation = $this->assign($project, $foreignParticipant, $location);
        $this->actingAs($user)->getJson(route('participant-portal.jobs.search', ['project_person_id' => $foreignParticipation->id]))->assertNotFound();
    }

    public function test_external_service_failure_is_returned_as_controlled_unavailable_response(): void
    {
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $user = User::factory()->create(['person_id' => $participant->id]);
        app(ModuleStateResolver::class)->set(SystemModule::where('key', 'participant_portal')->firstOrFail(), true, null, $user->id);
        $project = Projekt::factory()->create(['portal_feature_settings' => ['job_search' => true]]);
        $participation = $this->assign($project, $participant, Standort::factory()->create());
        Http::fake(['*' => Http::response(['error' => 'upstream'], 503)]);

        $this->actingAs($user)->getJson(route('participant-portal.jobs.search', ['project_person_id' => $participation->id]))
            ->assertStatus(503)
            ->assertJsonPath('message', 'Die externe Jobsuche ist vorübergehend nicht erreichbar. Bitte versuchen Sie es später erneut.');
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

    private function grantStaffAccess(User $user): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(['name' => 'Bewerbungen'], ['beschreibung' => '']);
        Permission::query()->updateOrCreate(
            ['name' => 'teilnehmer.update', 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::query()->create(['name' => 'Bewerbungs-Test-' . uniqid(), 'guard_name' => 'web', 'color' => '#2563eb']);
        RoleDataAccessSetting::query()->create(['role_id' => $role->id, 'team_scope' => 'own_projects', 'participant_scope' => 'all']);
        $user->assignRole($role);
        $user->givePermissionTo('teilnehmer.update');
    }
}
