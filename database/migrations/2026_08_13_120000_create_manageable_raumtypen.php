<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raumtypen', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('beschreibung', 500)->nullable();
            $table->boolean('aktiv')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $standardtypen = [
            'Büro', 'Elektroraum', 'Unterrichtsraum', 'Seminarraum',
            'Besprechungsraum', 'Labor', 'Werkstatt', 'Lager', 'Küche',
            'Aufenthaltsraum', 'Sanitärraum', 'Empfang', 'Serverraum',
            'Archiv', 'Aula', 'Bibliothek', 'Arbeitsplatz', 'Copyroom',
            'Technikraum', 'Hauswirtschaftsraum', 'Holzbereich', 'Metallbereich',
        ];
        $vorhandeneTypen = Schema::hasTable('raeumes')
            ? DB::table('raeumes')->whereNotNull('typ')->distinct()->pluck('typ')->all()
            : [];
        $typen = array_values(array_unique(array_filter([...$standardtypen, ...$vorhandeneTypen])));
        $jetzt = now();

        DB::table('raumtypen')->insert(array_map(
            fn (string $name, int $index) => [
                'name' => $name,
                'aktiv' => true,
                'sort_order' => ($index + 1) * 10,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ],
            $typen,
            array_keys($typen)
        ));

        if (Schema::hasTable('raeumes')) {
            Schema::table('raeumes', function (Blueprint $table) {
                $table->string('typ', 100)->change();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('raumtypen');
    }
};
