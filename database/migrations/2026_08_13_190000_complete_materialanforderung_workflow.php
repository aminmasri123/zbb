<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materialanforderungs', function (Blueprint $table) {
            $table->date('benoetigt_am')->nullable()->after('kostenstelle');
            $table->string('prioritaet', 20)->default('normal')->after('benoetigt_am');
        });

        Schema::table('materialanforderung_artikels', function (Blueprint $table) {
            $table->unsignedInteger('gelieferte_menge')->default(0)->after('stueck');
        });

        Schema::table('materialanforderung_vergabevermerks', function (Blueprint $table) {
            $table->text('kurzbeschreibung')->nullable()->after('anforderung_id');
            $table->json('begruendung_optionen')->nullable()->after('begruendung');
            $table->text('lieferadresse')->nullable()->after('lieferung_option');
            $table->string('bestellnummer')->nullable()->after('lieferadresse');
        });

        Schema::table('materialanforderung_genehmigungs', function (Blueprint $table) {
            $table->string('status', 40)->default('offen')->change();
        });
    }

    public function down(): void
    {
        Schema::table('materialanforderung_vergabevermerks', function (Blueprint $table) {
            $table->dropColumn([
                'kurzbeschreibung',
                'begruendung_optionen',
                'lieferadresse',
                'bestellnummer',
            ]);
        });

        Schema::table('materialanforderungs', function (Blueprint $table) {
            $table->dropColumn(['benoetigt_am', 'prioritaet']);
        });

        Schema::table('materialanforderung_artikels', function (Blueprint $table) {
            $table->dropColumn('gelieferte_menge');
        });
    }
};
