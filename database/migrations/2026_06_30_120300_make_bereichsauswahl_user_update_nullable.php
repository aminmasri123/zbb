<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE bereichsauswahls MODIFY user_update BIGINT UNSIGNED NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('UPDATE bereichsauswahls SET user_update = user_create WHERE user_update IS NULL');
        DB::statement('ALTER TABLE bereichsauswahls MODIFY user_update BIGINT UNSIGNED NOT NULL');
    }
};
