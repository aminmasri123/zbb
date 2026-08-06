<?php

use App\Models\AppCalendar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_calendars', function (Blueprint $table) {
            $table->string('kind', 20)->default('personal')->after('team_id');
        });

        Schema::table('app_calendar_events', function (Blueprint $table) {
            $table->string('audience', 20)->default('owner')->after('visibility');
            $table->string('source_type')->nullable()->after('audience');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id'], 'app_calendar_events_source_index');
        });

        DB::table('app_calendars')->whereNotNull('project_id')->update(['kind' => 'project']);
        DB::table('app_calendar_events')->where('visibility', 'project')->update(['audience' => 'project']);

        DB::table('app_calendars')
            ->whereNotNull('project_id')
            ->orderBy('id')
            ->get()
            ->groupBy('project_id')
            ->each(function ($calendars) {
                $canonical = $calendars->first();

                foreach ($calendars->slice(1) as $duplicate) {
                    DB::table('app_calendar_events')
                        ->where('calendar_id', $duplicate->id)
                        ->update(['calendar_id' => $canonical->id]);

                    DB::table('app_shares')
                        ->where('shareable_type', AppCalendar::class)
                        ->where('shareable_id', $duplicate->id)
                        ->update(['shareable_id' => $canonical->id]);

                    DB::table('app_calendars')->where('id', $duplicate->id)->delete();
                }
            });

        Schema::table('app_calendars', function (Blueprint $table) {
            $table->unique(['project_id', 'kind'], 'app_calendars_project_kind_unique');
        });

        Schema::create('app_calendar_event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('app_calendar_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('access_level', 20)->default('responsible');
            $table->boolean('response_required')->default(true);
            $table->string('response', 20)->default('pending');
            $table->text('response_note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
            $table->index(['user_id', 'response']);
        });

        $this->createCalendarPermissions();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', $this->permissionNames())
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::dropIfExists('app_calendar_event_attendees');

        Schema::table('app_calendars', function (Blueprint $table) {
            $table->dropUnique('app_calendars_project_kind_unique');
            $table->dropColumn('kind');
        });

        Schema::table('app_calendar_events', function (Blueprint $table) {
            $table->dropIndex('app_calendar_events_source_index');
            $table->dropColumn(['audience', 'source_type', 'source_id']);
        });
    }

    private function createCalendarPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('berechtigungskategories')) {
            return;
        }

        $categoryId = DB::table('berechtigungskategories')
            ->where('name', 'Kalender')
            ->value('id');

        $categoryId ??= DB::table('permissions')->where('name', 'apps.calendar')->value('berechtigungskategorie_id');

        if (! $categoryId) {
            return;
        }

        $descriptions = [
            'apps.calendar.project.view.all' => 'Erlaubt das Einsehen aller Termine in zugeordneten Projektkalendern.',
            'apps.calendar.project.manage' => 'Erlaubt das Anlegen und Bearbeiten von Terminen in zugeordneten Projektkalendern.',
            'apps.calendar.project.assign' => 'Erlaubt die Zuweisung von Projektterminen an Mitarbeitende.',
            'apps.calendar.respond' => 'Erlaubt die Zu- oder Absage eigener zugewiesener Kalendertermine.',
        ];

        foreach ($descriptions as $name => $description) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['berechtigungskategorie_id' => $categoryId, 'beschreibung' => $description]
            );
        }

        $leadRoleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['Administrator', 'Abteilungsleitung', 'Projektleitung'])
            ->pluck('id');

        $leadPermissionIds = DB::table('permissions')
            ->whereIn('name', [
                'apps.calendar.project.view.all',
                'apps.calendar.project.manage',
                'apps.calendar.project.assign',
                'apps.calendar.respond',
            ])
            ->pluck('id');

        foreach ($leadRoleIds as $roleId) {
            foreach ($leadPermissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        $respondPermissionId = DB::table('permissions')->where('name', 'apps.calendar.respond')->value('id');
        $calendarPermissionId = DB::table('permissions')->where('name', 'apps.calendar')->value('id');

        if ($respondPermissionId && $calendarPermissionId) {
            DB::table('role_has_permissions')
                ->where('permission_id', $calendarPermissionId)
                ->pluck('role_id')
                ->each(fn ($roleId) => DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $respondPermissionId,
                ]));
        }
    }

    private function permissionNames(): array
    {
        return [
            'apps.calendar.project.view.all',
            'apps.calendar.project.manage',
            'apps.calendar.project.assign',
            'apps.calendar.respond',
        ];
    }
};
