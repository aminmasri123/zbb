<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_floor_plans', function (Blueprint $table) {
            $table->boolean('layout_locked')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('access_floor_plans', function (Blueprint $table) {
            $table->dropColumn('layout_locked');
        });
    }
};
