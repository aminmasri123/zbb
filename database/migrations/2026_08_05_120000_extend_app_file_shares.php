<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_shares')) {
            return;
        }

        Schema::table('app_shares', function (Blueprint $table) {
            if (! Schema::hasColumn('app_shares', 'team_id')) {
                $table->unsignedBigInteger('team_id')->nullable()->after('email');
                $table->index(['shareable_type', 'shareable_id', 'team_id'], 'app_shares_shareable_team_index');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE app_shares MODIFY permission ENUM('view', 'edit', 'manage') NOT NULL DEFAULT 'view'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('app_shares')) {
            return;
        }

        DB::table('app_shares')->where('permission', 'manage')->update(['permission' => 'edit']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE app_shares MODIFY permission ENUM('view', 'edit') NOT NULL DEFAULT 'view'");
        }

        Schema::table('app_shares', function (Blueprint $table) {
            if (Schema::hasColumn('app_shares', 'team_id')) {
                $table->dropIndex('app_shares_shareable_team_index');
                $table->dropColumn('team_id');
            }
        });
    }
};
