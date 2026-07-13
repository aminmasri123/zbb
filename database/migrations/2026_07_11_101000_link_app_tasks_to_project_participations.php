<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_tasks', function (Blueprint $table) {
            $table->foreignId('project_person_id')
                ->nullable()
                ->after('project_id')
                ->constrained('projekt_has_personens', indexName: 'app_task_participation_fk')
                ->nullOnDelete();
            $table->index(['project_person_id', 'status', 'due_at'], 'app_task_participation_status_due');
        });
    }

    public function down(): void
    {
        Schema::table('app_tasks', function (Blueprint $table) {
            $table->dropIndex('app_task_participation_status_due');
            $table->dropForeign('app_task_participation_fk');
            $table->dropColumn('project_person_id');
        });
    }
};
