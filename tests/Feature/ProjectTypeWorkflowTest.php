<?php

namespace Tests\Feature;

use App\Models\Abteilung;
use App\Models\Berechtigungskategorie;
use App\Models\ProjectType;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectTypeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_project_type_data_is_preserved_but_not_changed_by_project_form(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'projekt.update');
        $type = ProjectType::query()->where('key', 'bop')->firstOrFail();
        $project = Projekt::factory()->create(['project_type_id' => $type->id]);

        $this->actingAs($user)->putJson(
            route('projekt.update', $project->id),
            $this->projectPayload($project->abteilung, $project->name)
        )->assertOk();

        $this->assertSame($type->id, $project->fresh()->project_type_id);
    }

    public function test_new_project_is_configured_directly_without_project_type_assignment(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'projekt.store');
        $department = Abteilung::factory()->create();
        $legacyType = ProjectType::query()->where('key', 'bvb_reha')->firstOrFail();

        $payload = $this->projectPayload($department, 'Direkt konfiguriertes Projekt');
        $payload['project_type_id'] = $legacyType->id;
        $payload['klassenbuch_aktiv'] = true;
        $payload['potenzialanalyse_aktiv'] = true;
        $payload['potenzialanalyse_tage'] = 5;

        $this->actingAs($user)->postJson(route('projekt.store'), $payload)
            ->assertCreated();

        $project = Projekt::query()->where('name', 'Direkt konfiguriertes Projekt')->firstOrFail();
        $this->assertNull($project->project_type_id);
        $this->assertTrue((bool) $project->klassenbuch_aktiv);
        $this->assertTrue((bool) $project->potenzialanalyse_aktiv);
        $this->assertSame(5, $project->potenzialanalyse_tage);
    }

    public function test_project_type_catalog_remains_as_non_destructive_legacy_data(): void
    {
        $this->assertSame(
            ['asa_flex', 'bae', 'bop', 'bvb', 'bvb_reha', 'coaching'],
            ProjectType::query()->orderBy('key')->pluck('key')->all()
        );
    }

    private function projectPayload(Abteilung $department, string $name): array
    {
        return [
            'name' => $name,
            'abteilung' => $department->id,
            'antragsdatum' => '2027-01-01',
            'starttermin' => '2027-02-01',
            'anfangsdatum' => '2027-03-01',
            'endtermin' => '2027-11-30',
            'enddatum' => '2027-12-15',
            'bereiche' => [],
        ];
    }

    private function givePermission(User $user, string $name): void
    {
        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Projektkonfiguration'],
            ['beschreibung' => '']
        );
        $permission = Permission::query()->updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo($permission);
    }
}
