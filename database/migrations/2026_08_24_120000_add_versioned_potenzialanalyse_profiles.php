<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('potenzialanalyse_profile', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekts')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('name', 150);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('entwurf');
            $table->boolean('aktiv')->default(false);
            $table->json('bericht_config')->nullable();
            $table->timestamp('veroeffentlicht_at')->nullable();
            $table->timestamps();

            $table->unique(['projekt_id', 'key', 'version'], 'pa_profil_project_key_version_unique');
        });

        Schema::create('potenzialanalyse_profil_kompetenzen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profil_id')->constrained('potenzialanalyse_profile')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label', 150);
            $table->string('kategorie', 30);
            $table->string('kategorie_label', 100);
            $table->string('kategorie_code', 5);
            $table->text('beschreibung')->nullable();
            $table->text('selbsteinschaetzung_text')->nullable();
            $table->json('bewertungsbeschreibungen')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('aktiv')->default(true);
            $table->timestamps();

            $table->unique(['profil_id', 'key'], 'pa_profil_kompetenz_unique');
        });

        Schema::table('projekts', function (Blueprint $table) {
            $table->foreignId('potenzialanalyse_profil_id')
                ->nullable()
                ->after('potenzialanalyse_auswertung_config')
                ->constrained('potenzialanalyse_profile')
                ->nullOnDelete();
        });

        Schema::table('gruppes', function (Blueprint $table) {
            $table->foreignId('potenzialanalyse_profil_id')
                ->nullable()
                ->after('projekt_id')
                ->constrained('potenzialanalyse_profile')
                ->nullOnDelete();
        });

        Schema::table('potenzialanalyse_uebungen', function (Blueprint $table) {
            $table->foreignId('profil_id')
                ->nullable()
                ->after('projekt_id')
                ->constrained('potenzialanalyse_profile')
                ->nullOnDelete();
            $table->string('berechnungsregel', 30)->default('direkte_punkte')->after('ergebnis_typ');
            $table->decimal('fehler_abzug', 8, 2)->default(1)->after('berechnungsregel');
            $table->json('berechnungs_config')->nullable()->after('fehler_abzug');
        });

        Schema::table('potenzialanalyse_uebung_ergebnisse', function (Blueprint $table) {
            $table->unsignedInteger('fehler')->nullable()->after('punkte');
            $table->decimal('berechnete_punkte', 10, 2)->nullable()->after('fehler');
            $table->decimal('maximalpunkte_snapshot', 10, 2)->nullable()->after('berechnete_punkte');
            $table->decimal('fehler_abzug_snapshot', 8, 2)->nullable()->after('maximalpunkte_snapshot');
            $table->json('berechnungs_snapshot')->nullable()->after('fehler_abzug_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('potenzialanalyse_uebung_ergebnisse', function (Blueprint $table) {
            $table->dropColumn([
                'fehler',
                'berechnete_punkte',
                'maximalpunkte_snapshot',
                'fehler_abzug_snapshot',
                'berechnungs_snapshot',
            ]);
        });

        Schema::table('potenzialanalyse_uebungen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profil_id');
            $table->dropColumn(['berechnungsregel', 'fehler_abzug', 'berechnungs_config']);
        });

        Schema::table('gruppes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('potenzialanalyse_profil_id');
        });

        Schema::table('projekts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('potenzialanalyse_profil_id');
        });

        Schema::dropIfExists('potenzialanalyse_profil_kompetenzen');
        Schema::dropIfExists('potenzialanalyse_profile');
    }
};
