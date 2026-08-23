<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_report_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('run_uuid', 36)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('project_id');
            $table->foreign('project_id')->references('id')->on('projekts')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('personens')->cascadeOnDelete();
            $table->enum('report_type', ['luv', 'interim', 'final']);
            $table->date('from_date');
            $table->date('until_date');
            $table->text('request');
            $table->enum('status', ['queued', 'running', 'completed', 'failed'])->default('queued');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->json('report')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'ai_report_runs_user_status_idx');
            $table->index(['user_id', 'created_at'], 'ai_report_runs_user_created_idx');
            $table->index(['project_id', 'report_type', 'status'], 'ai_report_runs_context_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_report_runs');
    }
};

