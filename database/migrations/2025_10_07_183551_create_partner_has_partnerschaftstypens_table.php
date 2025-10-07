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
        Schema::create('partner_has_partnerschaftstypens', function (Blueprint $table) {
            $table->id();
             $table->foreignId('partner_id')->constrained()->onDelete('cascade');
            $table->foreignId('partnerschaftstypen_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_has_partnerschaftstypens');
    }
};
