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
        Schema::create('gruppes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personen_id')->constrained()->onDelete('cascade');
            $table->foreignId('bereich_id')->constrained()->onDelete('cascade');
            $table->foreignId('projekt_id')->constrained('projekts')->onDelete('cascade');
            $table->foreignId('raum_id')->constrained('raeumes')->onDelete('cascade');
            $table->date('anfangsdatum')->nullable();
            $table->date('enddatum')->nullable();
            $table->time('startzeit')->nullable();
            $table->time('endzeit')->nullable();
            $table->text('bemerkung')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gruppes');
    }
};
