<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_completion_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projekts')->cascadeOnDelete();
            $table->string('label', 150);
            $table->string('description', 500)->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'active', 'sort_order'], 'completion_items_project_idx');
        });

        Schema::create('participation_completion_checklist_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_person_id')->constrained('projekt_has_personens', indexName: 'completion_check_participation_fk')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained('project_completion_checklist_items', indexName: 'completion_check_item_fk')->cascadeOnDelete();
            $table->boolean('completed')->default(false);
            $table->text('note')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users', indexName: 'completion_check_user_fk')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['project_person_id', 'checklist_item_id'], 'completion_check_unique');
        });

        Schema::create('participation_completion_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_person_id')->constrained('projekt_has_personens')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->enum('status', ['submitted', 'approved', 'rejected'])->default('submitted');
            $table->enum('completion_type', ['completed', 'terminated']);
            $table->date('exit_date');
            $table->string('outcome', 255);
            $table->text('summary');
            $table->text('recommendations')->nullable();
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->unique(['project_person_id', 'version'], 'completion_report_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_completion_reports');
        Schema::dropIfExists('participation_completion_checklist_completions');
        Schema::dropIfExists('project_completion_checklist_items');
    }
};
