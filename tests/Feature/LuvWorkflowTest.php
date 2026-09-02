<?php

namespace Tests\Feature;

use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LuvWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_luv_is_created_as_draft_reviewed_and_immutably_approved(): void
    {
        [$user, , $participant] = $this->context();

        $response = $this->actingAs($user)->postJson(route('projekthasteilnehmer.luv.store'), [
            'teilnehmer_id' => $participant->id,
            'typ' => 'Start',
            'von' => '2026-08-01',
            'bis' => '2026-08-31',
            'ausgangssituation' => 'Beobachtete Ausgangssituation.',
            'zielvereinbarung' => 'Vereinbarter nächster Schritt.',
        ])->assertCreated()
            ->assertJsonPath('luv.status', 'draft')
            ->assertJsonPath('luv.version', 1);

        $luvId = $response->json('luv.id');

        $export = $this->actingAs($user)->get(route('projekthasteilnehmer.luv.export', $luvId))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $export->getContent());

        $this->actingAs($user)->putJson(route('projekthasteilnehmer.luv.workflow.update', $luvId), [
            'status' => 'reviewed',
        ])->assertOk()->assertJsonPath('luv.status', 'reviewed');

        $this->actingAs($user)->putJson(route('projekthasteilnehmer.luv.workflow.update', $luvId), [
            'status' => 'approved',
        ])->assertUnprocessable();

        $this->actingAs($user)->putJson(route('projekthasteilnehmer.luv.workflow.update', $luvId), [
            'status' => 'approved',
            'discussed_on' => '2026-09-01',
            'consent_confirmed' => true,
        ])->assertOk()->assertJsonPath('luv.status', 'approved');

        $this->actingAs($user)->putJson(route('projekthasteilnehmer.luv.workflow.update', $luvId), [
            'status' => 'draft',
        ])->assertConflict();

        $this->actingAs($user)->deleteJson(route('projekthasteilnehmer.luv.destroy', $luvId))
            ->assertConflict();
    }

    public function test_luv_from_another_active_project_is_not_accessible(): void
    {
        [$user, , $participant] = $this->context();
        $foreignProject = Projekt::factory()->create();
        $foreignParticipation = ProjektHasPersonen::query()->create([
            'projekt_id' => $foreignProject->id,
            'personen_id' => $participant->id,
            'status' => 'aktiv',
        ]);
        $foreignLuv = $foreignParticipation->luv()->create([
            'typ' => 'Start',
            'version' => 1,
            'status' => 'draft',
            'von' => '2026-08-01',
            'bis' => '2026-08-31',
            'ausgangssituation' => '',
            'zielvereinbarung' => '',
            'qualifikationen' => '',
        ]);

        $this->actingAs($user)->get(route('projekthasteilnehmer.luv.export', $foreignLuv))
            ->assertNotFound();
        $this->actingAs($user)->deleteJson(route('projekthasteilnehmer.luv.destroy', $foreignLuv))
            ->assertNotFound();
    }

    /** @return array{User, Projekt, Personen} */
    private function context(): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create();
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $user->person_id,
            'status' => 'aktiv',
        ]);
        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $participant->id,
            'status' => 'aktiv',
        ]);
        $user->update(['current_team_id' => $project->id]);

        $role = Role::query()->create([
            'name' => 'LuV-'.uniqid(),
            'guard_name' => 'web',
            'color' => '#123456',
        ]);
        RoleDataAccessSetting::query()->create([
            'role_id' => $role->id,
            'team_scope' => 'own_projects',
            'participant_scope' => 'own_projects',
        ]);
        $user->assignRole($role);
        foreach ([
            'projekthasteilnehmer.luv.store',
            'projekthasteilnehmer.luv.update',
            'projekthasteilnehmer.luv.destroy',
            'projekthasteilnehmer.luv.export',
        ] as $permission) {
            $this->grantTestPermission($user, $permission);
        }

        return [$user->fresh(), $project->fresh(), $participant->fresh()];
    }
}
