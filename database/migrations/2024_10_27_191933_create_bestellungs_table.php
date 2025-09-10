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
        Schema::create('bestellungs', function (Blueprint $table) {
            $table->id();
            $table->date('bestellungsdatum');
            $table->foreignId('kaeufer')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['Eingegangen', 'in Bearbeitung', 'Genehmigung austehend', 'Genehmigt', 'Abgelehnt', 'Warten auf Lieferung', 'Versandt', 'Zugestellt', 'Storniert', 'Komplett Zurückgesendet', 'teils Zurückgesendet', 'Abgeschlossen']);
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
        Schema::dropIfExists('bestellungs');
    }
};
