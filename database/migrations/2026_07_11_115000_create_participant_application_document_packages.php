<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('participant_applications', 'participant_package_approved_at')) {
            Schema::table('participant_applications', fn (Blueprint $table) => $table->timestamp('participant_package_approved_at')->nullable()->after('notes'));
        }
        if (!Schema::hasColumn('participant_applications', 'staff_package_approved_at')) {
            Schema::table('participant_applications', fn (Blueprint $table) => $table->timestamp('staff_package_approved_at')->nullable()->after('participant_package_approved_at'));
        }
        if (!Schema::hasColumn('participant_applications', 'staff_package_approved_by_user_id')) {
            Schema::table('participant_applications', fn (Blueprint $table) => $table->unsignedBigInteger('staff_package_approved_by_user_id')->nullable()->after('staff_package_approved_at'));
        }

        Schema::table('participant_applications', function (Blueprint $table) {
            $table->foreign('staff_package_approved_by_user_id', 'app_pkg_staff_approver_fk')->references('id')->on('users')->nullOnDelete();
        });

        if (!Schema::hasTable('participant_application_documents')) {
            Schema::create('participant_application_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('application_id')->constrained('participant_applications')->cascadeOnDelete();
                $table->foreignId('document_id')->constrained('participant_portal_documents')->restrictOnDelete();
                $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['application_id', 'document_id'], 'application_document_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_application_documents');
        Schema::table('participant_applications', function (Blueprint $table) {
            $table->dropForeign('app_pkg_staff_approver_fk');
            $table->dropColumn(['participant_package_approved_at', 'staff_package_approved_at', 'staff_package_approved_by_user_id']);
        });
    }
};
