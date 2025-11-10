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
        Schema::create('abrechnung_has_fartens', function (Blueprint $table) {
             $table->id();
            $table->foreignId('abrechnung_id')->constrained('abrechnungens')->cascadeOnDelete();
            $table->foreignId('fahrt_id')->constrained('fahrtens')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abrechnung_has_fartens');
    }
};
