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
        Schema::create('geraetrueckgabes', function (Blueprint $table) {
            $table->id();
            $table->string('rueckgabescheinNr',20);
            $table->foreignId('ausleiher_id')->constrained('personens')->onDelete('cascade');
            $table->foreignId('ausgabe_id')->constrained('geraetausgabes')->onDelete('cascade');
            $table->date('rueckgabe');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geraetrueckgabes');
    }
};
