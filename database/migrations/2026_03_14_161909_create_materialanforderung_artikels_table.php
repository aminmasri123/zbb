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
        Schema::create('materialanforderung_artikels', function (Blueprint $table) {
             $table->id();
            $table->foreignId('anforderung_id')->constrained('materialanforderungs')->onDelete('cascade');
            $table->integer('pos');
            $table->text('link')->nullable();
            $table->string('artikel');
            $table->integer('stueck');
            $table->string('art_nr')->nullable();
            $table->decimal('einzelpreis', 10, 2);
            $table->decimal('gesamtpreis', 10, 2);
            $table->decimal('mwst', 5, 2)->default(19); // Steuer pro Position
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materialanforderung_artikels');
    }
};
