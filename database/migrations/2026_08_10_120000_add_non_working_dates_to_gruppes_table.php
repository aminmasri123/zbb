<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gruppes', function (Blueprint $table) {
            $table->json('non_working_dates')->nullable()->after('bemerkung');
        });
    }

    public function down(): void
    {
        Schema::table('gruppes', function (Blueprint $table) {
            $table->dropColumn('non_working_dates');
        });
    }
};
