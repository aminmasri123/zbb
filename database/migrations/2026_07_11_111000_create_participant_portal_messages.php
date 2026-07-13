<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_portal_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_person_id')->constrained('projekt_has_personens')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('sender_kind', ['participant', 'staff']);
            $table->text('body');
            $table->timestamp('participant_read_at')->nullable();
            $table->timestamp('staff_read_at')->nullable();
            $table->timestamps();

            $table->index(['project_person_id', 'created_at'], 'portal_messages_thread_idx');
            $table->index(['project_person_id', 'sender_kind', 'participant_read_at'], 'portal_messages_participant_unread_idx');
            $table->index(['project_person_id', 'sender_kind', 'staff_read_at'], 'portal_messages_staff_unread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_portal_messages');
    }
};
