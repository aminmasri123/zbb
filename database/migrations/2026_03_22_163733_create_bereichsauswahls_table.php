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
        Schema::create('bereichsauswahls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teilnehmer_id')->constrained('personen_ist_schuelers')->onDelete('cascade');
            $table->foreignId('bereich_id1')->constrained('bereiches')->onDelete('cascade')->nullable();
            $table->foreignId('bereich_id2')->constrained('bereiches')->onDelete('cascade')->nullable();
            $table->foreignId('bereich_id3')->constrained('bereiches')->onDelete('cascade')->nullable();
            $table->foreignId('bereich_id4')->constrained('bereiches')->onDelete('cascade')->nullable();
            $table->foreignId('user_create')->constrained('users')->onDelete('cascade');
            $table->foreignId('user_update')->constrained('users')->onDelete('cascade')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bereichsauswahls');
    }
};
