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
        DB::statement('ALTER TABLE bereichsauswahls MODIFY bereich_id1 BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE bereichsauswahls MODIFY bereich_id2 BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE bereichsauswahls MODIFY bereich_id3 BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE bereichsauswahls MODIFY bereich_id4 BIGINT UNSIGNED NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE bereichsauswahls MODIFY bereich_id1 BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE bereichsauswahls MODIFY bereich_id2 BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE bereichsauswahls MODIFY bereich_id3 BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE bereichsauswahls MODIFY bereich_id4 BIGINT UNSIGNED NOT NULL');
    }
};
