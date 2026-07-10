<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE potenzialanalyse_uebungen ADD hoechstwert INT UNSIGNED NULL AFTER beschreibung');
        DB::statement('ALTER TABLE potenzialanalyse_uebungen ADD auswertbar TINYINT(1) NOT NULL DEFAULT 0 AFTER hoechstwert');

        if (! Schema::hasTable('potenzialanalyse_uebung_ergebnisse')) {
            Schema::create('potenzialanalyse_uebung_ergebnisse', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gruppe_id')->constrained('gruppes')->cascadeOnDelete();
                $table->foreignId('personen_id')->constrained('personens')->cascadeOnDelete();
                $table->foreignId('uebung_id')->constrained('potenzialanalyse_uebungen')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('punkte')->nullable();
                $table->unsignedInteger('zeit')->nullable();
                $table->timestamps();

                $table->unique(['gruppe_id', 'personen_id', 'uebung_id'], 'pa_uebung_ergebnis_unique');
            });
        }

        if (! Schema::hasTable('potenzialanalyse_kompetenzbewertungen')) {
            Schema::create('potenzialanalyse_kompetenzbewertungen', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gruppe_id')->constrained('gruppes')->cascadeOnDelete();
                $table->foreignId('personen_id')->constrained('personens')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('typ', 20);
                $table->string('merkmal', 80);
                $table->unsignedTinyInteger('bewertung')->nullable();
                $table->text('bemerkung')->nullable();
                $table->timestamps();

                $table->unique(['gruppe_id', 'personen_id', 'typ', 'merkmal'], 'pa_kompetenz_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('potenzialanalyse_kompetenzbewertungen');
        Schema::dropIfExists('potenzialanalyse_uebung_ergebnisse');

        if (Schema::hasTable('potenzialanalyse_uebungen')) {
            if (Schema::hasColumn('potenzialanalyse_uebungen', 'auswertbar')) {
                Schema::table('potenzialanalyse_uebungen', function (Blueprint $table) {
                    $table->dropColumn('auswertbar');
                });
            }

            if (Schema::hasColumn('potenzialanalyse_uebungen', 'hoechstwert')) {
                Schema::table('potenzialanalyse_uebungen', function (Blueprint $table) {
                    $table->dropColumn('hoechstwert');
                });
            }
        }
    }
};
