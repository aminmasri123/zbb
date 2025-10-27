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
        Schema::create('personen_has_anwesenheitens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personen_id')->constrained()->onDelete('cascade'); //die Person die anwesend ist
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); //der User der die Anwesenheit erfasst
            $table->foreignId('tage_id')->constrained()->onDelete('cascade');
            $table->foreignId('zeiten_id')->constrained()->onDelete('cascade');
            $table->foreignId('anwesenheitsstatuten_id')->constrained()->onDelete('cascade');
            $table->string('bemerkung')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personen_has_anwesenheitens');
    }
};
