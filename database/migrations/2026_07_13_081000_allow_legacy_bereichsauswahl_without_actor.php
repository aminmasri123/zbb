<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE bereichsauswahls MODIFY user_create BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('UPDATE bereichsauswahls SET user_create = user_update WHERE user_create IS NULL AND user_update IS NOT NULL');
            $fallbackUserId = DB::table('users')->orderBy('id')->value('id');
            if ($fallbackUserId) {
                DB::table('bereichsauswahls')->whereNull('user_create')->update(['user_create' => $fallbackUserId]);
            }
            DB::statement('ALTER TABLE bereichsauswahls MODIFY user_create BIGINT UNSIGNED NOT NULL');
        }
    }
};
