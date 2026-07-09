<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bibb_attendance_list_drafts', function (Blueprint $table) {
            $table->string('final_pdf_path')->nullable()->after('payload');
            $table->timestamp('finalized_at')->nullable()->after('final_pdf_path');
            $table->timestamp('expires_at')->nullable()->after('finalized_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('bibb_attendance_list_drafts', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['final_pdf_path', 'finalized_at', 'expires_at']);
        });
    }
};
