<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RepairAdministrator extends Command
{
    protected $signature = 'admin:repair
        {email=amin.masri@outlook.com : E-Mail-Adresse des Administrators}';

    protected $description = 'Stellt Administratorrolle, alle Web-Berechtigungen und den Projektzugang wieder her';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user) {
            $this->error("Benutzer {$email} wurde nicht gefunden.");

            return self::FAILURE;
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'Administrator', 'guard_name' => 'web'],
            ['color' => '#dc3545']
        );

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->get();

        if ($permissions->isEmpty()) {
            $this->error('Es sind keine Web-Berechtigungen vorhanden. Bitte zuerst den PermissionsSeeder ausführen.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $role, $permissions): void {
            $role->syncPermissions($permissions);
            $user->syncRoles([$role]);

            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $project = DB::table('projekts')
                ->whereRaw('LOWER(name) = ?', ['bop'])
                ->first();

            if (! $project || ! $user->person_id) {
                return;
            }

            DB::table('projekt_has_personens')->insertOrIgnore(
                [
                    'personen_id' => $user->person_id,
                    'projekt_id' => $project->id,
                    'status' => 'aktiv',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $user->forceFill([
                'current_team_id' => $project->id,
                'default_projekt_id' => $project->id,
            ])->save();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user->refresh();
        $this->info("Administratorzugang für {$user->email} wurde repariert.");
        $this->table(
            ['Prüfung', 'Wert'],
            [
                ['Benutzer-ID', $user->id],
                ['Rolle', $user->getRoleNames()->join(', ')],
                ['Berechtigungen', $user->getAllPermissions()->count().' / '.$permissions->count()],
                ['Dashboard', $user->can('dashboard.index') ? 'erlaubt' : 'nicht erlaubt'],
                ['Aktives Projekt', $user->current_team_id ?: 'keins'],
            ]
        );

        return $user->can('dashboard.index') ? self::SUCCESS : self::FAILURE;
    }
}
