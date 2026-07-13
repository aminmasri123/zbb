<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projekts', function (Blueprint $table) {
            $table->json('feature_settings')->nullable()->after('potenzialanalyse_tage');
        });
    }

    public function down(): void
    {
        Schema::table('projekts', function (Blueprint $table) {
            $table->dropColumn('feature_settings');
        });
    }
};
