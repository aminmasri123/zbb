<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPE = 'Flur / Verkehrsfläche';

    public function up(): void
    {
        if (! Schema::hasTable('raumtypen')
            || DB::table('raumtypen')->where('name', self::TYPE)->exists()) {
            return;
        }

        DB::table('raumtypen')->insert([
            'name' => self::TYPE,
            'beschreibung' => 'Nicht buchbare Verkehrs- und Erschließungsfläche, die Räume und Zutrittstüren miteinander verbindet.',
            'aktiv' => true,
            'sort_order' => 230,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('raumtypen')) {
            return;
        }

        $isUsed = Schema::hasTable('raeumes')
            && DB::table('raeumes')->where('typ', self::TYPE)->exists();

        if (! $isUsed) {
            DB::table('raumtypen')->where('name', self::TYPE)->delete();
        }
    }
};
