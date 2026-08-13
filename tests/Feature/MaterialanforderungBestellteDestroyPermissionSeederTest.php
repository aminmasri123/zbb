<?php

namespace Tests\Feature;

use Database\Seeders\MaterialanforderungBestellteDestroyPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaterialanforderungBestellteDestroyPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_permission_and_can_be_run_repeatedly(): void
    {
        DB::table('permissions')
            ->where('name', 'materialanforderung.bestellte.destroy')
            ->where('guard_name', 'web')
            ->delete();

        $this->seed(MaterialanforderungBestellteDestroyPermissionSeeder::class);
        $this->seed(MaterialanforderungBestellteDestroyPermissionSeeder::class);

        $permission = DB::table('permissions')
            ->where('name', 'materialanforderung.bestellte.destroy')
            ->where('guard_name', 'web')
            ->get();

        $this->assertCount(1, $permission);
        $this->assertSame('Bestellte Materialanforderungen löschen', $permission->first()->beschreibung);

        $this->assertDatabaseHas('berechtigungskategories', [
            'id' => $permission->first()->berechtigungskategorie_id,
            'name' => 'Bestellungen',
        ]);
    }
}
