<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('potenzialanalyse_uebungen', function (Blueprint $table) {
            $table->boolean('im_bericht_anzeigen')->default(true)->after('auswertung_hervorheben');
        });
    }

    public function down(): void
    {
        Schema::table('potenzialanalyse_uebungen', function (Blueprint $table) {
            $table->dropColumn('im_bericht_anzeigen');
        });
    }
};
