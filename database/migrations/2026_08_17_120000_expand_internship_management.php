<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personen_has_bildungsmassnahmens', function (Blueprint $table) {
            $table->string('placement_type', 20)->nullable()->default('external')->after('typ');
            $table->foreignId('host_project_id')->nullable()->after('traeger')->constrained('projekts')->nullOnDelete();
            $table->foreignId('supervisor_person_id')->nullable()->after('host_project_id')->constrained('personens')->nullOnDelete();
            $table->string('host_address')->nullable()->after('supervisor_person_id');
            $table->string('department')->nullable()->after('host_address');
            $table->string('internship_kind', 30)->nullable()->after('department');
            $table->string('occupation')->nullable()->after('internship_kind');
            $table->string('attendance_weekday', 30)->nullable()->after('occupation');
            $table->text('activities')->nullable()->after('objective');
            $table->text('assessment')->nullable()->after('activities');

            $table->index(
                ['projekt_person_id', 'typ', 'placement_type', 'status'],
                'education_measure_internship_overview_idx'
            );
            $table->index(['host_project_id', 'supervisor_person_id'], 'internship_host_supervisor_idx');
        });
    }

    public function down(): void
    {
        Schema::table('personen_has_bildungsmassnahmens', function (Blueprint $table) {
            $table->dropIndex('education_measure_internship_overview_idx');
            $table->dropIndex('internship_host_supervisor_idx');
            $table->dropConstrainedForeignId('supervisor_person_id');
            $table->dropConstrainedForeignId('host_project_id');
            $table->dropColumn([
                'placement_type',
                'host_address',
                'department',
                'internship_kind',
                'occupation',
                'attendance_weekday',
                'activities',
                'assessment',
            ]);
        });
    }
};
