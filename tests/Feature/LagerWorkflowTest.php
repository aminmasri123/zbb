<?php

namespace Tests\Feature;

use App\Models\Berechtigungskategorie;
use App\Models\LagerArtikel;
use App\Models\LagerBewegung;
use App\Models\LagerReservierung;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LagerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_permission_cannot_reserve_lager_article(): void
    {
        $user = User::factory()->create();
        $artikel = $this->lagerArtikel(['bestand' => 5]);

        $response = $this->actingAs($user)->post(route('lager.reservierung.store', $artikel), [
            'menge' => 2,
            'zweck' => 'Schulung',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('lager_reservierungen', ['lager_artikel_id' => $artikel->id]);
    }

    public function test_user_with_permission_can_reserve_lager_article(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'lager.reservierung.store');
        $artikel = $this->lagerArtikel(['bestand' => 5]);

        $response = $this->actingAs($user)->post(route('lager.reservierung.store', $artikel), [
            'menge' => 2,
            'zweck' => 'Projektmaterial',
        ]);

        $response->assertRedirect();

        $reservierung = LagerReservierung::query()->where('lager_artikel_id', $artikel->id)->firstOrFail();
        $this->assertSame(LagerReservierung::STATUS_RESERVIERT, $reservierung->status);
        $this->assertSame($user->id, $reservierung->angefordert_von_user_id);
        $this->assertSame(2.0, (float) $reservierung->menge);
        $this->assertSame(5.0, (float) $artikel->fresh()->bestand);
    }

    public function test_lager_reservation_can_be_issued_and_reduces_stock(): void
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'lager.reservierung.update');
        $artikel = $this->lagerArtikel(['bestand' => 5]);
        $reservierung = LagerReservierung::create([
            'lager_artikel_id' => $artikel->id,
            'angefordert_von_user_id' => User::factory()->create()->id,
            'menge' => 2,
            'status' => LagerReservierung::STATUS_RESERVIERT,
            'zweck' => 'Ausgabe',
        ]);

        $response = $this->actingAs($user)->put(route('lager.reservierung.update', $reservierung), [
            'status' => LagerReservierung::STATUS_AUSGEGEBEN,
            'bemerkung' => 'Ausgegeben an Projektteam.',
        ]);

        $response->assertRedirect();

        $this->assertSame(LagerReservierung::STATUS_AUSGEGEBEN, $reservierung->fresh()->status);
        $this->assertNotNull($reservierung->fresh()->ausgegeben_at);
        $this->assertSame(3.0, (float) $artikel->fresh()->bestand);
        $this->assertSame(1, LagerBewegung::query()->where('lager_reservierung_id', $reservierung->id)->count());
    }

    private function lagerArtikel(array $attributes = []): LagerArtikel
    {
        return LagerArtikel::create(array_merge([
            'name' => 'Kopierpapier A4',
            'kategorie' => 'Buero',
            'artikelnummer' => 'ART-' . uniqid(),
            'einheit' => 'Paket',
            'bestand' => 0,
            'mindestbestand' => 0,
            'aktiv' => true,
        ], $attributes));
    }

    private function givePermission(User $user, string $permissionName): void
    {
        $categoryId = Berechtigungskategorie::query()->firstOrCreate(
            ['name' => 'Lager'],
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
