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
        Schema::create('zeitraums', function (Blueprint $table) {
            $table->id();
            $table->date('von')->nullable();
            $table->date('bis')->nullable();
            $table->enum('typ', ['geplant', 'ist'])->default('geplant');
            $table->string('model_type',50);
            $table->unsignedBigInteger('model_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zeitraums');
    }
};
