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
        Schema::create('dienstwagenfahrtenbuches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dienstwagen_id')->constrained('dienstwagens')->onDelete('cascade');
            $table->foreignId('person_id')->nullable()->constrained('personens')->onDelete('set null');
            $table->date('date');
            $table->integer('start_km');
            $table->integer('ende_km');
            $table->string('zweck'); // geschäftlich, privat, Kunde, Lieferung
            $table->string('ziel');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dienstwagenfahrtenbuches');
    }
};
