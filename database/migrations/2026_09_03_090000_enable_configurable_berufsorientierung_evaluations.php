<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projekts', function (Blueprint $table) {
            $table->json('berufsorientierung_auswertung_config')->nullable()->after('potenzialanalyse_auswertung_config');
        });

        Schema::table('berufsorientierung_bewertungen', function (Blueprint $table) {
            $table->string('kriterium_label')->nullable()->after('kriterium');
            $table->text('bemerkung')->nullable()->after('bewertung');
            $table->unsignedBigInteger('legacy_bewertungsbogen_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('berufsorientierung_bewertungen', function (Blueprint $table) {
            $table->dropColumn(['kriterium_label', 'bemerkung']);
        });
        Schema::table('projekts', function (Blueprint $table) {
            $table->dropColumn('berufsorientierung_auswertung_config');
        });
    }
};
