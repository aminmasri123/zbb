<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\PotenzialanalyseUebung;
use App\Models\PotenzialanalyseUebungKompetenz;
use App\Models\Projekt;
use App\Models\ProjektHasPersonen;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PotenzialanalyseGewichtungsmatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_weighting_matrix_is_saved_for_the_project(): void
    {
        [$user, $projekt] = $this->context();
        $ersteUebung = PotenzialanalyseUebung::query()->create([
            'projekt_id' => $projekt->id,
            'name' => 'Linien ziehen',
            'aktiv' => true,
        ]);
        $zweiteUebung = PotenzialanalyseUebung::query()->create([
            'projekt_id' => $projekt->id,
            'name' => 'Figuren ergänzen',
            'aktiv' => true,
        ]);

        $this->actingAs($user)->putJson(
            route('potenzialanalyse.projekt.gewichtungsmatrix.update', $projekt),
            [
                'uebungen' => [
                    [
                        'id' => $ersteUebung->id,
                        'kompetenzen' => [
                            ['merkmal' => 'feinmotorik', 'gewichtung' => 30],
                            ['merkmal' => 'sorgfalt', 'gewichtung' => 100],
                        ],
                    ],
                    [
                        'id' => $zweiteUebung->id,
                        'kompetenzen' => [
                            ['merkmal' => 'feinmotorik', 'gewichtung' => 70],
                        ],
                    ],
                ],
            ],
        )->assertOk()
            ->assertJsonPath('message', 'Gewichtungsmatrix wurde gespeichert.');

        $this->assertDatabaseHas('potenzialanalyse_uebung_kompetenzen', [
            'uebung_id' => $ersteUebung->id,
            'merkmal' => 'feinmotorik',
            'gewichtung' => 30,
        ]);
        $this->assertDatabaseHas('potenzialanalyse_uebung_kompetenzen', [
            'uebung_id' => $zweiteUebung->id,
            'merkmal' => 'feinmotorik',
            'gewichtung' => 70,
        ]);
        $this->assertSame(3, PotenzialanalyseUebungKompetenz::query()->count());
    }

    public function test_used_competency_must_sum_to_one_hundred_percent(): void
    {
        [$user, $projekt] = $this->context();
        $uebung = PotenzialanalyseUebung::query()->create([
            'projekt_id' => $projekt->id,
            'name' => 'Linien ziehen',
            'aktiv' => true,
        ]);

        $this->actingAs($user)->putJson(
            route('potenzialanalyse.projekt.gewichtungsmatrix.update', $projekt),
            [
                'uebungen' => [[
                    'id' => $uebung->id,
                    'kompetenzen' => [[
                        'merkmal' => 'feinmotorik',
                        'gewichtung' => 30,
                    ]],
                ]],
            ],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('gewichtungsmatrix');

        $this->assertDatabaseCount('potenzialanalyse_uebung_kompetenzen', 0);
    }

    private function context(): array
    {
        $user = User::factory()->create();
        $projekt = Projekt::factory()->create([
            'potenzialanalyse_aktiv' => true,
            'potenzialanalyse_tage' => 2,
        ]);
        $standort = Standort::factory()->create();

        ProjektHasPersonen::query()->create([
            'projekt_id' => $projekt->id,
            'personen_id' => $user->person_id,
            'standort_id' => $standort->id,
            'status' => 'aktiv',
        ]);
        $user->update(['current_team_id' => $projekt->id]);

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

        return [$user, $projekt];
    }
}
