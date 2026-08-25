<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('potenzialanalyse_uebungen', function (Blueprint $table) {
            $table->boolean('zeit_erfassen')->default(false)->after('berechnungsregel');
        });

        // Preserve the previous behaviour for existing direct-point and time exercises.
        DB::table('potenzialanalyse_uebungen')
            ->whereIn('berechnungsregel', ['direkte_punkte', 'zeit'])
            ->update(['zeit_erfassen' => true]);
    }

    public function down(): void
    {
        Schema::table('potenzialanalyse_uebungen', function (Blueprint $table) {
            $table->dropColumn('zeit_erfassen');
        });
    }
};
