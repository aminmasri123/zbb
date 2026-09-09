<?php

namespace Tests\Feature;

use App\Models\ParticipantImportReview;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ParticipantImportLocationTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'Coaching']);
        $first = Standort::factory()->create(['name' => 'Zugewiesen A']);
        $second = Standort::factory()->create(['name' => 'Zugewiesen B']);
        $foreign = Standort::factory()->create(['name' => 'Fremder Standort']);
        $user->projekte()->attach($project);
        $user->standorte()->attach([$first->id, $second->id]);
        $user->update(['current_team_id' => $project->id]);
        $this->grantTestPermission($user, 'teilnehmer.import');

        return [$user, $project, $first, $second, $foreign];
    }

    private function file(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('teilnehmer.csv', "Vorname;Nachname;Geschlecht;Geburtsdatum;Projekt_ID;Standort_ID\nAda;Standorttest;w;12.03.2008;999999;999999\nBerta;Standorttest;w;02.02.2007;888888;888888\n");
    }

    public function test_import_context_requires_permission_and_lists_only_assigned_locations(): void
    {
        [$user, $project, $first, $second, $foreign] = $this->context();
        $this->actingAs($user)->getJson(route('teilnehmer.import.context'))->assertOk()
            ->assertJsonPath('project.id', $project->id)->assertJsonCount(2, 'locations')
            ->assertJsonPath('locations.0.id', $first->id)->assertJsonPath('locations.1.id', $second->id)
            ->assertDontSee($foreign->name);
        $user->revokePermissionTo('teilnehmer.import');
        $this->getJson(route('teilnehmer.import.context'))->assertForbidden();
    }

    public function test_missing_or_unassigned_location_cannot_be_used_even_with_legacy_ids(): void
    {
        [$user, $project, $first, $second, $foreign] = $this->context();
        $file = $this->file();
        $this->actingAs($user)->postJson(route('teilnehmer.import'), ['file' => $file, 'preview' => 1])
            ->assertUnprocessable()->assertJsonPath('errors.0', 'Bitte wählen Sie einen Ihrer zugewiesenen Standorte aus.');
        $this->postJson(route('teilnehmer.import'), ['file' => $file, 'preview' => 1, 'standort_id' => $foreign->id])->assertForbidden();
        $this->postJson(route('teilnehmer.import'), ['file' => $file, 'standort_id' => $first->id])->assertUnprocessable();
        $this->assertDatabaseMissing('personens', ['nachname' => 'Standorttest']);
        $user->standorte()->detach();
        $this->getJson(route('teilnehmer.import.context'))->assertOk()->assertJsonCount(0, 'locations');
    }

    public function test_all_rows_use_active_project_and_selected_location_instead_of_spreadsheet_ids(): void
    {
        [$user, $project, $first, $second] = $this->context();
        $file = $this->file();
        $payload = ['file' => $file, 'standort_id' => $second->id, 'project_id' => $project->id];
        $preview = $this->actingAs($user)->postJson(route('teilnehmer.import'), $payload + ['preview' => 1])->assertOk()
            ->assertJsonPath('project', $project->name)->assertJsonPath('location.id', $second->id)
            ->assertJsonPath('rows.0.values.4', $project->id)->assertJsonPath('rows.0.values.5', $second->id)->json();
        $this->postJson(route('teilnehmer.import'), $payload + ['confirmation' => $preview['confirmation']])->assertOk()->assertJsonPath('result.created', 2);
        $people = Personen::where('nachname', 'Standorttest')->pluck('id');
        $this->assertSame(2, ProjektHasPersonen::whereIn('personen_id', $people)->where('projekt_id', $project->id)->where('standort_id', $second->id)->count());
        $this->assertSame(2, ProjektHasPersonen::whereIn('personen_id', $people)->count());
    }

    public function test_changed_selection_or_revoked_assignment_requires_a_new_preview(): void
    {
        [$user, $project, $first, $second] = $this->context();
        $file = $this->file();
        $payload = ['file' => $file, 'standort_id' => $first->id];
        $preview = $this->actingAs($user)->postJson(route('teilnehmer.import'), $payload + ['preview' => 1])->assertOk()->json();
        $this->postJson(route('teilnehmer.import'), ['file' => $file, 'standort_id' => $second->id, 'confirmation' => $preview['confirmation']])->assertUnprocessable();
        $user->standorte()->detach($first);
        $this->postJson(route('teilnehmer.import'), $payload + ['confirmation' => $preview['confirmation']])->assertForbidden();
        $this->assertDatabaseMissing('personens', ['nachname' => 'Standorttest']);
    }

    public function test_project_changed_in_another_tab_cannot_silently_receive_the_import(): void
    {
        [$user, $project, $first] = $this->context();
        $other = Projekt::factory()->create();
        $user->projekte()->attach($other);
        $user->update(['current_team_id' => $other->id]);
        $this->actingAs($user)->postJson(route('teilnehmer.import'), ['file' => $this->file(), 'preview' => 1,
            'project_id' => $project->id, 'standort_id' => $first->id])->assertConflict();
        $this->assertDatabaseMissing('personens', ['nachname' => 'Standorttest']);
    }

    public function test_deferred_import_remembers_location_but_does_not_bypass_current_assignments(): void
    {
        [$user, $project, $first] = $this->context();
        Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Ada', 'nachname' => 'Standorttest', 'geburtsdatum' => '2008-03-12']);
        $file = $this->file();
        $payload = ['file' => $file, 'standort_id' => $first->id];
        $preview = $this->actingAs($user)->postJson(route('teilnehmer.import'), $payload + ['preview' => 1])->assertOk()->json();
        $this->postJson(route('teilnehmer.import'), $payload + ['confirmation' => $preview['confirmation']])->assertOk()->assertJsonPath('result.deferred', 1);
        $review = ParticipantImportReview::firstOrFail();
        $saved = $this->getJson(route('teilnehmer.import.reviews.resume', $review->id))->assertOk()->assertJsonPath('standort_id', $first->id)->json();
        $user->standorte()->detach($first);
        $this->postJson(route('teilnehmer.import'), ['file' => UploadedFile::fake()->createWithContent('offen.csv', $saved['csv']),
            'review_id' => $review->id, 'standort_id' => $first->id, 'preview' => 1])->assertForbidden();
        $this->assertDatabaseHas('participant_import_reviews', ['id' => $review->id]);
    }
}
