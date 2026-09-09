<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('personens', function (Blueprint $table) { $table->string('namenszusatz')->nullable(); });
        Schema::table('projekt_has_personens', function (Blueprint $table) { $table->json('import_entry_data')->nullable(); });
    }

    public function down(): void
    {
        Schema::table('personens', function (Blueprint $table) { $table->dropColumn('namenszusatz'); });
        Schema::table('projekt_has_personens', function (Blueprint $table) { $table->dropColumn('import_entry_data'); });
    }
};
