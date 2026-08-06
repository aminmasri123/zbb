<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bop_phase_schedules', function (Blueprint $table) {
            $table->json('part_date_assignments')->nullable()->after('class_date_assignments');
        });
    }

    public function down(): void
    {
        Schema::table('bop_phase_schedules', function (Blueprint $table) {
            $table->dropColumn('part_date_assignments');
        });
    }
};
