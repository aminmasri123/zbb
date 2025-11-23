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
        Schema::create('personen_has_sozialedatens', function (Blueprint $table) {
             $table->id();
            $table->foreignId('person_id')->constrained('personens')->cascadeOnDelete();
            $table->string('kundennummer')->nullable();
            $table->boolean('drittstaatsangehoerig')->default(false);
            $table->boolean('gefluechtet')->default(false);
            $table->boolean('migrationshintergrund')->default(false);
            $table->boolean('behinderung')->default(false);
            $table->boolean('wohnsitz_stabil')->default(true);
            $table->foreignId('leistungsbezug_id')->nullable()->constrained('leistungsbezueges')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personen_has_sozialedatens');
    }
};
