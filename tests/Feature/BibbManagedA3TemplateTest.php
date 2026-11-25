<?php

namespace Tests\Feature;

use App\Models\Dokumente;
use App\Models\Partner;
use App\Models\Projekt;
use App\Models\User;
use App\Services\Bop\BibbAttendanceWordTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BibbManagedA3TemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bibb_a3_export_uses_the_project_bound_managed_template(): void
    {
        $template = Dokumente::query()
            ->where('export_permission', BibbAttendanceWordTemplate::A3_EXPORT_PERMISSION)
            ->firstOrFail();
        $this->assertSame('Anwesenheitsliste BO/BIBB DIN A3', $template->name);

        $user = User::factory()->create();
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');
        $project = Projekt::factory()->create(['name' => 'BOP Test']);
        $school = Partner::create(['name' => 'BIBB Testschule']);
        $project->partners()->attach($school);
        $project->teilnehmer()->attach($user->person_id, ['status' => 'aktiv']);
        $project->dokumente()->attach($template, [
            'gruppen_export' => true,
            'serienbrief' => true,
            'sort_order' => 0,
        ]);
        $user->update(['current_team_id' => $project->id]);
        $template->update(['aktiv' => false]);

        $this->actingAs($user->fresh())->postJson(route('anwesenheitsliste.POBO.bibb.export.word'), [
            'exportFormat' => 'A3',
            'schuleIdInputBibb' => $school->id,
            'schuljahrInputBibb' => '2026/2027',
            'teilInputBibb' => '1',
            'days' => [['date' => '2026-11-23', 'selected' => true]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('vorlage');
    }
}
