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
        Schema::create('personen_ist_schuelers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('personens')->onDelete('cascade');
            $table->string('klasse');
            $table->foreignId('schule_id')->constrained('partners')->onDelete('cascade');
            $table->boolean('foerderschueler')->default(false);
            $table->boolean('eee')->default(false);
            $table->string('schuljahr'); // z.B. 2024/2025
            $table->string('teil');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personen_ist_schuelers');
    }
};
