<?php

namespace Tests\Feature;

use App\Models\Raeume;
use App\Models\Raumtyp;
use App\Models\Standort;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaumtypManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_and_update_a_room_type(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'raeumlichkeiten.update');

        $response = $this->actingAs($user)->postJson(route('raeumlichkeiten.typen.store'), [
            'name' => 'Makerspace',
            'beschreibung' => 'Raum für digitale Fertigung',
            'aktiv' => true,
            'sort_order' => 15,
        ])->assertCreated();

        $raumtyp = Raumtyp::findOrFail($response->json('raumtyp.id'));
        $this->actingAs($user)->putJson(route('raeumlichkeiten.typen.update', $raumtyp), [
            'name' => 'FabLab',
            'beschreibung' => 'Neue Bezeichnung',
            'aktiv' => false,
            'sort_order' => 20,
        ])->assertOk();

        $this->assertDatabaseHas('raumtypen', [
            'id' => $raumtyp->id,
            'name' => 'FabLab',
            'aktiv' => false,
        ]);
    }

    public function test_renaming_a_room_type_updates_existing_rooms(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'raeumlichkeiten.update');
        $standort = Standort::factory()->create();
        $raumtyp = Raumtyp::query()->create(['name' => 'Altbau', 'aktiv' => true]);
        $raum = Raeume::query()->create([
            'standort_id' => $standort->id,
            'name' => 'Raum 101',
            'typ' => 'Altbau',
        ]);

        $this->actingAs($user)->putJson(route('raeumlichkeiten.typen.update', $raumtyp), [
            'name' => 'Bestandsbau',
            'aktiv' => true,
            'sort_order' => 0,
        ])->assertOk();

        $this->assertSame('Bestandsbau', $raum->fresh()->typ);
    }

    public function test_used_room_type_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'raeumlichkeiten.update');
        $standort = Standort::factory()->create();
        $raumtyp = Raumtyp::query()->create(['name' => 'Werkraum', 'aktiv' => true]);
        Raeume::query()->create([
            'standort_id' => $standort->id,
            'name' => 'Raum 202',
            'typ' => 'Werkraum',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('raeumlichkeiten.typen.destroy', $raumtyp))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('raumtyp');

        $this->assertDatabaseHas('raumtypen', ['id' => $raumtyp->id]);
    }

    public function test_user_without_update_permission_cannot_manage_room_types(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('raeumlichkeiten.typen.store'), [
            'name' => 'Nicht erlaubt',
        ])->assertForbidden();
    }
}
