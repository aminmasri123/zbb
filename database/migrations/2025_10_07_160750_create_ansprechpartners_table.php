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
        Schema::create('ansprechpartners', function (Blueprint $table) {
            $table->id();
            $table->string('vorname',50);
            $table->string('nachname',50);
            $table->enum('geschlecht', ['w', 'm', 'd']);
            $table->string('typ',50);
            $table->foreignId('partner_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ansprechpartners');
    }
};
