<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('potenzialanalyse_uebungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekts')->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedTinyInteger('tag')->nullable();
            $table->text('beschreibung')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });

        Schema::create('potenzialanalyse_kriterien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uebung_id')->constrained('potenzialanalyse_uebungen')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('beschreibung')->nullable();
            $table->unsignedTinyInteger('skala_min')->default(1);
            $table->unsignedTinyInteger('skala_max')->default(5);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });

        Schema::create('potenzialanalyse_beurteilungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gruppe_id')->constrained('gruppes')->cascadeOnDelete();
            $table->foreignId('personen_id')->constrained('personens')->cascadeOnDelete();
            $table->foreignId('kriterium_id')->constrained('potenzialanalyse_kriterien')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('bewertung')->nullable();
            $table->text('bemerkung')->nullable();
            $table->timestamps();

            $table->unique(['gruppe_id', 'personen_id', 'kriterium_id'], 'pa_beurteilung_unique');
        });

        Schema::create('potenzialanalyse_selbsteinschaetzungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gruppe_id')->constrained('gruppes')->cascadeOnDelete();
            $table->foreignId('personen_id')->constrained('personens')->cascadeOnDelete();
            $table->foreignId('kriterium_id')->constrained('potenzialanalyse_kriterien')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('bewertung')->nullable();
            $table->text('bemerkung')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['gruppe_id', 'personen_id', 'kriterium_id'], 'pa_selbsteinschaetzung_unique');
        });

        Schema::create('potenzialanalyse_berichte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gruppe_id')->constrained('gruppes')->cascadeOnDelete();
            $table->foreignId('personen_id')->constrained('personens')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('entwurf');
            $table->text('staerken')->nullable();
            $table->text('entwicklungsfelder')->nullable();
            $table->text('empfehlung')->nullable();
            $table->longText('bericht_text')->nullable();
            $table->timestamp('fertiggestellt_at')->nullable();
            $table->timestamps();

            $table->unique(['gruppe_id', 'personen_id'], 'pa_bericht_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('potenzialanalyse_berichte');
        Schema::dropIfExists('potenzialanalyse_selbsteinschaetzungen');
        Schema::dropIfExists('potenzialanalyse_beurteilungen');
        Schema::dropIfExists('potenzialanalyse_kriterien');
        Schema::dropIfExists('potenzialanalyse_uebungen');
    }
};
