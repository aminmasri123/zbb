<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projekt_has_personens', function (Blueprint $table) {
            $table->enum('status', [
                'angefragt',
                'angemeldet',
                'aufgenommen',
                'aktiv',
                'pausiert',
                'abgeschlossen',
                'abgebrochen',
            ])->default('aktiv')->change();
        });
    }

    public function down(): void
    {
        DB::table('projekt_has_personens')->where('status', 'angefragt')->update(['status' => 'angemeldet']);
        DB::table('projekt_has_personens')->where('status', 'aufgenommen')->update(['status' => 'aktiv']);

        Schema::table('projekt_has_personens', function (Blueprint $table) {
            $table->enum('status', [
                'angemeldet',
                'aktiv',
                'pausiert',
                'abgeschlossen',
                'abgebrochen',
            ])->default('aktiv')->change();
        });
    }
};
