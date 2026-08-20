<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pa_attendance_signature_versions', function (Blueprint $table) {
            $table->id();
            $table->char('subject_hash', 64);
            $table->unsignedInteger('version');
            $table->foreignId('draft_id')->nullable()->constrained('pa_attendance_list_drafts')->nullOnDelete();
            $table->foreignId('projekt_id')->nullable()->constrained('projekts')->nullOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('personens')->cascadeOnDelete();
            $table->string('schuljahr', 30);
            $table->string('teil', 30);
            $table->string('list_type', 20)->default('pa');
            $table->string('signature_key', 255);
            $table->string('day_key', 180);
            $table->date('signed_for_date')->nullable();
            $table->string('day_type', 30)->nullable();
            $table->string('day_label')->nullable();
            $table->string('class_name', 100)->nullable();
            $table->enum('action', ['captured', 'replaced', 'restored', 'deleted', 'imported']);
            $table->longText('signature_ciphertext')->nullable();
            $table->char('signature_sha256', 64)->nullable();
            $table->foreignId('restored_from_version_id')->nullable();
            $table->unsignedInteger('source_draft_revision')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name_snapshot')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at');

            $table->unique(['subject_hash', 'version'], 'pa_signature_subject_version_unique');
            $table->index(['partner_id', 'schuljahr', 'teil', 'list_type'], 'pa_signature_scope_idx');
            $table->index(['person_id', 'signed_for_date'], 'pa_signature_person_day_idx');
            $table->foreign('restored_from_version_id', 'pa_signature_restore_fk')
                ->references('id')
                ->on('pa_attendance_signature_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pa_attendance_signature_versions');
    }
};
