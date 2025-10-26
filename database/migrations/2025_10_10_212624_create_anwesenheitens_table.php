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
        Schema::create('anwesenheitens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personen_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('datum');
            $table->enum('status', ['anwesend', 'krank', 'entschuldigt', 'unentschuldigt', 'urlaub', 'feiertag'])->default('anwesend');
            $table->text('bemerkung')->nullable();
            $table->timestamps();

            $table->unique(['personen_id', 'datum'], 'anwesenheit_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anwesenheitens');
    }
};
