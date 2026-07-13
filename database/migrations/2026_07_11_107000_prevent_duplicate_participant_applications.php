<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_applications', function (Blueprint $table) {
            $table->unique(['project_person_id', 'external_ref'], 'application_participation_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('participant_applications', fn (Blueprint $table) => $table->dropUnique('application_participation_external_unique'));
    }
};
