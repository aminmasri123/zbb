<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_portal_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_person_id')->constrained('projekt_has_personens', indexName: 'portal_invitation_participation_fk')->cascadeOnDelete();
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('invited_by_user_id')->constrained('users', indexName: 'portal_invitation_user_fk')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['project_person_id', 'accepted_at', 'expires_at'], 'portal_invitation_status_lookup');
        });

        Schema::create('participant_portal_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->unique()->constrained('personens', indexName: 'portal_profile_person_fk')->cascadeOnDelete();
            $table->string('professional_headline', 160)->nullable();
            $table->text('career_goal')->nullable();
            $table->text('skills')->nullable();
            $table->text('interests')->nullable();
            $table->date('available_from')->nullable();
            $table->unsignedSmallInteger('job_search_radius_km')->nullable();
            $table->boolean('profile_visible_to_project_staff')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_portal_profiles');
        Schema::dropIfExists('participant_portal_invitations');
    }
};
