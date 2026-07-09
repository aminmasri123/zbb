<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bibb_attendance_list_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('draft_hash', 64)->unique();
            $table->foreignId('projekt_id')->nullable()->constrained('projekts')->nullOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('schuljahr', 30);
            $table->string('teil', 30);
            $table->longText('payload');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('user_create')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_update')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['partner_id', 'schuljahr', 'teil']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bibb_attendance_list_drafts');
    }
};
