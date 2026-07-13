<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modules')
            ->whereIn('key', ['it_management', 'warehouse_management', 'vehicle_management'])
            ->update([
                'is_enforced' => true,
                'supports_location_scope' => false,
            ]);
    }

    public function down(): void
    {
        DB::table('modules')
            ->whereIn('key', ['it_management', 'warehouse_management', 'vehicle_management'])
            ->update(['is_enforced' => false]);
    }
};
