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
        Schema::create('fahrtens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('personens')->cascadeOnDelete();
            $table->foreignId('personal_id')->constrained('personens')->cascadeOnDelete();

            $table->foreignId('fahrtart_id')->constrained('fahrtartens')->cascadeOnDelete();

            $table->date('datum');
            $table->string('start')->nullable();
            $table->string('ziel')->nullable();
            $table->decimal('entfernung_km', 6, 2)->nullable();
            $table->decimal('kosten_berechnet', 10, 2)->nullable();


            $table->enum('status', ['offen','in bearbeitung', 'abgerechnet', 'bezahlt', 'storniert'])->default('offen');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fahrtens');
    }
};
