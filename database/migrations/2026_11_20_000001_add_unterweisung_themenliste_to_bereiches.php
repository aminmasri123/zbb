<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bereiches', function (Blueprint $table) {
            $table->json('unterweisung_themenliste')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bereiches', function (Blueprint $table) {
            $table->dropColumn('unterweisung_themenliste');
        });
    }
};
