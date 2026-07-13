<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_intake_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projekts', indexName: 'intake_item_project_fk')->cascadeOnDelete();
            $table->string('label', 150);
            $table->string('description', 500)->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'active', 'sort_order'], 'project_intake_items_lookup');
        });

        Schema::create('participation_intake_checklist_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_person_id')->constrained('projekt_has_personens', indexName: 'intake_completion_participation_fk')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained('project_intake_checklist_items', indexName: 'intake_completion_item_fk')->cascadeOnDelete();
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users', indexName: 'intake_completion_user_fk')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_person_id', 'checklist_item_id'], 'participation_intake_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_intake_checklist_completions');
        Schema::dropIfExists('project_intake_checklist_items');
    }
};
