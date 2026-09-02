<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_report_runs', function (Blueprint $table): void {
            $table->string('luv_type', 20)->nullable()->after('report_type');
            $table->foreignId('template_id')->nullable()->after('luv_type')
                ->constrained('projekt_luv_templates')->nullOnDelete();
        });

        Schema::table('personen_has_notizens', function (Blueprint $table): void {
            $table->foreignId('projekt_person_id')->nullable()->after('person_id')
                ->constrained('projekt_has_personens')->nullOnDelete();
            $table->index(['projekt_person_id', 'created_at'], 'participant_note_project_date_index');
        });

        DB::table('ai_report_runs')->where('report_type', 'luv')->update(['luv_type' => 'Start']);
        DB::table('ai_report_runs')->where('report_type', 'interim')->update(['luv_type' => 'Verlauf']);
        DB::table('ai_report_runs')->where('report_type', 'final')->update(['luv_type' => 'Abschluss']);

        foreach (DB::table('personen_has_notizens')->whereNull('projekt_person_id')->select(['id', 'person_id'])->orderBy('id')->get() as $note) {
            $participations = DB::table('projekt_has_personens')->where('personen_id', $note->person_id)->pluck('id');
            if ($participations->count() === 1) {
                DB::table('personen_has_notizens')->where('id', $note->id)->update(['projekt_person_id' => $participations->first()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('personen_has_notizens', function (Blueprint $table): void {
            $table->dropIndex('participant_note_project_date_index');
            $table->dropConstrainedForeignId('projekt_person_id');
        });

        Schema::table('ai_report_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn('luv_type');
        });
    }
};
