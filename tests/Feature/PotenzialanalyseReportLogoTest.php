<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Standort;
use App\Models\User;
use App\Services\PotenzialanalyseProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PotenzialanalyseReportLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_report_logo_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');
        [$user, $project] = $this->context();
        $profile = app(PotenzialanalyseProfileService::class)
            ->createHametEPlusProfile($project, 'BvB Reha');

        $this->actingAs($user)->post(
            route('potenzialanalyse.profile.bericht-config.update', $profile),
            [
                '_method' => 'PUT',
                'titel' => 'Auswertung der Potenzialanalyse',
                'untertitel' => 'BvB Reha',
                'uebungsergebnisse_anzeigen' => '1',
                'selbsteinschaetzung_anzeigen' => '1',
                'staerkenprofil_anzeigen' => '1',
                'logo_anzeigen' => '1',
                'logo_entfernen' => '0',
                'logo' => UploadedFile::fake()->image('bvb-reha.png', 240, 180),
            ],
        )->assertOk()
            ->assertJsonPath('profil.bericht_config.darstellung.logo_anzeigen', true);

        $uploadedPath = data_get($profile->fresh()->bericht_config, 'darstellung.logo_path');
        $this->assertStringStartsWith('potenzialanalyse/logos/profil-'.$profile->id.'-', $uploadedPath);
        Storage::disk('public')->assertExists($uploadedPath);

        $this->actingAs($user)->putJson(
            route('potenzialanalyse.profile.bericht-config.update', $profile),
            [
                'titel' => 'Auswertung der Potenzialanalyse',
                'untertitel' => 'BvB Reha',
                'uebungsergebnisse_anzeigen' => true,
                'selbsteinschaetzung_anzeigen' => true,
                'staerkenprofil_anzeigen' => true,
                'logo_anzeigen' => false,
                'logo_entfernen' => true,
            ],
        )->assertOk()
            ->assertJsonPath('profil.bericht_config.darstellung.logo_anzeigen', false)
            ->assertJsonPath('profil.bericht_config.darstellung.logo_path', null);

        Storage::disk('public')->assertMissing($uploadedPath);
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $project = Projekt::factory()->create([
            'potenzialanalyse_aktiv' => true,
            'potenzialanalyse_tage' => 2,
        ]);
        $location = Standort::factory()->create();

        ProjektHasPersonen::query()->create([
            'projekt_id' => $project->id,
            'personen_id' => $user->person_id,
            'standort_id' => $location->id,
            'status' => 'aktiv',
        ]);
        $user->update(['current_team_id' => $project->id]);

        $category = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Potenzialanalyse'],
            ['beschreibung' => ''],
        );
        $permission = Permission::query()->updateOrCreate(
            ['name' => 'potenzialanalyse.manage', 'guard_name' => 'web'],
            ['berechtigungskategorie_id' => $category->id, 'beschreibung' => null],
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo($permission);

        return [$user, $project];
    }
}
