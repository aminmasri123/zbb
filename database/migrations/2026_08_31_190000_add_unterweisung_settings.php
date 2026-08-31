<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bereiches', function (Blueprint $table) {
            $table->json('unterweisung_themen')->nullable()->after('beschreibung');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->longText('unterweisung_unterschrift')->nullable()->after('profile_photo_path');
            $table->timestamp('unterweisung_unterschrift_updated_at')->nullable()->after('unterweisung_unterschrift');
        });
    }

    public function down(): void
    {
        Schema::table('bereiches', fn (Blueprint $table) => $table->dropColumn('unterweisung_themen'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'unterweisung_unterschrift',
            'unterweisung_unterschrift_updated_at',
        ]));
    }
};
