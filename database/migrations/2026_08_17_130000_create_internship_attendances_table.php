<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internship_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('education_measure_id');
            $table->date('attendance_date');
            $table->string('status', 40);
            $table->unsignedSmallInteger('planned_minutes')->nullable();
            $table->unsignedSmallInteger('actual_minutes')->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('recorded_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('education_measure_id', 'intern_attendance_measure_fk')
                ->references('id')
                ->on('personen_has_bildungsmassnahmens')
                ->cascadeOnDelete();
            $table->foreign('recorded_by_user_id', 'intern_attendance_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->unique(['education_measure_id', 'attendance_date'], 'intern_attendance_measure_date_uq');
            $table->index(['attendance_date', 'status'], 'intern_attendance_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internship_attendances');
    }
};
