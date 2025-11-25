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
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); //der User der die Anwesenheit erfasst
            $table->foreignId('gruppe_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('tage_id')->constrained()->onDelete('cascade');
            $table->foreignId('zeitgeplant_id')->constrained('zeitens')->onDelete('cascade');
            $table->foreignId('zeittatsaechlich_id')->nullable()->constrained('zeitens')->onDelete('cascade');
             $table->foreignId('anwesenheitsstatuten_id')->constrained()->onDelete('cascade');
            $table->string('bemerkung')->nullable();
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('gruppe_has_personens');
    }
};
