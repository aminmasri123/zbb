<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokument_pakete', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('beschreibung')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });

        Schema::create('dokument_paket_has_dokumentes', function (Blueprint $table) {
            $table->foreignId('dokument_paket_id')->constrained('dokument_pakete')->cascadeOnDelete();
            $table->foreignId('dokument_id')->constrained('dokumentes')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['dokument_paket_id', 'dokument_id'], 'dokument_paket_dokument_primary');
        });

        Schema::create('projekt_has_dokument_paketes', function (Blueprint $table) {
            $table->foreignId('projekt_id')->constrained('projekts')->cascadeOnDelete();
            $table->foreignId('dokument_paket_id')->constrained('dokument_pakete')->cascadeOnDelete();
            $table->primary(['projekt_id', 'dokument_paket_id'], 'projekt_dokument_paket_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projekt_has_dokument_paketes');
        Schema::dropIfExists('dokument_paket_has_dokumentes');
        Schema::dropIfExists('dokument_pakete');
    }
};
