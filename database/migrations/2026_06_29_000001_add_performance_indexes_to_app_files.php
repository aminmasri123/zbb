<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_files', function (Blueprint $table) {
            $table->index(['parent_id', 'type', 'name'], 'app_files_parent_type_name_index');
            $table->index(['owner_user_id', 'parent_id'], 'app_files_owner_parent_index');
            $table->index(['visibility', 'team_id', 'parent_id'], 'app_files_visibility_team_parent_index');
            $table->index(['visibility', 'project_id', 'parent_id'], 'app_files_visibility_project_parent_index');
            $table->index(['updated_at'], 'app_files_updated_at_index');
        });

        Schema::table('app_shares', function (Blueprint $table) {
            $table->index(['shareable_type', 'shareable_id', 'person_id'], 'app_shares_shareable_person_index');
            $table->index(['shareable_type', 'shareable_id', 'email'], 'app_shares_shareable_email_index');
        });
    }

    public function down(): void
    {
        Schema::table('app_shares', function (Blueprint $table) {
            $table->dropIndex('app_shares_shareable_person_index');
            $table->dropIndex('app_shares_shareable_email_index');
        });

        Schema::table('app_files', function (Blueprint $table) {
            $table->dropIndex('app_files_parent_type_name_index');
            $table->dropIndex('app_files_owner_parent_index');
            $table->dropIndex('app_files_visibility_team_parent_index');
            $table->dropIndex('app_files_visibility_project_parent_index');
            $table->dropIndex('app_files_updated_at_index');
        });
    }
};
