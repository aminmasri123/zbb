<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'zutritt.index' => ['Zutrittsverwaltung ansehen', 'Erlaubt das Einsehen der Zutrittsantraege und ihrer Bearbeitungsstaende.'],
        'zutritt.antrag.store' => ['Zutrittsantraege stellen', 'Erlaubt das Stellen eines neuen Zutrittsantrags fuer eine berechtigte Person.'],
        'zutritt.antrag.approve' => ['Zutrittsantraege genehmigen', 'Erlaubt das Genehmigen oder Ablehnen von Zutrittsantraegen. Eine Selbstgenehmigung bleibt ausgeschlossen.'],
        'zutritt.aktivierung.update' => ['Zutritte technisch bearbeiten', 'Erlaubt die dokumentierte manuelle Aktivierung und den Entzug genehmigter Zutrittsrechte.'],
        'zutritt.stammdaten.manage' => ['Zutrittsstammdaten verwalten', 'Erlaubt das Verwalten von Tueren und Zutrittsprofilen.'],
    ];

    public function up(): void
    {
        Schema::create('access_doors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standort_id')->nullable()->constrained('standorts')->nullOnDelete();
            $table->foreignId('room_from_id')->nullable()->constrained('raeumes')->nullOnDelete();
            $table->foreignId('room_to_id')->nullable()->constrained('raeumes')->nullOnDelete();
            $table->string('name', 160);
            $table->string('code', 80)->nullable()->unique();
            $table->string('external_reference', 160)->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['standort_id', 'active']);
        });

        Schema::create('access_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160)->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('access_profile_door', function (Blueprint $table) {
            $table->foreignId('access_profile_id')->constrained('access_profiles')->cascadeOnDelete();
            $table->foreignId('access_door_id')->constrained('access_doors')->cascadeOnDelete();

            $table->primary(['access_profile_id', 'access_door_id']);
        });

        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_for_person_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->string('requested_for_name', 200);
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_by_name', 200);
            $table->foreignId('access_profile_id')->nullable()->constrained('access_profiles')->nullOnDelete();
            $table->json('profile_snapshot');
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->text('reason');
            $table->string('status', 30)->default('submitted');
            $table->timestamp('submitted_at');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->string('technical_reference', 255)->nullable();
            $table->text('activation_note')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'valid_from', 'valid_until']);
            $table->index(['requested_for_person_id', 'status']);
        });

        Schema::create('access_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_request_id')->constrained('access_requests')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 200);
            $table->string('event_type', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('comment')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at');

            $table->index(['access_request_id', 'created_at']);
        });

        $now = now();
        DB::table('modules')->updateOrInsert(
            ['key' => 'access_management'],
            [
                'name' => 'Zutrittsverwaltung',
                'description' => 'Tueren, Zutrittsprofile, Antraege, Genehmigungen und manuelle technische Aktivierung.',
                'category' => 'resources',
                'is_system_module' => false,
                'is_enforced' => true,
                'supports_location_scope' => false,
                'visible_in_settings' => true,
                'default_enabled' => false,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $categoryId = DB::table('berechtigungskategories')->where('name', 'Zutrittsverwaltung')->value('id');

        if (! $categoryId) {
            $categoryId = DB::table('berechtigungskategories')->insertGetId([
                'name' => 'Zutrittsverwaltung',
                'beschreibung' => 'Zutrittsantraege, Genehmigungen, technische Aktivierung sowie Tuer- und Profilstammdaten.',
            ]);
        }

        foreach (self::PERMISSIONS as $name => [$displayName, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'display_name' => $displayName,
                    'berechtigungskategorie_id' => $categoryId,
                    'beschreibung' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $administratorRoleId = DB::table('roles')
            ->where('name', 'Administrator')
            ->where('guard_name', 'web')
            ->value('id');

        if ($administratorRoleId) {
            DB::table('role_berechtigungskategories')->insertOrIgnore([
                'role_id' => $administratorRoleId,
                'berechtigungskategorie_id' => $categoryId,
            ]);

            foreach (DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id') as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $administratorRoleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        $categoryId = DB::table('berechtigungskategories')->where('name', 'Zutrittsverwaltung')->value('id');
        if ($categoryId) {
            DB::table('role_berechtigungskategories')->where('berechtigungskategorie_id', $categoryId)->delete();
            DB::table('berechtigungskategories')->where('id', $categoryId)->delete();
        }

        $moduleId = DB::table('modules')->where('key', 'access_management')->value('id');
        if ($moduleId) {
            DB::table('module_assignments')->where('module_id', $moduleId)->delete();
            DB::table('modules')->where('id', $moduleId)->delete();
        }

        Schema::dropIfExists('access_request_events');
        Schema::dropIfExists('access_requests');
        Schema::dropIfExists('access_profile_door');
        Schema::dropIfExists('access_profiles');
        Schema::dropIfExists('access_doors');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
