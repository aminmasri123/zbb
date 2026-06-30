<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projekt_has_kostenstelles', function (Blueprint $table) {
            $table->date('gueltig_von')->nullable()->after('kostenstelle_id');
            $table->date('gueltig_bis')->nullable()->after('gueltig_von');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projekt_has_kostenstelles', function (Blueprint $table) {
            $table->dropColumn(['gueltig_von', 'gueltig_bis']);
        });
    }
};
