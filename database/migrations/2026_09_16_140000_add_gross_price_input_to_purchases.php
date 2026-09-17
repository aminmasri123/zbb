<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['materialanforderungs', 'purchase_offers'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                // Existing records and older clients use net prices.
                $table->string('preisart', 10)->default('netto');
                $table->decimal('versand_brutto', 12, 2)->nullable();
            });
        }
        Schema::table('materialanforderung_artikels', function (Blueprint $table) {
            $table->decimal('einzelpreis_brutto', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        foreach (['materialanforderungs', 'purchase_offers'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['preisart', 'versand_brutto']));
        }
        Schema::table('materialanforderung_artikels', fn (Blueprint $table) => $table->dropColumn('einzelpreis_brutto'));
    }
};
