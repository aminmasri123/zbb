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
        Schema::create('projekt_has_personens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personen_id')->constrained()->onDelete('cascade');
            $table->foreignId('projekt_id')->constrained()->onDelete('cascade');

            $table->enum('status', ['angemeldet', 'aktiv', 'pausiert', 'abgeschlossen', 'abgebrochen'])->default('aktiv');
            $table->text('bemerkung')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projekt_has_personens');
    }
};
