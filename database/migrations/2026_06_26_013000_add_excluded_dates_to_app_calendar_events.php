<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_calendar_events', function (Blueprint $table) {
            $table->json('excluded_dates')->nullable()->after('include_weekends');
        });
    }

    public function down(): void
    {
        Schema::table('app_calendar_events', function (Blueprint $table) {
            $table->dropColumn('excluded_dates');
        });
    }
};
