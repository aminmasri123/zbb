<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unterweisung_katalog', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->json('themen');
        });
        Schema::table('bereiches', fn (Blueprint $table) => $table->json('unterweisung_abweichungen')->nullable());

        $standard = config('unterweisung.themen', []);
        DB::transaction(function () use ($standard) {
            DB::table('bereiches')->whereNotNull('unterweisung_themenliste')
                ->select('id', 'unterweisung_themenliste')->chunkById(200, function ($bereiche) use ($standard) {
                    foreach ($bereiche as $bereich) {
                        $liste = json_decode($bereich->unterweisung_themenliste, true, flags: JSON_THROW_ON_ERROR);
                        $abweichungen = array_diff_assoc($liste, $standard)
                            + array_fill_keys(array_keys(array_diff_key($standard, $liste)), null);
                        DB::table('bereiches')->where('id', $bereich->id)
                            ->update(['unterweisung_abweichungen' => json_encode($abweichungen, JSON_THROW_ON_ERROR)]);
                    }
                });
            DB::table('unterweisung_katalog')->insert(['id' => 1, 'themen' => json_encode($standard, JSON_THROW_ON_ERROR)]);
        });
    }

    public function down(): void
    {
        Schema::table('bereiches', fn (Blueprint $table) => $table->dropColumn('unterweisung_abweichungen'));
        Schema::dropIfExists('unterweisung_katalog');
    }
};
