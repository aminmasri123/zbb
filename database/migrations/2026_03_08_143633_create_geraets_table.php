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
         Schema::create('geraets', function (Blueprint $table) {
            $table->id();
            $table->string('sn',20);
            $table->string('productID',30);
            $table->string('zustand',50);
            $table->string('geraet',20);
            $table->string('imLager',200)->nullable();
            $table->string('hersteller',20);
            $table->string('modell',20)->nullable();
            $table->date('baujahr')->nullable();
            $table->date('garantiefrist')->nullable();
            $table->boolean('verfuegbarkeit')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geraets');
    }
};
