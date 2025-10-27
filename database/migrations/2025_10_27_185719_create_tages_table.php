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
        Schema::create('tages', function (Blueprint $table) {
            $table->id();
            $table->date('datum')->unique();
            $table->enum('wochentag', ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag']);
            $table->enum('feiertag_typ', ['kein_feiertag', 'gesetzlicher_feiertag', 'betrieblich_freier_tag'])->default('kein_feiertag');
            $table->string('feiertag_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tages');
    }
};
