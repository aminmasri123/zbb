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
        Schema::create('zeitraums', function (Blueprint $table) {
            $table->id();
            $table->date('antragsdatum')->nullable();
            $table->date('starttermin')->nullable();
            $table->date('endtermin')->nullable();
            $table->date('anfangsdatum')->nullable();
            $table->date('enddatum')->nullable();
            $table->string('model_type',50);
            $table->unsignedBigInteger('model_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zeitraums');
    }
};
