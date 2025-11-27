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
        Schema::create('projekt_has_teilnehmer_luvs', function (Blueprint $table) {
            $table->id();
            $table->enum('typ', ['Start', 'Verlauf', 'Abschluss']);
            $table->foreignId('projekt_person_id')->constrained('projekt_has_personens')->onDelete('cascade');
            $table->date('von');
            $table->date('bis');
            $table->text('ausgangssituation');
            $table->text('zielvereinbarung');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projekt_has_teilnehmer_luvs');
    }
};
