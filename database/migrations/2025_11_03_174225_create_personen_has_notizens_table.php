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
        Schema::create('personen_has_notizens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('personens')->cascadeOnDelete(); //die Person die Notizen Schreibt
            $table->foreignId('person_id')->constrained('personens')->cascadeOnDelete(); //von wem die Notizen geschrieben wird
            $table->foreignId('notiztyp_id')->constrained('notizvariantens')->cascadeOnDelete();
            $table->foreignId('prioritaet_id')->constrained('notizvariantens')->cascadeOnDelete();
            $table->foreignId('kategorie_id')->constrained('notizvariantens')->cascadeOnDelete();
            $table->string('titel');
            $table->text('notizinhalt');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personen_has_notizens');
    }
};
