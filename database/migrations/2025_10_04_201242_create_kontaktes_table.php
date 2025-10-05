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
        Schema::create('kontaktes', function (Blueprint $table) {
            $table->id();
            $table->string('model_type',50);
            $table->unsignedBigInteger('model_id');
            $table->foreignId('kontakttyp_id')->constrained('kontakttypens')->onDelete('cascade');
            $table->string('wert',100);
            $table->string('bemerkung',200)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kontaktes');
    }
};
