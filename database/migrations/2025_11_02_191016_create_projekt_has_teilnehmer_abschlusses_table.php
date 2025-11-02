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
        Schema::create('projekt_has_teilnehmer_abschlusses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_has_person_id')->constrained('projekt_has_personens')->cascadeOnDelete();
            $table->foreignId('austritttypen_id')->default(1)->constrained('austritttypens')->onDelete('cascade');
            $table->foreignId('ergebnisse_id')->default(1)->constrained('ergebnisses')->onDelete('cascade');
            $table->foreignId('verbleib_id')->nullable()->constrained('verbleibteilnehmers')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projekt_has_teilnehmer_abschlusses');
    }
};
