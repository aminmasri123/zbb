<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_data_access_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->unique()->constrained('roles')->cascadeOnDelete();
            $table->string('team_scope')->default('none');
            $table->string('participant_scope')->default('none');
            $table->timestamps();
        });

        $defaults = [
            'Administrator' => ['team_scope' => 'all', 'participant_scope' => 'all'],
            'Developer' => ['team_scope' => 'all', 'participant_scope' => 'all'],
            'Sekretariat' => ['team_scope' => 'all', 'participant_scope' => 'all'],
            'Geschäftsführer' => ['team_scope' => 'all', 'participant_scope' => 'all'],
            'Geschäftsleitung' => ['team_scope' => 'all', 'participant_scope' => 'all'],
            'Abteilungsleitung' => ['team_scope' => 'department', 'participant_scope' => 'department'],
            'Assistenz der Abt.-Leitung' => ['team_scope' => 'department', 'participant_scope' => 'department'],
            'Sozialpädagoge' => ['team_scope' => 'own_projects', 'participant_scope' => 'own_locations'],
            'Anleiter' => ['team_scope' => 'own_projects', 'participant_scope' => 'current_project_same_location'],
        ];

        DB::table('roles')
            ->whereIn('name', array_keys($defaults))
            ->get(['id', 'name'])
            ->each(function ($role) use ($defaults) {
                DB::table('role_data_access_settings')->updateOrInsert(
                    ['role_id' => $role->id],
                    [
                        'team_scope' => $defaults[$role->name]['team_scope'],
                        'participant_scope' => $defaults[$role->name]['participant_scope'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_data_access_settings');
    }
};
