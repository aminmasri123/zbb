<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('potenzialanalyse_uebungen', function (Blueprint $table) {
            $table->boolean('auswertung_hervorheben')->default(false)->after('auswertbar');
        });
    }

    public function down(): void
    {
        Schema::table('potenzialanalyse_uebungen', function (Blueprint $table) {
            $table->dropColumn('auswertung_hervorheben');
        });
    }
};
