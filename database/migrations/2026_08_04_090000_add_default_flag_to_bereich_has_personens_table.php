<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bereich_has_personens') || Schema::hasColumn('bereich_has_personens', 'is_default')) {
            return;
        }

        Schema::table('bereich_has_personens', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('bereich_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bereich_has_personens') || ! Schema::hasColumn('bereich_has_personens', 'is_default')) {
            return;
        }

        Schema::table('bereich_has_personens', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
