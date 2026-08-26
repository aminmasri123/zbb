<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_portal_staff_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_person_id')
                ->constrained('projekt_has_personens', indexName: 'portal_staff_read_participation_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users', indexName: 'portal_staff_read_user_fk')
                ->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['project_person_id', 'user_id'], 'portal_staff_read_unique');
            $table->index(['user_id', 'last_read_at'], 'portal_staff_read_unread');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_portal_staff_reads');
    }
};
