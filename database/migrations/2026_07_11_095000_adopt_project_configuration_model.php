<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->boolean('visible_in_settings')->default(true)->after('supports_location_scope');
        });

        DB::table('modules')->update(['supports_location_scope' => false]);

        DB::table('modules')
            ->whereIn('key', ['bop', 'bvb_reha'])
            ->update([
                'is_enforced' => false,
                'visible_in_settings' => false,
            ]);

        DB::table('modules')->updateOrInsert(
            ['key' => 'participant_management'],
            [
                'name' => 'Teilnehmerverwaltung',
                'description' => 'Zentrale Verwaltung von Teilnehmern und ihren Projektzuordnungen',
                'category' => 'core',
                'is_system_module' => false,
                'is_enforced' => true,
                'supports_location_scope' => false,
                'visible_in_settings' => true,
                'default_enabled' => true,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        $participantModuleId = DB::table('modules')
            ->where('key', 'participant_management')
            ->value('id');

        if ($participantModuleId) {
            DB::table('module_assignments')->where('module_id', $participantModuleId)->delete();
            DB::table('modules')->where('id', $participantModuleId)->delete();
        }

        DB::table('modules')
            ->where('key', 'room_management')
            ->update(['supports_location_scope' => true]);

        DB::table('modules')
            ->where('key', 'bop')
            ->update(['is_enforced' => true]);

        DB::table('modules')
            ->where('key', 'bvb_reha')
            ->update(['is_enforced' => true]);

        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('visible_in_settings');
        });
    }
};
