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
        Schema::create('zielgruppes', function (Blueprint $table) {
            $table->id();
            $table->string('bezeichnung');
            $table->text('beschreibung')->nullable();
            $table->timestamps();
        });
    }

    /* Arbeitslos
        Langzeitarbeitslos
        Nichterwerbstätig
        Nichterwerbstätige, die keine schriftliche oder berufliche Ausbildung absolvieren
        Erwerbstätige
        Selbstständige
        Auszubildende
        Berufsschüler
        Schüler allgemeinbildender Schulen

        Flüchtinge
        KMU
        */
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zielgruppes');
    }
};
