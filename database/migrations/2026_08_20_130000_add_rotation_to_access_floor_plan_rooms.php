<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_floor_plan_rooms', function (Blueprint $table) {
            $table->decimal('rotation_degrees', 7, 2)->default(0)->after('height_percent');
        });
    }

    public function down(): void
    {
        Schema::table('access_floor_plan_rooms', function (Blueprint $table) {
            $table->dropColumn('rotation_degrees');
        });
    }
};
