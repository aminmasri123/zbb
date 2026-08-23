<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_workspace_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('run_uuid')->unique();
            $table->enum('task', ['chat', 'summarize', 'compare', 'image_analysis']);
            $table->text('instruction');
            $table->json('source_metadata')->nullable();
            $table->string('title')->nullable();
            $table->longText('content')->nullable();
            $table->json('citations')->nullable();
            $table->json('warnings')->nullable();
            $table->enum('status', ['completed', 'failed'])->default('completed');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('ai_workspace_runs'); }
};
