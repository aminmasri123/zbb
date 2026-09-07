<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_attendance_signature_removals', function (Blueprint $table) {
            $table->id();
            // No cascading deletes: recovery copies must survive changes to their source records.
            $table->unsignedBigInteger('gruppe_id');
            $table->unsignedBigInteger('projekt_id');
            $table->string('list_type', 8);
            $table->unsignedBigInteger('draft_id');
            $table->unsignedBigInteger('person_id');
            $table->string('signature_key');
            $table->date('signed_for_date');
            $table->longText('signature_ciphertext');
            $table->unsignedBigInteger('removed_by');
            $table->string('removed_by_name');
            $table->timestamp('removed_at');
            $table->unsignedBigInteger('restored_by')->nullable();
            $table->string('restored_by_name')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->index(['projekt_id', 'list_type', 'draft_id'], 'group_signature_removals_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_attendance_signature_removals');
    }
};
