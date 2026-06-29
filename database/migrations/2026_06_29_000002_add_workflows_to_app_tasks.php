<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_task_workflow_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projekts')->nullOnDelete();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('visibility', ['private', 'all', 'team', 'project'])->default('private');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('app_task_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('app_task_workflow_templates')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assignee_person_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->enum('status', ['open', 'progress', 'done'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->unsignedInteger('due_offset_days')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('app_tasks', function (Blueprint $table) {
            $table->foreignId('workflow_template_id')->nullable()->after('team_id')->constrained('app_task_workflow_templates')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0)->after('priority');
            $table->timestamp('started_at')->nullable()->after('due_at');
            $table->timestamp('completed_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('app_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_template_id');
            $table->dropColumn(['sort_order', 'started_at', 'completed_at']);
        });

        Schema::dropIfExists('app_task_workflow_steps');
        Schema::dropIfExists('app_task_workflow_templates');
    }
};
