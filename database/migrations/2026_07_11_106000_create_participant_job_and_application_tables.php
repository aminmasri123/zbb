<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_job_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('personens', indexName: 'job_bookmark_person_fk')->cascadeOnDelete();
            $table->string('external_ref', 150);
            $table->string('title');
            $table->string('employer')->nullable();
            $table->string('location')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->date('published_at')->nullable();
            $table->string('source', 40)->default('ba_jobsuche');
            $table->timestamps();
            $table->unique(['person_id', 'external_ref'], 'job_bookmark_person_ref_unique');
        });

        Schema::create('participant_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_person_id')->constrained('projekt_has_personens', indexName: 'application_participation_fk')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users', indexName: 'application_creator_fk')->cascadeOnDelete();
            $table->string('external_ref', 150)->nullable();
            $table->string('title');
            $table->string('employer')->nullable();
            $table->string('location')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->enum('status', ['draft', 'preparing', 'sent', 'response', 'interview', 'accepted', 'rejected', 'withdrawn'])->default('draft');
            $table->date('applied_at')->nullable();
            $table->date('next_action_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['project_person_id', 'status', 'next_action_at'], 'application_status_action_lookup');
        });

        Schema::create('participant_application_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('participant_applications', indexName: 'application_history_application_fk')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by_user_id')->constrained('users', indexName: 'application_history_user_fk')->cascadeOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_application_status_histories');
        Schema::dropIfExists('participant_applications');
        Schema::dropIfExists('participant_job_bookmarks');
    }
};
