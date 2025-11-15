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
        Schema::create('dienstwagenwartungsaufzeichnungens', function (Blueprint $table) {
            $table->id();

            // Verknüpfung zum Dienstwagen (Fremdschlüssel)
            $table->foreignId('dienstwagen_id')->constrained('dienstwagens')->onDelete('cascade');

            // Art der Wartung: z. B. Service, Reparatur, Inspektion
            $table->string('art');

            // Datum der Wartung
            $table->date('datum');

            // Kilometerstand zum Zeitpunkt der Wartung
            $table->integer('kilometerstand');

            // Name oder Ort der Werkstatt (optional)
            $table->string('werkstatt')->nullable();

            // Kosten der Wartung (optional)
            $table->decimal('kosten', 10, 2)->nullable();

            // Zusätzliche Notizen oder Bemerkungen (optional)
            $table->text('notizen')->nullable();

            // Erstellt-/Aktualisiert-Zeitstempel
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dienstwagenwartungsaufzeichnungens');
    }
};
