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
        Schema::create('gruppe_has_teilnehmers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_has_bereich_id')->constrained('projekt_has_bereiches')->onDelete('cascade');
            $table->foreignId('personen_id')->constrained()->onDelete('cascade');
            $table->date('beitrittsdatum')->nullable();
            $table->date('austrittsdatum')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gruppe_has_teilnehmers');
    }
};
