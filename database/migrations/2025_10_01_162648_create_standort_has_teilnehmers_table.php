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
        Schema::create('standort_has_teilnehmers', function (Blueprint $table) {
            $table->foreignId('teilnehmer_id')->constrained()->onDelete('cascade');
            $table->foreignId('standort_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('standort_has_teilnehmers');
    }
};
