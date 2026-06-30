<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('einteilung_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekts')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('schuljahr');
            $table->string('teil');
            $table->unsignedTinyInteger('runden_anzahl')->default(3);
            $table->unsignedSmallInteger('standard_kapazitaet')->default(15);
            $table->foreignId('user_create')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_update')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['projekt_id', 'partner_id', 'schuljahr', 'teil'],
                'einteilung_settings_context_unique'
            );
        });

        Schema::create('einteilung_bereich_kapazitaeten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('einteilung_setting_id')->constrained('einteilung_settings')->cascadeOnDelete();
            $table->foreignId('bereich_id')->constrained('bereiches')->cascadeOnDelete();
            $table->unsignedSmallInteger('plaetze')->default(15);
            $table->timestamps();

            $table->unique(
                ['einteilung_setting_id', 'bereich_id'],
                'einteilung_kapazitaet_bereich_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('einteilung_bereich_kapazitaeten');
        Schema::dropIfExists('einteilung_settings');
    }
};
