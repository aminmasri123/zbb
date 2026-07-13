<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projekts', indexName: 'portal_course_project_fk')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users', indexName: 'portal_course_creator_fk')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('self_enrollment')->default(false);
            $table->timestamps();
            $table->index(['project_id', 'status', 'starts_at'], 'portal_course_project_status');
        });

        Schema::create('portal_course_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('portal_courses', indexName: 'portal_lesson_course_fk')->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->index(['course_id', 'published', 'sort_order'], 'portal_lesson_course_order');
        });

        Schema::create('portal_course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('portal_courses', indexName: 'portal_enrollment_course_fk')->cascadeOnDelete();
            $table->foreignId('project_person_id')->constrained('projekt_has_personens', indexName: 'portal_enrollment_participation_fk')->cascadeOnDelete();
            $table->enum('status', ['enrolled', 'in_progress', 'completed', 'cancelled'])->default('enrolled');
            $table->timestamp('enrolled_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'project_person_id'], 'portal_course_participation_unique');
        });

        Schema::create('portal_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('portal_course_enrollments', indexName: 'portal_progress_enrollment_fk')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('portal_course_lessons', indexName: 'portal_progress_lesson_fk')->cascadeOnDelete();
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['enrollment_id', 'lesson_id'], 'portal_enrollment_lesson_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_lesson_progress');
        Schema::dropIfExists('portal_course_enrollments');
        Schema::dropIfExists('portal_course_lessons');
        Schema::dropIfExists('portal_courses');
    }
};
