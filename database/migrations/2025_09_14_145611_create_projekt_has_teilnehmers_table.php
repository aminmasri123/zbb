<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('projekt_has_teilnehmers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teilnehmer_id')->constrained()->onDelete('cascade');
            $table->foreignId('projekt_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('projekt_has_teilnehmers');
    }
};
