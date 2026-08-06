<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bop_runs', function (Blueprint $table) {
            $table->json('planned_classes')->nullable()->after('school_type');
        });
    }

    public function down(): void
    {
        Schema::table('bop_runs', function (Blueprint $table) {
            $table->dropColumn('planned_classes');
        });
    }
};
