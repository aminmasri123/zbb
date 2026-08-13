<?php

namespace Tests\Feature;

use App\Models\Materialanforderung;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Standort;
use App\Models\User;
use App\Notifications\UpdateMaterialanforderungNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class MaterialanforderungWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_can_open_materialanforderung_overview(): void
    {
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $creator = User::factory()->create(['current_team_id' => $project->id]);
        $this->grantTestPermission($creator, 'materialanforderung.index');
        $anforderung = $this->anforderung($creator, $project, 'entwurf');

        $this->actingAs($creator)
            ->get(route('materialanforderung.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('anforderungen', 1)
                ->where('anforderungen.0.id', $anforderung->id));
    }

    public function test_only_sachlicher_approver_assigned_to_project_can_approve(): void
    {
        Notification::fake();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $creator = User::factory()->create(['current_team_id' => $project->id]);
        $assignedApprover = User::factory()->create(['current_team_id' => $project->id]);
        $foreignApprover = User::factory()->create();

        foreach ([$assignedApprover, $foreignApprover] as $approver) {
            $this->grantTestPermission($approver, 'materialanforderung.sachlische_freigabe.update');
            $this->grantTestPermission($approver, 'materialanforderung.sachlische_freigabe.index');
        }
        $this->assign($assignedApprover, $project);
        $this->grantTestPermission($assignedApprover, 'materialanforderung.index');
        $this->grantTestPermission($assignedApprover, 'materialanforderung.show');

        $anforderung = $this->anforderung($creator, $project, 'eingereicht');

        $this->actingAs($foreignApprover)
            ->put(route('materialanforderung.genehmigen', [$anforderung->id, 'sachlich_genehmigt']))
            ->assertForbidden();

        $this->assertSame('eingereicht', $anforderung->fresh()->status);

        $this->actingAs($assignedApprover)
            ->put(route('materialanforderung.genehmigen', [$anforderung->id, 'sachlich_genehmigt']))
            ->assertRedirect();

        $this->assertSame('sachlich_genehmigt', $anforderung->fresh()->status);
        $this->assertDatabaseHas('materialanforderung_genehmigungs', [
            'anforderung_id' => $anforderung->id,
            'genehmiger_id' => $assignedApprover->id,
            'status' => 'sachlich_genehmigt',
        ]);

        $this->actingAs($assignedApprover)
            ->get(route('materialanforderung.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('anforderungen', 1)
                ->where('anforderungen.0.id', $anforderung->id)
                ->where('anforderungen.0.von_mir_bearbeitet', true));

        $this->actingAs($assignedApprover)
            ->get(route('materialanforderung.show', $anforderung))
            ->assertOk();
    }

    public function test_kaufmaennischer_approver_sees_requests_across_projects_before_and_after_approval(): void
    {
        Notification::fake();
        $requestProject = Projekt::factory()->create(['name' => 'BOP']);
        $otherProject = Projekt::factory()->create(['name' => 'Anderes Projekt']);
        $creator = User::factory()->create(['current_team_id' => $requestProject->id]);
        $approver = User::factory()->create(['current_team_id' => $otherProject->id]);
        $this->grantTestPermission($approver, 'materialanforderung.index');
        $this->grantTestPermission($approver, 'materialanforderung.show');
        $this->grantTestPermission($approver, 'materialanforderung.kaufmännische_freigabe.update');
        $anforderung = $this->anforderung($creator, $requestProject, 'sachlich_genehmigt');

        $this->actingAs($approver)
            ->get(route('materialanforderung.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('anforderungen', 1)
                ->where('anforderungen.0.id', $anforderung->id));

        $this->actingAs($approver)
            ->put(route('materialanforderung.genehmigen', [$anforderung->id, 'kaufmaennisch_genehmigt']))
            ->assertRedirect();

        $this->assertSame('kaufmaennisch_genehmigt', $anforderung->fresh()->status);

        $this->actingAs($approver)
            ->get(route('materialanforderung.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('anforderungen', 1)
                ->where('anforderungen.0.id', $anforderung->id)
                ->where('anforderungen.0.von_mir_bearbeitet', true));

        $this->actingAs($approver)
            ->get(route('materialanforderung.show', $anforderung))
            ->assertOk();
    }

    public function test_creator_can_export_own_materialanforderung_as_pdf(): void
    {
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $creator = User::factory()->create(['current_team_id' => $project->id]);
        $this->grantTestPermission($creator, 'materialanforderung.show');
        $anforderung = $this->anforderung($creator, $project, 'entwurf');
        $anforderung->artikeln()->create([
            'pos' => 1,
            'artikel' => 'Kopierpapier A4',
            'stueck' => 2,
            'einzelpreis' => 5,
            'mwst' => 19,
            'gesamtpreis' => 10,
        ]);

        $this->actingAs($creator)
            ->get(route('materialanforderung.pdf', $anforderung))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_creator_can_withdraw_submitted_request_before_sachliche_approval(): void
    {
        Notification::fake();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $creator = User::factory()->create(['current_team_id' => $project->id]);
        $creator->person->update(['vorname' => 'Amin', 'nachname' => 'Masri']);
        $this->grantTestPermission($creator, 'materialanforderung.update');
        $approver = User::factory()->create(['current_team_id' => $project->id]);
        $this->grantTestPermission($approver, 'materialanforderung.sachlische_freigabe.index');
        $this->assign($approver, $project);
        $anforderung = $this->anforderung($creator, $project, 'eingereicht');

        $this->actingAs($creator)
            ->put(route('materialanforderung.genehmigen', [$anforderung->id, 'zurueckgezogen']), [
                'anmerkung' => 'Preis muss noch korrigiert werden.',
            ])
            ->assertRedirect();

        $this->assertSame('entwurf', $anforderung->fresh()->status);
        $this->assertDatabaseHas('materialanforderung_genehmigungs', [
            'anforderung_id' => $anforderung->id,
            'genehmiger_id' => $creator->id,
            'status' => 'zurueckgezogen',
            'kommentar' => 'Preis muss noch korrigiert werden.',
        ]);
        Notification::assertSentTo(
            $approver,
            UpdateMaterialanforderungNotification::class,
            fn (UpdateMaterialanforderungNotification $notification) =>
                $notification->toDatabase($approver)['message']
                    === "Amin Masri hat die Materialanforderung #{$anforderung->id} zurückgezogen."
        );

        $this->actingAs($creator)
            ->get(route('materialanforderung.show', $anforderung))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canEditMaterialanforderung', true));
    }

    public function test_creator_cannot_withdraw_after_sachliche_approval(): void
    {
        Notification::fake();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $creator = User::factory()->create(['current_team_id' => $project->id]);
        $this->grantTestPermission($creator, 'materialanforderung.update');
        $anforderung = $this->anforderung($creator, $project, 'sachlich_genehmigt');

        $this->actingAs($creator)
            ->put(route('materialanforderung.genehmigen', [$anforderung->id, 'zurueckgezogen']), [
                'anmerkung' => 'Zu spät.',
            ])
            ->assertForbidden();

        $this->assertSame('sachlich_genehmigt', $anforderung->fresh()->status);
    }

    public function test_bestellwesen_records_order_number_and_partial_delivery_quantities(): void
    {
        Notification::fake();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $creator = User::factory()->create(['current_team_id' => $project->id]);
        $buyer = User::factory()->create();
        $this->grantTestPermission($buyer, 'materialanforderung.bestellwesen.update');
        $anforderung = $this->anforderung($creator, $project, 'kaufmaennisch_genehmigt');
        $artikel = $anforderung->artikeln()->create([
            'pos' => 1,
            'artikel' => 'Schutzbrille',
            'stueck' => 10,
            'einzelpreis' => 5,
            'mwst' => 19,
            'gesamtpreis' => 50,
        ]);

        $this->actingAs($buyer)
            ->put(route('materialanforderung.genehmigen', [$anforderung->id, 'bestellt']))
            ->assertSessionHasErrors('bestellnummer');

        $this->actingAs($buyer)
            ->put(route('materialanforderung.genehmigen', [$anforderung->id, 'bestellt']), ['bestellnummer' => 'B-2026-15'])
            ->assertRedirect();

        $this->assertSame('bestellt', $anforderung->fresh()->status);
        $this->assertDatabaseHas('materialanforderung_vergabevermerks', [
            'anforderung_id' => $anforderung->id,
            'bestellnummer' => 'B-2026-15',
        ]);

        $this->actingAs($buyer)
            ->put(route('materialanforderung.genehmigen', [$anforderung->id, 'teilweise_geliefert']), [
                'liefermengen' => [$artikel->id => 4],
            ])
            ->assertRedirect();

        $this->assertSame('teilweise_geliefert', $anforderung->fresh()->status);
        $this->assertSame(4, (int) $artikel->fresh()->gelieferte_menge);
    }

    public function test_invalid_partial_delivery_returns_validation_errors_instead_of_error_page(): void
    {
        Notification::fake();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $creator = User::factory()->create(['current_team_id' => $project->id]);
        $buyer = User::factory()->create();
        $this->grantTestPermission($buyer, 'materialanforderung.bestellwesen.update');
        $anforderung = $this->anforderung($creator, $project, 'bestellt');
        $artikel = $anforderung->artikeln()->create([
            'pos' => 1,
            'artikel' => 'Schutzbrille',
            'stueck' => 10,
            'einzelpreis' => 5,
            'mwst' => 19,
            'gesamtpreis' => 50,
        ]);

        $this->actingAs($buyer)
            ->from(route('materialanforderung.show', $anforderung))
            ->put(route('materialanforderung.genehmigen', [$anforderung->id, 'teilweise_geliefert']), [
                'liefermengen' => [$artikel->id => 10],
            ])
            ->assertRedirect(route('materialanforderung.show', $anforderung))
            ->assertSessionHasErrors([
                'liefermengen' => 'Alle Artikel sind vollständig geliefert. Bitte verwenden Sie stattdessen „Vollständig geliefert“.',
            ]);

        $this->assertSame('bestellt', $anforderung->fresh()->status);
        $this->assertSame(0, (int) $artikel->fresh()->gelieferte_menge);
    }

    public function test_authorized_user_can_delete_ordered_request_with_all_related_data_and_audit_log(): void
    {
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $creator = User::factory()->create(['current_team_id' => $project->id]);
        $deleter = User::factory()->create();
        $this->grantTestPermission($deleter, 'materialanforderung.bestellte.destroy');
        $anforderung = $this->anforderung($creator, $project, 'bestellt');
        $artikel = $anforderung->artikeln()->create([
            'pos' => 1,
            'artikel' => 'Schutzbrille',
            'stueck' => 10,
            'gelieferte_menge' => 0,
            'einzelpreis' => 5,
            'mwst' => 19,
            'gesamtpreis' => 50,
        ]);
        $vergabevermerk = $anforderung->vergabevermerk()->create([
            'lieferung_art' => 'Lieferleistung',
            'lieferung_option' => 'per Lieferung',
            'lieferadresse' => 'Teststraße 1',
            'bestellnummer' => 'B-2026-99',
        ]);
        $genehmigung = $anforderung->genehmigungen()->create([
            'genehmiger_id' => $deleter->id,
            'status' => 'kaufmaennisch_genehmigt',
            'kommentar' => 'Freigegeben',
        ]);
        $notificationId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => UpdateMaterialanforderungNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $creator->id,
            'data' => json_encode([
                'id' => $anforderung->id,
                'typ' => 'Materialanforderung',
                'message' => 'Test',
            ], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($deleter)
            ->delete(route('materialanforderung.destroy', $anforderung), [
                'begruendung' => 'Doppelte Testbestellung wurde irrtümlich angelegt.',
                'bestaetigung' => "LÖSCHEN #{$anforderung->id}",
            ])
            ->assertRedirect(route('materialanforderung.index'))
            ->assertSessionHas('success', "Materialanforderung #{$anforderung->id} und alle zugehörigen Daten wurden endgültig gelöscht. Das Löschprotokoll bleibt erhalten.");

        $this->assertDatabaseMissing('materialanforderungs', ['id' => $anforderung->id]);
        $this->assertDatabaseMissing('materialanforderung_artikels', ['id' => $artikel->id]);
        $this->assertDatabaseMissing('materialanforderung_vergabevermerks', ['id' => $vergabevermerk->id]);
        $this->assertDatabaseMissing('materialanforderung_genehmigungs', ['id' => $genehmigung->id]);
        $this->assertDatabaseMissing('notifications', ['id' => $notificationId]);
        $this->assertDatabaseHas('materialanforderung_loeschprotokolls', [
            'materialanforderung_id' => $anforderung->id,
            'projekt_id' => $project->id,
            'ersteller_id' => $creator->id,
            'geloescht_von_id' => $deleter->id,
            'status' => 'bestellt',
            'bestellnummer' => 'B-2026-99',
            'begruendung' => 'Doppelte Testbestellung wurde irrtümlich angelegt.',
        ]);

        $snapshot = DB::table('materialanforderung_loeschprotokolls')
            ->where('materialanforderung_id', $anforderung->id)
            ->value('snapshot');
        $this->assertSame('Schutzbrille', json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR)['artikel'][0]['artikel']);
    }

    public function test_creator_with_special_permission_sees_delete_button_for_own_ordered_request(): void
    {
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $creator = User::factory()->create(['current_team_id' => $project->id]);
        $this->grantTestPermission($creator, 'materialanforderung.bestellte.destroy');
        $anforderung = $this->anforderung($creator, $project, 'bestellt');

        $this->actingAs($creator)
            ->get(route('materialanforderung.show', $anforderung))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('anforderung.id', $anforderung->id)
                ->where('canDeleteOrderedMaterialanforderung', true));
    }

    public function test_ordered_deletion_requires_special_permission_and_delivered_requests_remain_protected(): void
    {
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $creator = User::factory()->create(['current_team_id' => $project->id]);
        $regularUser = User::factory()->create();
        $specialUser = User::factory()->create();
        $this->grantTestPermission($regularUser, 'materialanforderung.destroy');
        $this->grantTestPermission($specialUser, 'materialanforderung.bestellte.destroy');
        $ordered = $this->anforderung($creator, $project, 'bestellt');
        $delivered = $this->anforderung($creator, $project, 'geliefert');

        $payload = [
            'begruendung' => 'Doppelte Testbestellung wurde angelegt.',
            'bestaetigung' => "LÖSCHEN #{$ordered->id}",
        ];

        $this->actingAs($regularUser)
            ->delete(route('materialanforderung.destroy', $ordered), $payload)
            ->assertForbidden();

        $this->actingAs($specialUser)
            ->delete(route('materialanforderung.destroy', $delivered), [
                'begruendung' => 'Dieser Vorgang soll nicht löschbar sein.',
                'bestaetigung' => "LÖSCHEN #{$delivered->id}",
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('materialanforderungs', ['id' => $ordered->id]);
        $this->assertDatabaseHas('materialanforderungs', ['id' => $delivered->id]);
    }

    private function anforderung(User $creator, Projekt $project, string $status): Materialanforderung
    {
        return Materialanforderung::create([
            'projekt_id' => $project->id,
            'kostenstelle' => '12345',
            'prioritaet' => 'normal',
            'status' => $status,
            'gesamtpreis' => 10,
            'endsumme' => 11.90,
            'ersteller_id' => $creator->id,
        ]);
    }

    private function assign(User $user, Projekt $project): void
    {
        ProjektHasPersonen::create([
            'projekt_id' => $project->id,
            'personen_id' => $user->person_id,
            'standort_id' => Standort::factory()->create()->id,
            'status' => 'aktiv',
        ]);
    }
}
