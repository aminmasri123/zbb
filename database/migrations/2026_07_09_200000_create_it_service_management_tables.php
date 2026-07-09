<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geraets', function (Blueprint $table) {
            if (! Schema::hasColumn('geraets', 'standort_id')) {
                $table->foreignId('standort_id')->nullable()->after('imLager')->constrained('standorts')->nullOnDelete();
            }

            if (! Schema::hasColumn('geraets', 'verantwortliche_personen_id')) {
                $table->foreignId('verantwortliche_personen_id')->nullable()->after('standort_id')->constrained('personens')->nullOnDelete();
            }

            if (! Schema::hasColumn('geraets', 'inventarnummer')) {
                $table->string('inventarnummer', 80)->nullable()->after('productID')->unique();
            }

            if (! Schema::hasColumn('geraets', 'kategorie')) {
                $table->string('kategorie', 80)->nullable()->after('geraet')->index();
            }

            if (! Schema::hasColumn('geraets', 'status')) {
                $table->string('status', 40)->nullable()->after('zustand')->index();
            }

            if (! Schema::hasColumn('geraets', 'raum')) {
                $table->string('raum', 120)->nullable()->after('verantwortliche_personen_id');
            }

            if (! Schema::hasColumn('geraets', 'ip_adresse')) {
                $table->string('ip_adresse', 80)->nullable()->after('modell');
            }

            if (! Schema::hasColumn('geraets', 'mac_adresse')) {
                $table->string('mac_adresse', 80)->nullable()->after('ip_adresse');
            }

            if (! Schema::hasColumn('geraets', 'betriebssystem')) {
                $table->string('betriebssystem', 120)->nullable()->after('mac_adresse');
            }

            if (! Schema::hasColumn('geraets', 'letzte_wartung_am')) {
                $table->date('letzte_wartung_am')->nullable()->after('garantiefrist');
            }

            if (! Schema::hasColumn('geraets', 'naechste_wartung_am')) {
                $table->date('naechste_wartung_am')->nullable()->after('letzte_wartung_am');
            }

            if (! Schema::hasColumn('geraets', 'notiz')) {
                $table->text('notiz')->nullable()->after('naechste_wartung_am');
            }
        });

        Schema::create('it_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_nr', 30)->nullable()->unique();
            $table->foreignId('standort_id')->nullable()->constrained('standorts')->nullOnDelete();
            $table->foreignId('geraet_id')->nullable()->constrained('geraets')->nullOnDelete();
            $table->foreignId('gemeldet_von_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gemeldet_von_personen_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->foreignId('betroffene_personen_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->foreignId('zugewiesen_an_personen_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->foreignId('geloest_von_personen_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->string('titel', 150);
            $table->string('kategorie', 60);
            $table->string('prioritaet', 30)->default('normal');
            $table->string('status', 50)->default('neu');
            $table->string('raum', 120)->nullable();
            $table->string('kontakt', 160)->nullable();
            $table->text('beschreibung')->nullable();
            $table->text('planung')->nullable();
            $table->text('loesung')->nullable();
            $table->text('interne_notiz')->nullable();
            $table->date('faellig_am')->nullable();
            $table->timestamp('geplant_am')->nullable();
            $table->timestamp('begonnen_at')->nullable();
            $table->timestamp('geloest_at')->nullable();
            $table->timestamp('geschlossen_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'prioritaet']);
            $table->index(['standort_id', 'status']);
            $table->index(['zugewiesen_an_personen_id', 'status'], 'it_tickets_assignee_status_index');
        });

        $categoryId = $this->ensureCategory();
        $this->upsertPermissions($categoryId, [
            'it.service.index' => 'Erlaubt das Einsehen des IT-Service-Dashboards mit Tickets und Geraeten aller Standorte.',
            'it.ticket.store' => 'Erlaubt das Anlegen neuer IT-Tickets fuer Standorte, Personen oder Geraete.',
            'it.ticket.update' => 'Erlaubt das Priorisieren, Planen, Zuweisen und Abschliessen von IT-Tickets.',
            'it.ticket.destroy' => 'Erlaubt das Loeschen von IT-Tickets.',
            'it.geraet.store' => 'Erlaubt das Anlegen neuer IT-Geraete im IT-Service.',
            'it.geraet.update' => 'Erlaubt das Bearbeiten von IT-Geraeten inklusive Standort, Verantwortlichkeit und Wartungsdaten.',
            'it.geraet.destroy' => 'Erlaubt das Loeschen oder Aussondern von IT-Geraeten.',
        ]);
    }

    public function down(): void
    {
        $permissionNames = [
            'it.service.index',
            'it.ticket.store',
            'it.ticket.update',
            'it.ticket.destroy',
            'it.geraet.store',
            'it.geraet.update',
            'it.geraet.destroy',
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        $categoryId = DB::table('berechtigungskategories')->where('name', 'IT-Service')->value('id');

        if ($categoryId) {
            DB::table('role_berechtigungskategories')
                ->where('berechtigungskategorie_id', $categoryId)
                ->delete();

            DB::table('berechtigungskategories')
                ->where('id', $categoryId)
                ->where('name', 'IT-Service')
                ->delete();
        }

        Schema::dropIfExists('it_tickets');

        Schema::table('geraets', function (Blueprint $table) {
            if (Schema::hasColumn('geraets', 'standort_id')) {
                $table->dropConstrainedForeignId('standort_id');
            }

            if (Schema::hasColumn('geraets', 'verantwortliche_personen_id')) {
                $table->dropConstrainedForeignId('verantwortliche_personen_id');
            }

            $columns = collect([
                'inventarnummer',
                'kategorie',
                'status',
                'raum',
                'ip_adresse',
                'mac_adresse',
                'betriebssystem',
                'letzte_wartung_am',
                'naechste_wartung_am',
                'notiz',
            ])->filter(fn (string $column) => Schema::hasColumn('geraets', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        $this->clearPermissionCache();
    }

    private function ensureCategory(): int
    {
        $category = DB::table('berechtigungskategories')->where('name', 'IT-Service')->first();

        if (! $category) {
            DB::table('berechtigungskategories')->insert([
                'name' => 'IT-Service',
                'beschreibung' => 'Helpdesk, IT-Tickets und IT-Geraeteverwaltung.',
            ]);

            $category = DB::table('berechtigungskategories')->where('name', 'IT-Service')->first();
        }

        return (int) $category->id;
    }

    private function upsertPermissions(int $categoryId, array $permissions): void
    {
        $this->ensureItRole();

        foreach ($permissions as $name => $description) {
            DB::table('permissions')->updateOrInsert(
                [
                    'name' => $name,
                    'guard_name' => 'web',
                ],
                [
                    'berechtigungskategorie_id' => $categoryId,
                    'beschreibung' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys($permissions))
            ->where('guard_name', 'web')
            ->pluck('id');

        $roleIds = DB::table('roles')
            ->whereIn('name', ['Administrator', 'Developer', 'IT'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_berechtigungskategories')->insertOrIgnore([
                'role_id' => $roleId,
                'berechtigungskategorie_id' => $categoryId,
            ]);

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $this->clearPermissionCache();
    }

    private function ensureItRole(): void
    {
        DB::table('roles')->updateOrInsert(
            [
                'name' => 'IT',
                'guard_name' => 'web',
            ],
            [
                'color' => 'bg-sky-300',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function clearPermissionCache(): void
    {
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
