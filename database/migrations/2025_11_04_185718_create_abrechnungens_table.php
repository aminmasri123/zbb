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
        Schema::create('abrechnungens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('personens')->cascadeOnDelete();
            $table->unsignedTinyInteger('monat');
            $table->unsignedSmallInteger('jahr');
            $table->decimal('gesamtkosten', 10, 2)->default(0);
            $table->enum('status', ['offen','in_bearbeitung', 'abgerechnet', 'bezahlt', 'storniert'])->default('offen');
            $table->date('auszahldatum')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abrechnungens');
    }
};
