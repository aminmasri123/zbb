<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gruppes', 'standort_id')) {
            Schema::table('gruppes', function (Blueprint $table) {
                $table->foreignId('standort_id')
                    ->nullable()
                    ->after('projekt_id')
                    ->constrained('standorts')
                    ->nullOnDelete();
            });
        }

        DB::table('gruppes')
            ->join('raeumes', 'raeumes.id', '=', 'gruppes.raum_id')
            ->whereNotNull('gruppes.raum_id')
            ->update([
                'gruppes.standort_id' => DB::raw('raeumes.standort_id'),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('gruppes', 'standort_id')) {
            Schema::table('gruppes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('standort_id');
            });
        }
    }
};
