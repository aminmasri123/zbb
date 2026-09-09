<?php

namespace Tests\Feature;

use App\Models\Kontakttypen;
use App\Models\Notizvarianten;
use App\Models\ParticipantImportReview;
use App\Models\Personen;
use App\Models\PersonenHasNotizen;
use App\Models\PersonenHasSozialedaten;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ParticipantImportDecisionTest extends TestCase
{
    use RefreshDatabase;

    private int $importLocationId;

    private function setupContext(bool $sourceAccess = true): array
    {
        $user = User::factory()->create();
        $location = \App\Models\Standort::factory()->create();
        $user->standorte()->attach($location);
        $this->importLocationId = $location->id;
        $user->assignRole(Role::firstOrCreate(['name' => 'Projektleitung', 'guard_name' => 'web'], ['color' => '#123456']));
        $target = Projekt::factory()->create(['name' => 'BVB Reha']);
        $source = Projekt::factory()->create(['name' => 'BOP Altprojekt']);
        $user->projekte()->attach($target, ['standort_id' => $location->id, 'status' => 'aktiv']);
        if ($sourceAccess) {
            $user->projekte()->attach($source);
        }
        $user->update(['current_team_id' => $target->id]);
        $this->grantTestPermission($user, 'teilnehmer.import');
        $this->grantTestPermission($user, 'teilnehmer.update');
        $person = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Ada', 'nachname' => 'Bestand', 'geburtsdatum' => '2008-03-12']);
        $person->projekte()->attach($source, ['status' => 'abgeschlossen']);

        return [$user, $target, $source, $person];
    }

    private function file(bool $includeNew = true): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('teilnehmer.csv', "Vorname;Nachname;Geburtsdatum;Geschlecht\nAda;Bestand;12.03.2008;w\n".($includeNew ? "Berta;Neuzugang;02.02.2007;w\n" : ''));
    }

    private function preview($user, $file): array
    {
        return $this->actingAs($user)->postJson(route('teilnehmer.import'), ['standort_id' => $this->importLocationId, 'file' => $file, 'preview' => 1])->assertOk()->json();
    }

    public function test_new_rows_import_while_matches_are_encrypted_and_can_be_resumed(): void
    {
        [$user,$target,$source,$person] = $this->setupContext();
        $file = $this->file();
        $preview = $this->preview($user, $file);
        $this->assertSame('match', $preview['rows'][0]['match']['status']);
        $this->assertSame(['BOP Altprojekt'], $preview['rows'][0]['match']['candidates'][0]['projects']);
        $this->postJson(route('teilnehmer.import'), ['standort_id' => $this->importLocationId, 'file' => $file, 'confirmation' => $preview['confirmation']])->assertOk()->assertJsonPath('result.created', 1)->assertJsonPath('result.deferred', 1);
        $review = ParticipantImportReview::firstOrFail();
        $this->assertStringNotContainsString('Bestand', DB::table('participant_import_reviews')->value('payload'));
        $this->assertFalse($person->projekte()->whereKey($target->id)->exists());
        $saved = $this->getJson(route('teilnehmer.import.reviews.resume', $review->id))->assertOk()->json();
        $resumed = UploadedFile::fake()->createWithContent('offen.csv', $saved['csv']);
        $next = $this->postJson(route('teilnehmer.import'), ['standort_id' => $this->importLocationId, 'file' => $resumed, 'preview' => 1, 'review_id' => $review->id, 'import_profile' => $saved['profile']])->assertOk()->json();
        $this->postJson(route('teilnehmer.import'), ['standort_id' => $this->importLocationId, 'file' => $resumed, 'confirmation' => $next['confirmation'], 'review_id' => $review->id, 'import_profile' => $saved['profile'],
            'decisions' => ['2' => ['action' => 'reuse', 'person_id' => $person->id, 'identity_checked' => true]]])->assertOk()->assertJsonPath('result.linked', 1)->assertJsonPath('result.created', 0);
        $this->assertDatabaseCount('participant_import_reviews', 0);
        $this->assertSame(1, Personen::where('nachname', 'Bestand')->count());
        $this->assertSame('abgeschlossen', $person->projekte()->whereKey($source->id)->first()->pivotModel->status);
        $link = ProjektHasPersonen::where('personen_id', $person->id)->where('projekt_id', $target->id)->firstOrFail();
        $this->assertSame($user->id, $link->import_entry_data['confirmed_by']);
        $this->assertFalse($link->import_entry_data['reports_transferred']);
    }

    public function test_hidden_projects_and_person_ids_are_not_disclosed_and_cannot_be_forged(): void
    {
        [$user,$target,$source,$person] = $this->setupContext(false);
        $file = $this->file();
        $preview = $this->preview($user, $file);
        $this->assertSame(['status' => 'restricted', 'candidates' => []], $preview['rows'][0]['match']);
        $this->assertStringNotContainsString('BOP Altprojekt', json_encode($preview));
        $this->postJson(route('teilnehmer.import'), ['standort_id' => $this->importLocationId, 'file' => $file, 'confirmation' => $preview['confirmation'], 'decisions' => ['2' => ['action' => 'reuse', 'person_id' => $person->id, 'identity_checked' => true]]])->assertUnprocessable();
        $this->assertDatabaseMissing('personens', ['nachname' => 'Neuzugang']);
    }

    public function test_shared_legacy_data_prevents_automatic_access_expansion(): void
    {
        [$user,$target,$source,$person] = $this->setupContext();
        PersonenHasSozialedaten::create(['person_id' => $person->id, 'behinderung' => true]);
        $file = $this->file(false);
        $preview = $this->preview($user, $file);
        $this->assertFalse($preview['rows'][0]['match']['candidates'][0]['can_reuse']);
        $this->postJson(route('teilnehmer.import'), ['standort_id' => $this->importLocationId, 'file' => $file, 'confirmation' => $preview['confirmation'], 'decisions' => ['2' => ['action' => 'reuse', 'person_id' => $person->id, 'identity_checked' => true]]])->assertUnprocessable();
        $this->assertFalse($person->projekte()->whereKey($target->id)->exists());
    }

    public function test_pending_rows_are_owner_and_project_scoped_and_expire_without_deleting_people(): void
    {
        [$user,$target,$source,$person] = $this->setupContext();
        $review = ParticipantImportReview::create(['user_id' => $user->id, 'projekt_id' => $target->id, 'payload' => ['profile' => 'standard', 'rows' => [['Ada', 'Bestand']]], 'expires_at' => now()->addDays(30)]);
        $other = User::factory()->create();
        $other->projekte()->attach($target);
        $other->update(['current_team_id' => $target->id]);
        $this->grantTestPermission($other, 'teilnehmer.import');
        $this->actingAs($other)->getJson(route('teilnehmer.import.reviews.resume', $review->id))->assertNotFound();
        $this->getJson(route('teilnehmer.import.reviews'))->assertOk()->assertJsonCount(0, 'reviews');
        $review->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($user)->getJson(route('teilnehmer.import.reviews.resume', $review->id))->assertNotFound();
        $this->artisan('participants:purge-expired-import-reviews')->assertSuccessful();
        $this->assertDatabaseCount('participant_import_reviews', 0);
        $this->assertDatabaseHas('personens', ['id' => $person->id]);
    }

    public function test_separate_person_requires_explicit_check_and_preserves_the_existing_person(): void
    {
        [$user,$target,$source,$person] = $this->setupContext();
        $file = $this->file(false);
        $preview = $this->preview($user, $file);
        $request = ['standort_id' => $this->importLocationId, 'file' => $file, 'confirmation' => $preview['confirmation'], 'decisions' => ['2' => ['action' => 'separate', 'identity_checked' => false]]];
        $this->postJson(route('teilnehmer.import'), $request)->assertUnprocessable();
        $request['decisions']['2']['identity_checked'] = true;
        $this->postJson(route('teilnehmer.import'), $request)->assertOk()->assertJsonPath('result.created', 1)->assertJsonPath('result.linked', 0);
        $this->assertSame(2, Personen::where('nachname', 'Bestand')->count());
        $this->assertFalse($person->projekte()->whereKey($target->id)->exists());
        $this->assertSame('abgeschlossen', $person->projekte()->whereKey($source->id)->first()->pivotModel->status);
    }

    public function test_match_created_after_preview_is_deferred_instead_of_duplicated(): void
    {
        [$user,$target] = $this->setupContext();
        $file = UploadedFile::fake()->createWithContent('neu.csv', "Vorname;Nachname;Geburtsdatum;Geschlecht\nBerta;Spaeter;02.02.2007;w\n");
        $preview = $this->preview($user, $file);
        $this->assertSame('new', $preview['rows'][0]['match']['status']);
        $person = Personen::factory()->create(['typ' => 'teilnehmer', 'vorname' => 'Berta', 'nachname' => 'Spaeter', 'geburtsdatum' => '2007-02-02']);
        $person->projekte()->attach($target);
        $this->postJson(route('teilnehmer.import'), ['standort_id' => $this->importLocationId, 'file' => $file, 'confirmation' => $preview['confirmation'], 'decisions' => ['2' => ['action' => 'new']]])
            ->assertOk()->assertJsonPath('result.created', 0)->assertJsonPath('result.deferred', 1);
        $this->assertSame(1, Personen::where('nachname', 'Spaeter')->count());
    }

    public function test_reuse_preserves_contacts_and_old_notes_and_scopes_the_participant_page(): void
    {
        [$user,$target,$source,$person] = $this->setupContext();
        $contactType = Kontakttypen::firstOrCreate(['name' => 'Email']);
        $person->kontaktes()->create(['kontakttyp_id' => $contactType->id, 'wert' => 'bisher@example.test']);
        $variant = Notizvarianten::create(['name' => 'Test', 'typ' => 'typ']);
        $sourceParticipation = ProjektHasPersonen::where('personen_id', $person->id)->where('projekt_id', $source->id)->firstOrFail();
        $note = PersonenHasNotizen::create([
            'person_id' => $person->id, 'projekt_person_id' => $sourceParticipation->id, 'user_id' => $user->person_id,
            'notiztyp_id' => $variant->id, 'prioritaet_id' => $variant->id, 'kategorie_id' => $variant->id,
            'titel' => 'Vertrauliche Altprojektnotiz', 'notizinhalt' => 'Bleibt ausschließlich im Altprojekt.',
        ]);
        $file = UploadedFile::fake()->createWithContent('kontakte.csv', "Vorname;Nachname;Geburtsdatum;E-Mail;Geschlecht\nAda;Bestand;12.03.2008;neue@example.test;w\n");
        $preview = $this->preview($user, $file);
        $this->postJson(route('teilnehmer.import'), ['standort_id' => $this->importLocationId, 'file' => $file, 'confirmation' => $preview['confirmation'],
            'decisions' => ['2' => ['action' => 'reuse', 'person_id' => $person->id, 'identity_checked' => true]]])->assertOk()->assertJsonPath('result.linked', 1);
        $this->assertSame(['bisher@example.test'], $person->kontaktes()->pluck('wert')->all());
        $this->assertSame($sourceParticipation->id, $note->fresh()->projekt_person_id);
        $this->assertDatabaseCount('personen_has_notizens', 1);
        $this->get(route('teilnehmer.edit', $person->id))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('teilnehmer.notizen', 0)->has('teilnehmer.projekte', 1)->where('teilnehmer.projekte.0.id', $target->id));
        $this->grantTestPermission($user, 'teilnehmer.index');
        $this->get(route('teilnehmer.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('teilnehmers.data', 1)->has('teilnehmers.data.0.projekte', 1)->where('teilnehmers.data.0.projekte.0.id', $target->id));
    }

    public function test_import_permission_without_update_permission_cannot_expose_matches(): void
    {
        [$user,$target,$source,$person] = $this->setupContext();
        $user->revokePermissionTo('teilnehmer.update');
        $preview = $this->preview($user,$this->file(false));
        $this->assertSame(['status' => 'restricted', 'candidates' => []],$preview['rows'][0]['match']);
    }
}
