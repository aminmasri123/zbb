<?php

namespace Tests\Feature;

use App\Models\Dokumente;
use App\Models\Partner;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaAttendanceManagedTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_pa_attendance_uses_only_the_project_bound_managed_word_template(): void
    {
        $this->assertDatabaseMissing('dokumentes', [
            'dateipfad' => '/vorlage/projekte/bop/word/Anwesenheit_PA.docx',
        ]);
        $this->assertDatabaseMissing('dokumentes', [
            'dateipfad' => '/vorlage/projekte/bop/excel/Anwesenheitsliste-PA.xlsx',
        ]);

        $template = Dokumente::query()
            ->where('dateipfad', '/vorlage/projekte/bop/word/pa/Anwesenheitsliste-PA.docx')
            ->firstOrFail();
        $this->assertSame('Anwesenheitsliste PA', $template->name);
        $this->assertSame('anwesenheit.abrechnung', $template->export_permission);

        $user = User::factory()->create();
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');
        $project = Projekt::factory()->create(['name' => 'BOP Test']);
        $school = Partner::create(['name' => 'PA Testschule']);
        $project->partners()->attach($school);
        $project->teilnehmer()->attach($user->person_id, ['status' => 'aktiv']);
        $project->dokumente()->attach($template, [
            'gruppen_export' => true,
            'serienbrief' => true,
            'sort_order' => 0,
        ]);
        $user->update(['current_team_id' => $project->id]);
        $template->update(['aktiv' => false]);

        $this->actingAs($user->fresh())->postJson(route('anwesenheitsliste.PA.export.word'), [
            'startDate' => '2026-11-23',
            'endDate' => '2026-11-24',
            'schuleId' => $school->id,
            'schuljahr' => '2026/2027',
            'teil' => '1',
            'exportMode' => 'alle',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('vorlage');
    }
}
