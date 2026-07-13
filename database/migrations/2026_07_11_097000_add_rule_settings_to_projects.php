<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projekts', function (Blueprint $table) {
            $table->json('rule_settings')->nullable()->after('feature_settings');
        });
    }

    public function down(): void
    {
        Schema::table('projekts', function (Blueprint $table) {
            $table->dropColumn('rule_settings');
        });
    }
};
