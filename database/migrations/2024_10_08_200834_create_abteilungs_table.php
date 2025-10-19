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
        Schema::create('abteilungs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->foreignId('personen_id')->nullable()->constrained()->nullOnDelete();         // setzt auf NULL, wenn User gelöscht wird
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
        Schema::dropIfExists('abteilungs');
    }
};
