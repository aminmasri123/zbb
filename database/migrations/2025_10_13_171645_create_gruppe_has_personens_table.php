<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('gruppe_has_personens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personen_id')->constrained()->onDelete('cascade');
            $table->foreignId('gruppe_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

   
    public function down(): void
    {
        Schema::dropIfExists('gruppe_has_personens');
    }
};
