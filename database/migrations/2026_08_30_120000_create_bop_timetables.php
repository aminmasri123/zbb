<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bop_timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bop_phase_schedule_id')->constrained('bop_phase_schedules')->cascadeOnDelete();
            $table->date('schedule_date');
            $table->unsignedTinyInteger('slot_minutes')->default(15);
            $table->json('config');
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bop_phase_schedule_id', 'schedule_date'], 'bop_timetable_phase_date_unique');
        });

        Schema::create('bop_timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bop_timetable_id')->constrained('bop_timetables')->cascadeOnDelete();
            $table->string('group_key', 80)->nullable();
            $table->string('type', 20);
            $table->string('title', 150);
            $table->foreignId('bereich_id')->nullable()->constrained('bereiches')->nullOnDelete();
            $table->foreignId('supervisor_person_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['bop_timetable_id', 'start_time'], 'bop_timetable_time_index');
            $table->index(['bop_timetable_id', 'bereich_id', 'start_time'], 'bop_timetable_area_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bop_timetable_entries');
        Schema::dropIfExists('bop_timetables');
    }
};
