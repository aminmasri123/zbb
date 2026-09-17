<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bereiches')) {
            return;
        }

        DB::table('bereiches')
            ->where('name', 'Metaltechnik')
            ->update(['name' => 'Metalltechnik']);
    }

    public function down(): void
    {
        // Die frühere Schreibweise war ein Tippfehler und wird nicht wiederhergestellt.
    }
};
