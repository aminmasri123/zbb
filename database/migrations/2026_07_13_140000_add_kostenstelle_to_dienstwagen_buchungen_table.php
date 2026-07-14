<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dienstwagen_buchungen', function (Blueprint $table) {
            $table->foreignId('kostenstelle_id')
                ->nullable()
                ->after('person_id')
                ->constrained('kostenstelles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dienstwagen_buchungen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kostenstelle_id');
        });
    }
};
