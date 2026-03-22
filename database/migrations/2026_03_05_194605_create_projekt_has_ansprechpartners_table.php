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
        Schema::create('projekt_has_ansprechpartners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekts')->onDelete('cascade');
            $table->foreignId('ansprechpartner_id')->constrained('partner_has_partnerschaftstypens')->onDelete('cascade');
            $table->foreignId('partnerschaftstypen_id')->constrained('partnerschaftstypens')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projekt_has_ansprechpartners');
    }
};
