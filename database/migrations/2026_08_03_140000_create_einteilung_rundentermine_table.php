<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('einteilung_rundentermine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('einteilung_setting_id')->constrained('einteilung_settings')->cascadeOnDelete();
            $table->unsignedTinyInteger('runde');
            $table->date('anfangsdatum');
            $table->date('enddatum');
            $table->time('startzeit');
            $table->time('endzeit');
            $table->timestamps();

            $table->unique(
                ['einteilung_setting_id', 'runde'],
                'einteilung_rundentermine_setting_runde_unique'
            );
        });

        // Bestehende, bereits generierte Gruppen als Rundentermine uebernehmen.
        DB::table('einteilung_settings')
            ->orderBy('id')
            ->each(function ($setting): void {
                for ($runde = 1; $runde <= (int) $setting->runden_anzahl; $runde++) {
                    $bemerkung = "BOP Einteilung Schule {$setting->partner_id} Schuljahr {$setting->schuljahr} Teil {$setting->teil} Runde {$runde}";
                    $gruppe = DB::table('gruppes')
                        ->where('projekt_id', $setting->projekt_id)
                        ->where('bemerkung', $bemerkung)
                        ->whereNotNull('anfangsdatum')
                        ->whereNotNull('enddatum')
                        ->whereNotNull('startzeit')
                        ->whereNotNull('endzeit')
                        ->orderBy('id')
                        ->first();

                    if (!$gruppe) {
                        continue;
                    }

                    DB::table('einteilung_rundentermine')->insert([
                        'einteilung_setting_id' => $setting->id,
                        'runde' => $runde,
                        'anfangsdatum' => $gruppe->anfangsdatum,
                        'enddatum' => $gruppe->enddatum,
                        'startzeit' => $gruppe->startzeit,
                        'endzeit' => $gruppe->endzeit,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('einteilung_rundentermine');
    }
};
