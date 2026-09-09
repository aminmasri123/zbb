<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('aptitude_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekts')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('version');
            $table->json('definition');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['projekt_id', 'version']);
        });
        Schema::table('gruppes', function (Blueprint $table) {
            $table->foreignId('aptitude_profile_id')->nullable()->constrained('aptitude_profiles')->restrictOnDelete();
        });
        Schema::create('aptitude_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gruppe_id')->constrained('gruppes')->cascadeOnDelete();
            $table->foreignId('project_person_id')->constrained('projekt_has_personens')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('aptitude_profiles')->restrictOnDelete();
            $table->date('tested_on');
            $table->string('status')->default('started');
            $table->json('scores');
            $table->json('results');
            $table->json('reports');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('aptitude_attempt_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('aptitude_attempts')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->json('snapshot');
            $table->timestamp('created_at');
            $table->unique(['attempt_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aptitude_attempt_revisions');
        Schema::dropIfExists('aptitude_attempts');
        Schema::table('gruppes', fn (Blueprint $table) => $table->dropConstrainedForeignId('aptitude_profile_id'));
        Schema::dropIfExists('aptitude_profiles');
    }
};
