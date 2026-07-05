<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('klassenbuch_typen', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('beschreibung')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });

        Schema::create('klassenbuecher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gruppe_id')->constrained('gruppes')->cascadeOnDelete();
            $table->foreignId('klassenbuch_typ_id')->nullable()->constrained('klassenbuch_typen')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titel');
            $table->string('schuljahr')->nullable();
            $table->unsignedTinyInteger('lehrjahr')->nullable();
            $table->string('status')->default('aktiv');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index(['gruppe_id', 'status']);
        });

        Schema::create('klassenbuch_wochen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klassenbuch_id')->constrained('klassenbuecher')->cascadeOnDelete();
            $table->unsignedSmallInteger('jahr');
            $table->unsignedTinyInteger('kalenderwoche');
            $table->date('start_datum');
            $table->date('end_datum');
            $table->string('status')->default('offen');
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['klassenbuch_id', 'jahr', 'kalenderwoche'], 'kb_wochen_unique');
            $table->index(['status', 'start_datum']);
        });

        Schema::create('klassenbuch_eintraege', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klassenbuch_woche_id')->constrained('klassenbuch_wochen')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('datum');
            $table->unsignedTinyInteger('stunde')->nullable();
            $table->string('fach')->nullable();
            $table->text('thema');
            $table->string('azubi_nummern')->nullable();
            $table->string('signum', 20)->nullable();
            $table->text('bemerkung')->nullable();
            $table->timestamps();

            $table->index(['klassenbuch_woche_id', 'datum', 'stunde'], 'kb_eintraege_sort_index');
        });

        Schema::create('klassenbuch_kommentare', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klassenbuch_woche_id')->constrained('klassenbuch_wochen')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('typ')->default('kommentar');
            $table->boolean('intern')->default(false);
            $table->text('text');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['klassenbuch_woche_id', 'intern']);
        });

        DB::table('klassenbuch_typen')->insert([
            [
                'slug' => 'fachpraxis',
                'name' => 'Fachpraxis',
                'beschreibung' => 'Klassenbuch fuer praktische Ausbildung im Gewerk.',
                'aktiv' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'unterricht',
                'name' => 'Unterricht',
                'beschreibung' => 'Klassenbuch fuer Unterricht, Fachtheorie oder Lehrkraft-Dokumentation.',
                'aktiv' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'sozialpaedagogik',
                'name' => 'Sozialpaedagogik',
                'beschreibung' => 'Optionales Klassenbuch fuer sozialpaedagogische Begleitung.',
                'aktiv' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'sonstiges',
                'name' => 'Sonstiges',
                'beschreibung' => 'Erweiterbarer Typ fuer weitere Projekte wie BvB.',
                'aktiv' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('klassenbuch_kommentare');
        Schema::dropIfExists('klassenbuch_eintraege');
        Schema::dropIfExists('klassenbuch_wochen');
        Schema::dropIfExists('klassenbuecher');
        Schema::dropIfExists('klassenbuch_typen');
    }
};
