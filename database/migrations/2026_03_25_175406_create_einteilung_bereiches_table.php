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
        Schema::create('einteilung_bereiches', function (Blueprint $table) {
            $table->id();
            $table->morphs('teilnehmende'); // erstellt person_id + person_type
            $table->foreignId('bereich_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('runde'); // 1, 2, 3
            $table->timestamps();
            // 🔥 verhindert doppelte Einteilungen
            $table->unique(['teilnehmende_id', 'runde']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('einteilung_bereiches');
    }
};
