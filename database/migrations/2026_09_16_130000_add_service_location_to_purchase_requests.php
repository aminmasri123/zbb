<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materialanforderung_vergabevermerks', function (Blueprint $table) {
            $table->text('leistungsort')->nullable();
            $table->enum('lieferung_option', ['per Abholung', 'per Lieferung'])->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('materialanforderung_vergabevermerks')->whereNull('lieferung_option')->update(['lieferung_option' => 'per Lieferung']);
        Schema::table('materialanforderung_vergabevermerks', function (Blueprint $table) {
            $table->dropColumn('leistungsort');
            $table->enum('lieferung_option', ['per Abholung', 'per Lieferung'])->nullable(false)->default('per Lieferung')->change();
        });
    }
};
