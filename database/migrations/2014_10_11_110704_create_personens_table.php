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
        Schema::create('personens', function (Blueprint $table) {
             $table->id();
            $table->string('vorname');
            $table->string('nachname');
            $table->enum('geschlecht', ['w', 'm', 'd']);
            $table->date('geburtsdatum')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->string('typ');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personens');
    }
};
