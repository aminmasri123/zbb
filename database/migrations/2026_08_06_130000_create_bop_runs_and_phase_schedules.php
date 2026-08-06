<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bop_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projekt_id')->constrained('projekts')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('schuljahr', 20);
            $table->string('teil', 40);
            $table->string('school_type', 40)->nullable();
            $table->date('first_visit_date')->nullable();
            $table->date('last_visit_date')->nullable();
            $table->string('status', 20)->default('planning');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['projekt_id', 'partner_id', 'schuljahr', 'teil'], 'bop_runs_context_unique');
        });

        Schema::create('bop_phase_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bop_run_id')->constrained('bop_runs')->cascadeOnDelete();
            $table->string('phase_type', 40);
            $table->json('dates')->nullable();
            $table->string('scope_type', 20)->default('school');
            $table->json('selected_classes')->nullable();
            $table->string('group_mode', 30)->default('none');
            $table->unsignedSmallInteger('group_count')->nullable();
            $table->foreignId('supervisor_person_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->foreignId('bereich_id')->nullable()->constrained('bereiches')->nullOnDelete();
            $table->foreignId('raum_id')->nullable()->constrained('raeumes')->nullOnDelete();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('generate_groups')->default(false);
            $table->boolean('publish_to_calendar')->default(false);
            $table->foreignId('calendar_event_id')->nullable()->constrained('app_calendar_events')->nullOnDelete();
            $table->foreignId('einteilung_setting_id')->nullable()->constrained('einteilung_settings')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['bop_run_id', 'phase_type']);
        });

        Schema::create('bop_phase_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bop_phase_schedule_id')->constrained('bop_phase_schedules')->cascadeOnDelete();
            $table->foreignId('personen_ist_schueler_id')->constrained('personen_ist_schuelers')->cascadeOnDelete();
            $table->string('class_name')->nullable();
            $table->string('group_key')->nullable();
            $table->string('completion_status', 20)->default('planned');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['bop_phase_schedule_id', 'personen_ist_schueler_id'], 'bop_phase_participant_unique');
            $table->index(['bop_phase_schedule_id', 'group_key'], 'bop_phase_group_index');
        });

        Schema::table('gruppes', function (Blueprint $table) {
            $table->foreignId('bop_phase_schedule_id')->nullable()->after('projekt_id')->constrained('bop_phase_schedules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gruppes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bop_phase_schedule_id');
        });
        Schema::dropIfExists('bop_phase_participants');
        Schema::dropIfExists('bop_phase_schedules');
        Schema::dropIfExists('bop_runs');
    }
};
