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
        Schema::create('geraet_has_rueckgabes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geraet_id')->constrained('geraets')->onDelete('cascade');
            $table->foreignId('rueckgabe_id')->constrained('geraetrueckgabes')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geraet_has_rueckgabes');
    }
};
