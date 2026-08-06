<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bop_phase_schedules', function (Blueprint $table) {
            $table->unsignedTinyInteger('days_per_class')->default(2)->after('selected_classes');
            $table->json('class_date_assignments')->nullable()->after('days_per_class');
        });
    }

    public function down(): void
    {
        Schema::table('bop_phase_schedules', function (Blueprint $table) {
            $table->dropColumn(['days_per_class', 'class_date_assignments']);
        });
    }
};
