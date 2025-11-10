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
        Schema::create('fahrtkostensaetzes', function (Blueprint $table) {
             $table->id();
            $table->foreignId('fahrtart_id')->constrained('fahrtartens')->cascadeOnDelete();

            $table->enum('rechentyp', ['pro_km', 'pro_fahrt', 'pro_monat', 'prozent']);
            $table->decimal('satz', 8, 2)->nullable(); //betrag oder protzent

            $table->date('gueltig_ab');
            $table->date('gueltig_bis')->nullable();

            $table->text('bemerkung')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fahrtkostensaetzes');
    }
};
