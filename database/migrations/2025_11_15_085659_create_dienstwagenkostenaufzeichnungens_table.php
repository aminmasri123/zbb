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
        Schema::create('dienstwagenkostenaufzeichnungens', function (Blueprint $table) {
             $table->id();

            // Verknüpfung zum Dienstwagen (Fremdschlüssel)
            $table->foreignId('dienstwagen_id')->constrained('dienstwagens')->onDelete('cascade');

            // Kostenart: z. B. Tankkosten, Reparatur, Versicherung, Leasing usw.
            $table->string('art');

            // Datum der Kostenbuchung
            $table->date('datum');

            // Betrag der Kosten in Euro
            $table->decimal('betrag', 10, 2);

            // Beschreibung oder zusätzliche Angaben (optional)
            $table->string('beschreibung')->nullable();

            // Erstellt- und Aktualisiert-Zeitstempel
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dienstwagenkostenaufzeichnungens');
    }
};
