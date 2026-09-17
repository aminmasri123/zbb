<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PurchaseMigrationResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_migration_can_resume_when_its_tables_and_columns_already_exist(): void
    {
        DB::table('purchase_rules')->update([
            'approval_limit_cents' => 60000,
            'quote_limit_cents' => 60000,
        ]);
        $ruleId = DB::table('purchase_rules')->value('id');

        $migration = require database_path('migrations/2026_09_16_120000_extend_purchase_approvals.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('purchase_rules'));
        $this->assertTrue(Schema::hasTable('purchase_number_counters'));
        $this->assertTrue(Schema::hasTable('purchase_numbers'));
        $this->assertTrue(Schema::hasTable('purchase_offers'));
        $this->assertSame(1, DB::table('purchase_rules')->count());
        $this->assertSame($ruleId, DB::table('purchase_rules')->value('id'));
        $this->assertSame(60000, (int) DB::table('purchase_rules')->value('approval_limit_cents'));

        foreach ([
            'bestellnummer', 'standort_id', 'versand_netto', 'versand_mwst', 'lieferant_adresse',
            'lieferantenreferenz', 'approval_policy', 'revision', 'selected_offer_id', 'order_snapshot', 'bestellt_am',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('materialanforderungs', $column), $column);
        }
    }

    public function test_purchase_permissions_can_be_created_after_an_empty_permission_list_was_cached(): void
    {
        $names = ['materialanforderung.settings.manage', 'materialanforderung.gf_freigabe'];
        Permission::query()->whereIn('name', $names)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        try {
            Permission::findByName($names[0]);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            // Prime the same empty cache that caused the production migration to fail.
        }
        $administrator = Role::firstOrCreate(
            ['name' => 'Administrator', 'guard_name' => 'web'],
            ['color' => '#111827']
        );

        app(\Database\Seeders\PurchaseWorkflowPermissionSeeder::class)->run();

        $this->assertDatabaseHas('permissions', ['name' => $names[0], 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => $names[1], 'guard_name' => 'web']);
        $this->assertTrue($administrator->fresh()->hasPermissionTo($names[0]));
    }
}
