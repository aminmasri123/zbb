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
        Schema::create('dienstwagens', function (Blueprint $table) {
            $table->id();

            // Fahrzeugtyp: z. B. PKW, LKW, Transporter
            $table->string('typ');

            // Kennzeichen (eindeutig)
            $table->string('kennzeichen')->unique();

            // Fahrzeugmarke und Modell
            $table->string('marke');
            $table->string('modell');

            // Baujahr
            $table->integer('baujahr');

            // Kraftstoffart: Benzin, Diesel, Elektro, Hybrid usw.
            $table->string('kraftstoffart');

            // Aktueller Kilometerstand
            $table->integer('kilometerstand')->default(0);

            // Status: verfügbar, in Nutzung, Werkstatt, außer Betrieb
            $table->enum('status', ['verfügbar', 'in Nutzung', 'Werkstatt', 'außer Betrieb', 'verkaüft', 'passiv'])->default('verfügbar');

            // Verknüpfung zum Standort (optional)
            $table->foreignId('standort_id')->constrained('standorts')->cascadeOnDelete()->nullable();

            // Datum der nächsten Wartung (optional)
            $table->date('naechste_wartung')->nullable();

            // Erstellt- und Aktualisiert-Zeitstempel
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dienstwagens');
    }
};
