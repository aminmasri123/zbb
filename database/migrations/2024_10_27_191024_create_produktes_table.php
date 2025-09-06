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
        Schema::create('produktes', function (Blueprint $table) {
            $table->id();
            $table->string('name',30);
            $table->string('artikelnummer',20)->nullable();
            $table->string('link',100)->nullable();
            $table->float('preis')->nullable();
            $table->float('anzahl')->nullable();
            $table->enum('status', ['Abgelehnt', 'Versandt', 'Zugestellt', 'Stoniert', 'Zurückgesendet', 'Abgeschossen']);
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
        Schema::dropIfExists('produktes');
    }
};
