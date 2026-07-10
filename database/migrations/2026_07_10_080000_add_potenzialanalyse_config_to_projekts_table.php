<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projekts', 'potenzialanalyse_aktiv')) {
            Schema::table('projekts', function (Blueprint $table) {
                $table->boolean('potenzialanalyse_aktiv')->default(false)->after('klassenbuch_aktiv');
            });
        }

        if (! Schema::hasColumn('projekts', 'potenzialanalyse_tage')) {
            Schema::table('projekts', function (Blueprint $table) {
                $table->unsignedTinyInteger('potenzialanalyse_tage')->nullable()->after('potenzialanalyse_aktiv');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('projekts', 'potenzialanalyse_tage')) {
            Schema::table('projekts', function (Blueprint $table) {
                $table->dropColumn('potenzialanalyse_tage');
            });
        }

        if (Schema::hasColumn('projekts', 'potenzialanalyse_aktiv')) {
            Schema::table('projekts', function (Blueprint $table) {
                $table->dropColumn('potenzialanalyse_aktiv');
            });
        }
    }
};
