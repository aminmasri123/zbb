<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projekts', function (Blueprint $table) {
            $table->json('potenzialanalyse_auswertung_config')->nullable()->after('potenzialanalyse_tage');
        });

        Schema::table('potenzialanalyse_uebungen', function (Blueprint $table) {
            $table->string('ergebnis_typ', 20)->default('punkte')->after('auswertbar');
            $table->decimal('mindestwert', 10, 2)->default(0)->after('ergebnis_typ');
        });

        Schema::create('potenzialanalyse_uebung_kompetenzen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uebung_id')->constrained('potenzialanalyse_uebungen')->cascadeOnDelete();
            $table->string('merkmal', 80);
            $table->decimal('gewichtung', 7, 2)->default(100);
            $table->boolean('aktiv')->default(true);
            $table->timestamps();

            $table->unique(['uebung_id', 'merkmal'], 'pa_uebung_kompetenz_unique');
        });

        Schema::table('potenzialanalyse_berichte', function (Blueprint $table) {
            $table->string('generator_stil', 30)->nullable()->after('bericht_text');
            $table->json('generator_snapshot')->nullable()->after('generator_stil');
        });
    }

    public function down(): void
    {
        Schema::table('potenzialanalyse_berichte', function (Blueprint $table) {
            $table->dropColumn(['generator_stil', 'generator_snapshot']);
        });

        Schema::dropIfExists('potenzialanalyse_uebung_kompetenzen');

        Schema::table('potenzialanalyse_uebungen', function (Blueprint $table) {
            $table->dropColumn(['ergebnis_typ', 'mindestwert']);
        });

        Schema::table('projekts', function (Blueprint $table) {
            $table->dropColumn('potenzialanalyse_auswertung_config');
        });
    }
};
