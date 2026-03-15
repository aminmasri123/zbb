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
        Schema::create('materialanforderung_vergabevermerks', function (Blueprint $table) {
             $table->id();
            $table->foreignId('anforderung_id')->constrained('materialanforderungs')->onDelete('cascade');
            $table->enum('lieferung_art', ['Lieferleistung', 'Dienstleistung'])->default('Lieferleistung');
            $table->text('begruendung')->nullable();
            $table->string('lieferant')->nullable();
            $table->enum('lieferung_option', ['per Abholung', 'per Lieferung'])->default('per Lieferung');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materialanforderung_vergabevermerks');
    }
};
