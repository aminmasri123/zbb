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
        Schema::create('geraetausgabes', function (Blueprint $table) {
            $table->id();
            $table->string('ausgabescheinNr', 25);
            $table->foreignId('ausleiher_id')->constrained('personens')->onDelete('cascade');
            $table->foreignId('projekte_id')->constrained('projekts')->onDelete('cascade');
            $table->date('ausgabe');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geraetausgabes');
    }
};
