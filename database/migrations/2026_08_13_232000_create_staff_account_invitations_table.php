<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_account_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users', indexName: 'staff_invitation_user_fk')
                ->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('invited_by_user_id')
                ->nullable()
                ->constrained('users', indexName: 'staff_invitation_inviter_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['user_id', 'accepted_at', 'expires_at'],
                'staff_invitation_status_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_account_invitations');
    }
};
