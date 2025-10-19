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
        Schema::create('teilnehmers', function (Blueprint $table) {
            $table->id();
            $table->string('vorname',30);
            $table->string('nachname',30);
            $table->enum('geschlecht', ['w', 'm', 'd']);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->default(null); // optionaler Login
            $table->boolean('aktiv')->default(false);
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
        Schema::dropIfExists('teilnehmers');
    }
};
