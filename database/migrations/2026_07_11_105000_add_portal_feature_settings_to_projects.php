<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projekts', function (Blueprint $table) {
            $table->json('portal_feature_settings')->nullable()->after('rule_settings');
        });
    }

    public function down(): void
    {
        Schema::table('projekts', fn (Blueprint $table) => $table->dropColumn('portal_feature_settings'));
    }
};
