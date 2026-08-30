<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bop_runs', function (Blueprint $table) {
            $table->json('break_defaults')->nullable()->after('planned_classes');
        });
    }

    public function down(): void
    {
        Schema::table('bop_runs', function (Blueprint $table) {
            $table->dropColumn('break_defaults');
        });
    }
};
