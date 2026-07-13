<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('modules')->updateOrInsert(
            ['key' => 'bvb_reha'],
            [
                'name' => 'BvB Reha',
                'description' => 'Berufsvorbereitende Bildungsmaßnahme Rehabilitation',
                'category' => 'education',
                'is_system_module' => false,
                'is_enforced' => false,
                'default_enabled' => true,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $moduleIds = DB::table('modules')->whereIn('key', ['bop', 'bvb_reha'])->pluck('id', 'key');

        DB::table('project_types')->insert([
            ['key' => 'bop', 'name' => 'BOP', 'description' => 'Berufsorientierungsprogramm', 'module_id' => $moduleIds['bop'] ?? null, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'bvb_reha', 'name' => 'BvB Reha', 'description' => 'Berufsvorbereitende Bildungsmaßnahme Rehabilitation', 'module_id' => $moduleIds['bvb_reha'] ?? null, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'bvb', 'name' => 'BvB', 'description' => 'Berufsvorbereitende Bildungsmaßnahme', 'module_id' => null, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'bae', 'name' => 'BaE', 'description' => 'Berufsausbildung in außerbetrieblichen Einrichtungen', 'module_id' => null, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'asa_flex', 'name' => 'AsA flex', 'description' => 'Assistierte Ausbildung flexibel', 'module_id' => null, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'coaching', 'name' => 'Coaching', 'description' => 'Coachingmaßnahmen', 'module_id' => null, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('projekts', function (Blueprint $table) {
            $table->foreignId('project_type_id')
                ->nullable()
                ->after('id')
                ->constrained('project_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projekts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_type_id');
        });

        Schema::dropIfExists('project_types');

        // Der additive Modul-Katalogeintrag bleibt absichtlich erhalten, damit ein
        // Rollback keine spaeter angelegten Modulzuweisungen loescht.
    }
};
