<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projekts', 'klassenbuch_aktiv')) {
            Schema::table('projekts', function (Blueprint $table) {
                $table->boolean('klassenbuch_aktiv')->default(false)->after('aktiv');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('projekts', 'klassenbuch_aktiv')) {
            Schema::table('projekts', function (Blueprint $table) {
                $table->dropColumn('klassenbuch_aktiv');
            });
        }
    }
};
