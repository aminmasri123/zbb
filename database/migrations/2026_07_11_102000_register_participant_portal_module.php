<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modules')->updateOrInsert(
            ['key' => 'participant_portal'],
            [
                'name' => 'Teilnehmerportal',
                'description' => 'Self-Service-Zugang für Profile, Aufgaben, Termine, Jobsuche, Bewerbungen und Kurse',
                'category' => 'platform',
                'is_system_module' => false,
                'is_enforced' => true,
                'supports_location_scope' => false,
                'visible_in_settings' => true,
                'default_enabled' => false,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        $moduleId = DB::table('modules')->where('key', 'participant_portal')->value('id');
        if ($moduleId) {
            DB::table('module_assignments')->where('module_id', $moduleId)->delete();
            DB::table('modules')->where('id', $moduleId)->delete();
        }
    }
};
