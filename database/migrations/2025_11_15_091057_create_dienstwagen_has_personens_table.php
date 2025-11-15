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
        Schema::create('dienstwagen_has_personens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dienstwagen_id')->constrained('dienstwagens')->onDelete('cascade');
            $table->foreignId('person_id')->constrained('personens')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dienstwagen_has_personens');
    }
};
