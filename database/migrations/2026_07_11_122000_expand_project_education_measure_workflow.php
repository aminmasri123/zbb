<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('personen_has_bildungsmassnahmens', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('traeger');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone', 50)->nullable()->after('contact_email');
            $table->unsignedSmallInteger('weekly_hours')->nullable()->after('end');
            $table->date('next_follow_up_at')->nullable()->after('weekly_hours');
            $table->text('objective')->nullable()->after('bemerkung');
            $table->text('result')->nullable()->after('objective');
            $table->dateTime('archived_at')->nullable()->after('status');
            $table->index(['projekt_person_id', 'archived_at', 'next_follow_up_at'], 'education_measure_follow_up_idx');
        });

        Schema::create('education_measure_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('education_measure_id')->constrained('personen_has_bildungsmassnahmens')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('note')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['education_measure_id', 'created_at'], 'education_status_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_measure_status_history');
        Schema::table('personen_has_bildungsmassnahmens', function (Blueprint $table) {
            $table->dropIndex('education_measure_follow_up_idx');
            $table->dropColumn(['contact_name','contact_email','contact_phone','weekly_hours','next_follow_up_at','objective','result','archived_at']);
        });
    }
};
