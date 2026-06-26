<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('app_files')->nullOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projekts')->nullOnDelete();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->enum('type', ['file', 'folder'])->default('file');
            $table->string('name');
            $table->string('original_name')->nullable();
            $table->string('path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->enum('visibility', ['private', 'all', 'team', 'project'])->default('private');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('app_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projekts')->nullOnDelete();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->string('color', 20)->nullable();
            $table->enum('visibility', ['private', 'all', 'team', 'project'])->default('private');
            $table->timestamps();
        });

        Schema::create('app_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projekts')->nullOnDelete();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('role')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->enum('visibility', ['private', 'all', 'team', 'project'])->default('private');
            $table->timestamps();
        });

        Schema::create('app_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignee_person_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projekts')->nullOnDelete();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'progress', 'done'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->date('due_at')->nullable();
            $table->enum('visibility', ['private', 'all', 'team', 'project'])->default('private');
            $table->timestamps();
        });

        Schema::create('app_popups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projekts')->nullOnDelete();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('title');
            $table->text('message');
            $table->enum('level', ['info', 'success', 'warning', 'danger'])->default('info');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->enum('visibility', ['private', 'all', 'team', 'project'])->default('private');
            $table->timestamps();
        });

        Schema::create('app_shares', function (Blueprint $table) {
            $table->id();
            $table->morphs('shareable');
            $table->foreignId('shared_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('personens')->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->enum('permission', ['view', 'edit'])->default('view');
            $table->text('message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['person_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_shares');
        Schema::dropIfExists('app_popups');
        Schema::dropIfExists('app_tasks');
        Schema::dropIfExists('app_contacts');
        Schema::dropIfExists('app_calendar_events');
        Schema::dropIfExists('app_files');
    }
};
