<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GeneralSchoolDocumentPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_permissions_never_expose_a_school_outside_the_active_project(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create();
        $allowedPartner = Partner::query()->create(['name' => 'Erlaubte Schule']);
        $foreignPartner = Partner::query()->create(['name' => 'Fremde Schule']);

        DB::table('projekt_has_partners')->insert([
            'projekt_id' => $project->id,
            'partner_id' => $allowedPartner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user->update(['current_team_id' => $project->id]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo([
            'dokumente.schule.export',
            'teilnehmer.liste.export',
            'dokumente.ansprechpartner.manage',
        ]);

        $this->actingAs($user)
            ->get(route('hausordnung.export.schule.pdf', [
                'partnerId' => $foreignPartner->id,
                'schuljahr' => '2026/2027',
                'teil' => '1',
                'sortBy' => 'nachname',
                'termin' => '2026-07-12',
            ]))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('export.elterneinverstaendniserklaerung.schule', [
                'partnerId' => $foreignPartner->id,
                'schuljahr' => '2026/2027',
                'teil' => '1',
            ]))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('export.teilnehmerliste.schule.excel', [
                'schuleId' => $foreignPartner->id,
                'schuljahr' => '2026/2027',
                'teil' => '1',
            ]))
            ->assertNotFound();
    }
}
