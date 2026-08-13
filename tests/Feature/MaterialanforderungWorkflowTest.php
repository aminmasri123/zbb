<?php

namespace Tests\Feature;

use App\Models\Materialanforderung;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        $this->grantTestPermission($creator, 'materialanforderung.update');
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
