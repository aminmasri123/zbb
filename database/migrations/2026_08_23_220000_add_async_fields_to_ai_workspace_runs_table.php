<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_workspace_runs', function (Blueprint $table): void {
            $table->string('status', 20)->default('queued')->change();
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');
            $table->longText('request_payload')->nullable()->after('source_metadata');
            $table->string('error_code')->nullable()->after('warnings');
            $table->text('error_message')->nullable()->after('error_code');
            $table->unsignedInteger('duration_seconds')->nullable()->after('error_message');
            $table->dateTime('started_at')->nullable()->after('duration_seconds');
            $table->dateTime('completed_at')->nullable()->after('started_at');
            $table->index(['user_id', 'status'], 'ai_workspace_runs_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_workspace_runs', function (Blueprint $table): void {
            $table->dropIndex('ai_workspace_runs_user_status_idx');
            $table->dropColumn([
                'progress_percent', 'request_payload', 'error_code', 'error_message',
                'duration_seconds', 'started_at', 'completed_at',
            ]);
            $table->string('status', 20)->default('completed')->change();
        });
    }
};
