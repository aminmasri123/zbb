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
        Schema::create('raeumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standort_id')->constrained('standorts')->cascadeOnDelete();
            $table->string('name');
            $table->enum('typ', [
                'Büro',
                'Elektroraum',
                'Unterrichtsraum',
                'Seminarraum',
                'Besprechungsraum',
                'Labor',
                'Werkstatt',
                'Lager',
                'Küche',
                'Aufenthaltsraum',
                'Sanitärraum',
                'Empfang',
                'Serverraum',
                'Archiv',
                'Aula',
                'Bibliothek',
                'Arbeitsplatz',
                'Copyroom',
                'Technikraum',
                'Hauswirtschaftsraum',
                'Holzbereich',
                'Metallbereich'
            ]);

            $table->unsignedInteger('kapazitaet')->nullable();
            $table->string('beschreibung')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raeumes');
    }
};
