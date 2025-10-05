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
        Schema::create('projekts', function (Blueprint $table) {
            $table->id();
            $table->string('name',30);
            $table->string('kostenstelle',7)->unique();
            $table->foreignId('abteilung_id')->constrained();
            $table->text('beschreibung')->nullable();
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
        Schema::dropIfExists('projekts');
    }
};
