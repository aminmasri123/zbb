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
        Schema::create('fahrtartens', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // z.B. "PKW", "ÖPNV Ticket"
            $table->string('beschreibung')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fahrtartens');
    }
};
