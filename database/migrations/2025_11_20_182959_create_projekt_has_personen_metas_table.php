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
        Schema::create('projekt_has_personen_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_person_id')->constrained('projekt_has_personens')->onDelete('cascade');
            $table->foreignId('betreuer_id')->nullable()->constrained('personens')->onDelete('set null'); //Betreuer ist eine Person die die Teilnehemer betreut
            $table->foreignId('austritt_id')->nullable()->constrained('austritttypens')->onDelete('set null');
            $table->foreignId('zielgruppe_id')->nullable()->constrained('zielgruppes')->onDelete('set null');
            $table->foreignId('projektabschluss_id')->nullable()->constrained('ergebnisses')->onDelete('set null');
            $table->foreignId('verbleib_id')->nullable()->constrained('verbleibteilnehmers')->onDelete('set null');
            $table->foreignId('massnahmebegleiter_id')->nullable()->constrained('personens')->onDelete('set null');
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('projekt_has_personen_metas');
    }
};
