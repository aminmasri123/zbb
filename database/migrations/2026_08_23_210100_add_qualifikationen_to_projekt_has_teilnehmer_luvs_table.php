<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projekt_has_teilnehmer_luvs', function (Blueprint $table) {
            $table->text('qualifikationen')->nullable()->after('zielvereinbarung');
        });
    }

    public function down(): void
    {
        Schema::table('projekt_has_teilnehmer_luvs', function (Blueprint $table) {
            $table->dropColumn('qualifikationen');
        });
    }
};
