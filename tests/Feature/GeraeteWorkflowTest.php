<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\Geraet;
use App\Models\Geraetausgabe;
use App\Models\Geraetrueckgabe;
use App\Models\Personen;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GeraeteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_permission_cannot_create_device_issue(): void
    {
        $user = User::factory()->create();
        [$person, $projekt, $geraet] = $this->deviceWorkflowData();

        $response = $this->actingAs($user)->post(route('geraet.ausgabe.store'), [
            'ausgabeschein_nr' => 'AS-100',
            'ausleiher' => $person->id,
            'projekt' => $projekt->id,
            'sn' => [$geraet->sn],
            'ausleihdatum' => '2026-07-09',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('geraetausgabes', ['ausgabescheinNr' => 'AS-100']);
        $this->assertTrue($geraet->fresh()->verfuegbarkeit);
    }

    public function test_device_can_be_issued_and_returned_with_permissions(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'geraet.ausgabe.store');
        $this->givePermission($user, 'geraet.rueckgabe.store');
        $this->givePermission($user, 'geraet.rueckgabe.geraete');

        [$person, $projekt, $geraet] = $this->deviceWorkflowData();

        $issueResponse = $this->actingAs($user)->post(route('geraet.ausgabe.store'), [
            'ausgabeschein_nr' => 'AS-101',
            'ausleiher' => $person->id,
            'projekt' => $projekt->id,
            'sn' => [$geraet->sn],
            'ausleihdatum' => '2026-07-09',
        ]);

        $issueResponse->assertRedirect(route('geraet.ausgabe.index'));

        $ausgabe = Geraetausgabe::query()->where('ausgabescheinNr', 'AS-101')->firstOrFail();
        $this->assertFalse($geraet->fresh()->verfuegbarkeit);
        $this->assertTrue($geraet->fresh()->ausgaben->contains($ausgabe));
        $this->assertDatabaseHas('geraet_has_ausgabes', [
            'geraet_id' => $geraet->id,
            'ausgabe_id' => $ausgabe->id,
        ]);

        $openDevicesResponse = $this->actingAs($user)->get(route('geraet.rueckgabe.geraete', $ausgabe->id));
        $openDevicesResponse->assertOk()->assertJsonFragment(['id' => $geraet->id]);

        $returnResponse = $this->actingAs($user)->post(route('geraet.rueckgabe.store'), [
            'ausgabeschein_nr' => $ausgabe->id,
            'ausleiher' => $person->id,
            'rueckgabescheinNr' => 'RS-101',
            'sn' => [$geraet->id],
            'rueckgabedatum' => '2026-07-10',
            'ablageort' => 'Lager A',
        ]);

        $returnResponse->assertRedirect(route('geraet.rueckgabe.index'));

        $rueckgabe = Geraetrueckgabe::query()->where('rueckgabescheinNr', 'RS-101')->firstOrFail();
        $this->assertTrue($geraet->fresh()->verfuegbarkeit);
        $this->assertSame('Lager A', $geraet->fresh()->imLager);
        $this->assertTrue($geraet->fresh()->rueckgaben->contains($rueckgabe));
        $this->assertDatabaseHas('geraet_has_rueckgabes', [
            'geraet_id' => $geraet->id,
            'rueckgabe_id' => $rueckgabe->id,
        ]);

        $this->actingAs($user)
            ->get(route('geraet.rueckgabe.geraete', $ausgabe->id))
            ->assertOk()
            ->assertExactJson([]);
    }

    private function deviceWorkflowData(): array
    {
        return [
            Personen::factory()->create(['typ' => 'mitarbeiter']),
            Projekt::factory()->create(),
            Geraet::factory()->create(['verfuegbarkeit' => true]),
        ];
    }

    private function givePermission(User $user, string $permissionName): void
    {
        $categoryId = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Geraet'],
            ['beschreibung' => '']
        )->id;

        Permission::query()->updateOrCreate(
            [
                'name' => $permissionName,
                'guard_name' => 'web',
            ],
            [
                'berechtigungskategorie_id' => $categoryId,
                'beschreibung' => null,
            ]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user->givePermissionTo($permissionName);
    }
}
