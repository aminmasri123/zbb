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
        Schema::create('bereiches', function (Blueprint $table) {
            $table->id();
            $table->string('name',30);
            $table->string('beschreibung',200)->nullable();
            $table->string('code', 10)->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bereiches');
    }
};
