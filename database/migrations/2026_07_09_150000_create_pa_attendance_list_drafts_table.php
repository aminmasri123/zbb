<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pa_attendance_list_drafts')) {
            Schema::table('pa_attendance_list_drafts', function (Blueprint $table) {
                $table->index(['partner_id', 'schuljahr', 'teil', 'export_mode', 'klasse'], 'pa_drafts_scope_idx');
            });

            return;
        }

        Schema::create('pa_attendance_list_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('draft_hash', 64)->unique();
            $table->foreignId('projekt_id')->nullable()->constrained('projekts')->nullOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('schuljahr', 30);
            $table->string('teil', 30);
            $table->string('export_mode', 20)->default('alle');
            $table->string('klasse', 80)->nullable();
            $table->longText('payload');
            $table->string('final_pdf_path')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('user_create')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_update')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['partner_id', 'schuljahr', 'teil', 'export_mode', 'klasse'], 'pa_drafts_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pa_attendance_list_drafts');
    }
};
