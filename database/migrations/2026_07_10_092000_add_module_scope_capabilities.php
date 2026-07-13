<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->boolean('supports_location_scope')->default(false);
        });

        DB::table('modules')
            ->where('key', 'room_management')
            ->update(['supports_location_scope' => true]);

        DB::table('modules')
            ->where('key', 'bop')
            ->update([
                'is_enforced' => true,
                'supports_location_scope' => false,
            ]);
    }

    public function down(): void
    {
        DB::table('modules')
            ->where('key', 'bop')
            ->update(['is_enforced' => false]);

        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('supports_location_scope');
        });
    }
};
