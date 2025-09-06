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
        Schema::create('projekt_has_bereiches', function (Blueprint $table) {
            $table->foreignId('projekt_id')->constrained('projekts')->onDelete('cascade');
            $table->foreignId('bereich_id')->constrained('bereiches')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('projekt_has_bereiches');
    }
};
