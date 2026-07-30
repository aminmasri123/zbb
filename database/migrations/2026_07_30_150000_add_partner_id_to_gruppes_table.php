<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gruppes', function (Blueprint $table) {
            $table->foreignId('partner_id')
                ->nullable()
                ->after('projekt_id')
                ->constrained('partners')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gruppes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_id');
        });
    }
};
