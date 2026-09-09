<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_daily_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gruppe_id')->constrained('gruppes')->cascadeOnDelete();
            $t->date('performed_on');
            $t->string('description', 500);
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['gruppe_id', 'performed_on']);
        });
        Schema::create('group_daily_task_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained('group_daily_tasks')->cascadeOnDelete();
            $t->foreignId('project_person_id')->constrained('projekt_has_personens')->cascadeOnDelete();
            $t->string('observation', 1000)->nullable();
            $t->unique(['task_id', 'project_person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_daily_task_participants');
        Schema::dropIfExists('group_daily_tasks');
    }
};
