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
        Schema::create('personen_has_zielgruppes', function (Blueprint $table) {
            $table->foreignId('person_id')->constrained('personens')->cascadeOnDelete();
            $table->foreignId('zielgruppe_id')->constrained('zielgruppes')->cascadeOnDelete();
            $table->primary(['person_id', 'zielgruppe_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personen_has_zielgruppes');
    }
};
