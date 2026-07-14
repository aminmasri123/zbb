<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BopUsbStickLetterExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_bop_project_can_export_the_letter_for_an_assigned_school(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $school = Partner::query()->create(['name' => 'Testschule']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        $project->partners()->attach($school->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo('dokumente.schule.export');

        $this->actingAs($user)->post(route('partner.bop-usb-stick-letter.export', $school), [
            'schuljahr' => '2025/2026',
            'datum' => '2026-07-14',
        ])->assertOk()->assertDownload('USB-Stick-Brief-Testschule.docx');
    }

    public function test_school_outside_the_active_project_is_not_exported(): void
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create(['name' => 'BOP']);
        $school = Partner::query()->create(['name' => 'Fremde Schule']);
        $user->projekte()->attach($project->id);
        $user->update(['current_team_id' => $project->id]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo('dokumente.schule.export');

        $this->actingAs($user)->post(route('partner.bop-usb-stick-letter.export', $school), [
            'schuljahr' => '2025/2026',
            'datum' => '2026-07-14',
        ])->assertNotFound();
    }
}
