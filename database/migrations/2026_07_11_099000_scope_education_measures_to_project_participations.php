<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personen_has_bildungsmassnahmens', function (Blueprint $table) {
            $table->foreignId('projekt_person_id')
                ->nullable()
                ->after('person_id')
                ->constrained('projekt_has_personens')
                ->nullOnDelete();
            $table->index(['projekt_person_id', 'status'], 'bildungsmassnahme_projekt_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('personen_has_bildungsmassnahmens', function (Blueprint $table) {
            $table->dropIndex('bildungsmassnahme_projekt_status_index');
            $table->dropConstrainedForeignId('projekt_person_id');
        });
    }
};
