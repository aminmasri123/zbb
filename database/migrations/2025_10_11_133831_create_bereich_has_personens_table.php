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
        Schema::create('bereich_has_personens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_has_personen_id')->constrained()->onDelete('cascade');
            $table->foreignId('bereich_id')->constrained()->onDelete('cascade');
            $table->text('bemerkung')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bereich_has_personens');
    }
};
