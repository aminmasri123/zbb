<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bereichsauswahl_settings', function (Blueprint $table) {
            $table->json('bereich_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bereichsauswahl_settings', function (Blueprint $table) {
            $table->dropColumn('bereich_ids');
        });
    }
};
