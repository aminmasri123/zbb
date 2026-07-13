<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Personen;
use App\Models\ParticipationCompletionReport;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\RoleDataAccessSetting;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ParticipationCompletionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_versioned_report_requires_checklist_and_four_eyes_before_status_changes(): void
    {
        [$author, $approver, $project, $participation] = $this->context();

        $definition = $this->actingAs($author)->putJson(route('projekt.completion-checklist.update', $project), ['items' => [[
            'id' => null, 'label' => 'Abschlussgespräch geführt', 'description' => 'Ergebnis dokumentiert', 'required' => true, 'sort_order' => 0,
        ]]])->assertOk();
        $itemId = $definition->json('items.0.id');

        $reportResponse = $this->actingAs($author)->postJson(route('teilnehmer.completion-reports.submit', $participation), [
            'completion_type' => 'completed', 'exit_date' => today()->toDateString(), 'outcome' => 'Ausbildung aufgenommen',
            'summary' => 'Die Teilnahmeziele wurden erreicht.', 'recommendations' => 'Nachbetreuung vereinbart.',
        ])->assertCreated()->assertJsonPath('report.version', 1)->assertJsonPath('report.status', 'submitted');
        $report = ParticipationCompletionReport::findOrFail($reportResponse->json('report.id'));
        $this->assertSame(hash('sha256', json_encode($report->snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), $report->snapshot_sha256);

        $this->actingAs($approver)->putJson(route('teilnehmer.completion-reports.decide', $report), ['decision' => 'approved'])->assertUnprocessable();
        $this->actingAs($author)->putJson(route('teilnehmer.completion-checklist.update', ['participation' => $participation, 'item' => $itemId]), ['completed' => true, 'note' => 'Am '.today()->format('d.m.Y').' geführt'])->assertOk();
        $this->actingAs($author)->putJson(route('teilnehmer.completion-reports.decide', $report), ['decision' => 'approved'])->assertUnprocessable();

        $this->actingAs($approver)->putJson(route('teilnehmer.completion-reports.decide', $report), ['decision' => 'approved', 'decision_note' => 'Geprüft'])->assertOk()->assertJsonPath('report.status', 'approved');
        $this->assertSame('abgeschlossen', $participation->fresh()->status);
        $this->actingAs($approver)->get(route('teilnehmer.completion-reports.export', $report))->assertOk()->assertHeader('content-type', 'application/json; charset=UTF-8');

        $this->actingAs($author)->postJson(route('teilnehmer.completion-reports.submit', $participation), [
            'completion_type' => 'completed', 'exit_date' => today()->toDateString(), 'outcome' => 'Korrigierter Verbleib', 'summary' => 'Korrigierte zweite Fassung.',
        ])->assertCreated()->assertJsonPath('report.version', 2);
        $this->assertDatabaseHas('participation_completion_reports', ['project_person_id' => $participation->id, 'version' => 1, 'status' => 'approved']);
    }

    public function test_foreign_project_cannot_manage_or_export_completion(): void
    {
        [$author, $approver, $project, $participation, $location] = $this->context();
        $report = ParticipationCompletionReport::query()->create(['project_person_id' => $participation->id, 'version' => 1, 'status' => 'approved', 'completion_type' => 'completed', 'exit_date' => today(), 'outcome' => 'Ergebnis', 'summary' => 'Bericht', 'snapshot' => [], 'snapshot_sha256' => hash('sha256', '[]'), 'created_by_user_id' => $author->id]);
        $foreign = Projekt::factory()->create();
        $this->assign($foreign, $author->person, $location);
        $author->update(['current_team_id' => $foreign->id]);
        $this->actingAs($author)->get(route('teilnehmer.completion-reports.export', $report))->assertNotFound();
        $this->actingAs($author)->postJson(route('teilnehmer.completion-reports.submit', $participation), ['completion_type' => 'completed'])->assertNotFound();
    }

    private function context(): array
    {
        $author = User::factory()->create(); $approver = User::factory()->create();
        $this->grant($author); $this->grant($approver);
        $location = Standort::factory()->create();
        $project = Projekt::factory()->create(['feature_settings' => ['participant_management' => true, 'completion_management' => true]]);
        $this->assign($project, $author->person, $location); $this->assign($project, $approver->person, $location);
        $author->update(['current_team_id' => $project->id]); $approver->update(['current_team_id' => $project->id]);
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);
        $participation = $this->assign($project, $participant, $location);
        return [$author, $approver, $project, $participation, $location];
    }

    private function assign(Projekt $project, Personen $person, Standort $location): ProjektHasPersonen
    {
        return ProjektHasPersonen::query()->create(['projekt_id' => $project->id, 'personen_id' => $person->id, 'standort_id' => $location->id, 'status' => 'aktiv']);
    }

    private function grant(User $user): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(['name' => 'Teilnahmeabschluss'], ['beschreibung' => '']);
        foreach (['projekt.update', 'teilnehmer.update'] as $name) Permission::query()->updateOrCreate(['name' => $name, 'guard_name' => 'web'], ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::query()->create(['name' => 'Completion-'.uniqid(), 'guard_name' => 'web', 'color' => '#123456']);
        RoleDataAccessSetting::query()->create(['role_id' => $role->id, 'team_scope' => 'own_projects', 'participant_scope' => 'all']);
        $user->assignRole($role); $user->givePermissionTo(['projekt.update', 'teilnehmer.update']);
    }
}
