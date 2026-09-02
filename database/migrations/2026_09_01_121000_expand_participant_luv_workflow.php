<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projekt_has_teilnehmer_luvs', function (Blueprint $table): void {
            $table->foreignId('template_id')->nullable()->after('projekt_person_id')
                ->constrained('projekt_luv_templates')->nullOnDelete();
            $table->foreignId('ai_report_run_id')->nullable()->after('template_id')
                ->constrained('ai_report_runs')->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('typ');
            $table->string('status', 20)->default('draft')->after('version');
            $table->string('form_version', 60)->nullable()->after('status');
            $table->json('payload')->nullable()->after('qualifikationen');
            $table->json('source_snapshot')->nullable()->after('payload');
            $table->foreignId('created_by')->nullable()->after('source_snapshot')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('reviewed_by')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->after('approved_by');
            $table->dateTime('approved_at')->nullable()->after('reviewed_at');
            $table->date('discussed_on')->nullable()->after('approved_at');
            $table->boolean('consent_confirmed')->default(false)->after('discussed_on');

            $table->index(['projekt_person_id', 'typ', 'status'], 'participant_luv_type_status_index');
        });

        DB::table('projekt_has_teilnehmer_luvs')->update([
            'status' => 'approved',
            'approved_at' => DB::raw('updated_at'),
        ]);

        $existing = DB::table('projekt_has_teilnehmer_luvs')
            ->select(['id', 'projekt_person_id', 'typ', 'ausgangssituation', 'zielvereinbarung', 'qualifikationen'])
            ->orderBy('id')
            ->get();

        $versions = [];
        foreach ($existing as $row) {
            $versionKey = $row->projekt_person_id.'|'.$row->typ;
            $versions[$versionKey] = ($versions[$versionKey] ?? 0) + 1;
            DB::table('projekt_has_teilnehmer_luvs')->where('id', $row->id)->update([
                'version' => $versions[$versionKey],
                'payload' => json_encode([
                    'sections' => [
                        ['key' => 'ausgangssituation', 'heading' => 'Ausgangssituation', 'value' => $row->ausgangssituation],
                        ['key' => 'zielvereinbarung', 'heading' => 'Zielvereinbarung', 'value' => $row->zielvereinbarung],
                        ['key' => 'qualifikationen', 'heading' => 'Qualifikationen', 'value' => $row->qualifikationen],
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        Schema::table('projekt_has_teilnehmer_luvs', function (Blueprint $table): void {
            $table->unique(['projekt_person_id', 'typ', 'version'], 'participant_luv_type_version_unique');
            $table->unique('ai_report_run_id', 'participant_luv_ai_run_unique');
        });
    }

    public function down(): void
    {
        Schema::table('projekt_has_teilnehmer_luvs', function (Blueprint $table): void {
            $table->dropIndex('participant_luv_type_status_index');
            $table->dropUnique('participant_luv_type_version_unique');
            $table->dropUnique('participant_luv_ai_run_unique');
            $table->dropConstrainedForeignId('template_id');
            $table->dropConstrainedForeignId('ai_report_run_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'version',
                'status',
                'form_version',
                'payload',
                'source_snapshot',
                'reviewed_at',
                'approved_at',
                'discussed_on',
                'consent_confirmed',
            ]);
        });
    }
};
