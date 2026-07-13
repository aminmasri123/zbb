<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('category', 40);
            $table->boolean('is_system_module')->default(false);
            $table->boolean('is_enforced')->default(false);
            $table->boolean('default_enabled')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('module_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('scope_key', 100);
            $table->foreignId('location_id')->nullable()->constrained('standorts')->cascadeOnDelete();
            $table->boolean('enabled');
            $table->json('settings')->nullable();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['module_id', 'scope_key'], 'module_assignment_scope_unique');
            $table->index(['location_id', 'enabled']);
        });

        $now = now();
        DB::table('modules')->insert([
            ['key' => 'bop', 'name' => 'BOP', 'description' => 'Berufsorientierungsprogramm', 'category' => 'education', 'is_system_module' => false, 'is_enforced' => false, 'default_enabled' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'room_management', 'name' => 'Raumverwaltung', 'description' => 'Raeume, Meldungen und Buchungen', 'category' => 'resources', 'is_system_module' => false, 'is_enforced' => true, 'default_enabled' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'it_management', 'name' => 'IT-Verwaltung', 'description' => 'Geraete, Ausgaben, Rueckgaben und IT-Service', 'category' => 'resources', 'is_system_module' => false, 'is_enforced' => false, 'default_enabled' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'warehouse_management', 'name' => 'Lagerverwaltung', 'description' => 'Artikel, Reservierungen und Bestandsbewegungen', 'category' => 'resources', 'is_system_module' => false, 'is_enforced' => false, 'default_enabled' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'vehicle_management', 'name' => 'Dienstwagenverwaltung', 'description' => 'Fahrzeuge, Buchungen und Fahrtenbuch', 'category' => 'resources', 'is_system_module' => false, 'is_enforced' => false, 'default_enabled' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'document_management', 'name' => 'Dokumentenverwaltung', 'description' => 'Dokument- und Exportvorlagen', 'category' => 'platform', 'is_system_module' => false, 'is_enforced' => false, 'default_enabled' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'attendance', 'name' => 'Anwesenheit', 'description' => 'Anwesenheits- und Klassenbuchfunktionen', 'category' => 'platform', 'is_system_module' => false, 'is_enforced' => false, 'default_enabled' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'reporting', 'name' => 'Berichte und Exporte', 'description' => 'Berichte, PDF-, Word- und Excel-Exporte', 'category' => 'platform', 'is_system_module' => false, 'is_enforced' => false, 'default_enabled' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('module_assignments');
        Schema::dropIfExists('modules');
    }
};
