<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gruppes', function (Blueprint $table) {
            $table->unsignedBigInteger('raum_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Ein Rueckbau auf NOT NULL waere nicht sicher, sobald Gruppen ohne Raum existieren.
    }
};
