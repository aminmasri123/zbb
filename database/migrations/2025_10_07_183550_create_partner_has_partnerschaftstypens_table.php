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
        Schema::create('partner_has_partnerschaftstypens', function (Blueprint $table) {
           $table->id();

            // 🔗 Beziehung zum Partner
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();

            // 🔗 Beziehung zum Partnerschaftstyp
            $table->foreignId('partnerschaftstypen_id')->constrained('partnerschaftstypens')->cascadeOnDelete();

            // 🔗 Ansprechpartner (Person)
            $table->foreignId('ansprechpartner_id')->nullable()->constrained('personens')->nullOnDelete();

            // ❗ Rolle, Aufgabe oder Funktion
            $table->string('rolle')->nullable();

            $table->timestamps();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_has_partnerschaftstypens');
    }
};
