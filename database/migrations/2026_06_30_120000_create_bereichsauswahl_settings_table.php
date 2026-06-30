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
        Schema::create('bereichsauswahl_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekts')->onDelete('cascade');
            $table->foreignId('partner_id')->constrained('partners')->onDelete('cascade');
            $table->string('schuljahr');
            $table->string('teil');
            $table->unsignedTinyInteger('auswahl_anzahl')->default(4);
            $table->string('public_token', 80)->unique();
            $table->boolean('zugang_aktiv')->default(true);
            $table->foreignId('user_create')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_update')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['projekt_id', 'partner_id', 'schuljahr', 'teil'],
                'bereichsauswahl_settings_context_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bereichsauswahl_settings');
    }
};
