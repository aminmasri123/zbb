<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projekts', 'participant_profile_settings')) {
            Schema::table('projekts', function (Blueprint $table) {
                $table->json('participant_profile_settings')->nullable()->after('portal_feature_settings');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('projekts', 'participant_profile_settings')) {
            Schema::table('projekts', fn (Blueprint $table) => $table->dropColumn('participant_profile_settings'));
        }
    }
};
