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
        Schema::create('personen_has_bildungsmassnahmens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('personens')->onDelete('cascade');
            $table->enum('typ', ['Praktikum', 'Fortbildung', 'Schulung', 'Weiterbildung', 'Sprachkurs', 'Integrationskurs']);
            $table->date('start');
            $table->date('end');
            $table->enum('status', ['geplant', 'laufend', 'abgeschlossen', 'abgebrochen'])->default('geplant');
            $table->string('traeger')->nullable(); // Schule, Unternehmen, Bildungsträger
            $table->text('bemerkung')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personen_has_bildungsmassnahmens');
    }
};
