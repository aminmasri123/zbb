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
        Schema::create('adresses', function (Blueprint $table) {
            $table->id();
            $table->string('model_type', 50); // z.B. 'App\Models\Teilnehmer'
            $table->unsignedBigInteger('model_id');
            $table->string('strasse')->nullable();
            $table->string('hausnummer', 10)->nullable();
            $table->string('plz', 10)->nullable();
            $table->string('stadt')->nullable();
            $table->string('land')->default('Deutschland');
            $table->string('zusatzinfo')->nullable(); // z. B. Etage, Zimmernummer
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('adresses');
    }
};
