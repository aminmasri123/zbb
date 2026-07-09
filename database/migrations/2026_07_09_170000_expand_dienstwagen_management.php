<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dienstwagens', function (Blueprint $table) {
            if (! Schema::hasColumn('dienstwagens', 'bild_path')) {
                $table->string('bild_path')->nullable()->after('naechste_wartung');
                $table->string('fin')->nullable()->after('bild_path');
                $table->string('hsn_tsn')->nullable()->after('fin');
                $table->date('tuev_bis')->nullable()->after('hsn_tsn');
                $table->date('au_bis')->nullable()->after('tuev_bis');
                $table->date('oelwechsel_am')->nullable()->after('au_bis');
                $table->integer('oelwechsel_km')->nullable()->after('oelwechsel_am');
                $table->date('versicherung_bis')->nullable()->after('oelwechsel_km');
                $table->date('steuer_faellig_am')->nullable()->after('versicherung_bis');
                $table->date('inspektion_am')->nullable()->after('steuer_faellig_am');
                $table->date('reifenwechsel_am')->nullable()->after('inspektion_am');
                $table->date('leasing_bis')->nullable()->after('reifenwechsel_am');
                $table->string('tankkarte')->nullable()->after('leasing_bis');
                $table->text('notizen')->nullable()->after('tankkarte');
            }
        });

        Schema::table('dienstwagenfahrtenbuches', function (Blueprint $table) {
            if (! Schema::hasColumn('dienstwagenfahrtenbuches', 'startort')) {
                $table->string('startort')->nullable()->after('date');
                $table->string('fahrtart')->default('dienstlich')->after('ziel');
                $table->string('geschaeftspartner')->nullable()->after('fahrtart');
                $table->text('bemerkung')->nullable()->after('geschaeftspartner');
            }
        });

        Schema::create('dienstwagen_buchungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dienstwagen_id')->constrained('dienstwagens')->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('ziel')->nullable();
            $table->string('zweck');
            $table->string('status')->default('geplant');
            $table->integer('start_km')->nullable();
            $table->integer('end_km')->nullable();
            $table->text('notizen')->nullable();
            $table->timestamps();

            $table->index(['dienstwagen_id', 'start_at', 'end_at']);
            $table->index(['person_id', 'start_at']);
            $table->index('status');
        });

        Schema::create('dienstwagen_meldungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dienstwagen_id')->constrained('dienstwagens')->cascadeOnDelete();
            $table->foreignId('gemeldet_von_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gemeldet_von_personen_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->foreignId('verantwortlich_person_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->string('titel');
            $table->string('kategorie')->default('sonstiges');
            $table->string('prioritaet')->default('normal');
            $table->string('status')->default('offen');
            $table->text('beschreibung')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamp('erledigt_am')->nullable();
            $table->timestamps();

            $table->index(['dienstwagen_id', 'status']);
            $table->index(['prioritaet', 'status']);
        });

        Schema::create('dienstwagen_verlaeufe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dienstwagen_id')->nullable()->constrained('dienstwagens')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->string('aktion', 80);
            $table->string('titel');
            $table->text('beschreibung')->nullable();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->json('changes_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['dienstwagen_id', 'created_at']);
            $table->index(['related_type', 'related_id']);
        });

        $this->upsertPermissions([
            'dienstwagen.buchungen.index' => 'Erlaubt das Einsehen von Dienstwagenbuchungen.',
            'dienstwagen.buchungen.store' => 'Erlaubt das Anlegen von Dienstwagenbuchungen.',
            'dienstwagen.buchungen.update' => 'Erlaubt das Bearbeiten von Dienstwagenbuchungen.',
            'dienstwagen.buchungen.destroy' => 'Erlaubt das Loeschen oder Stornieren von Dienstwagenbuchungen.',
            'dienstwagen.meldungen.index' => 'Erlaubt das Einsehen von Dienstwagenmeldungen.',
            'dienstwagen.meldungen.store' => 'Erlaubt das Erfassen von Schaeden, Reparaturen und Aufgaben an Dienstwagen.',
            'dienstwagen.meldungen.update' => 'Erlaubt das Bearbeiten und Abschliessen von Dienstwagenmeldungen.',
            'dienstwagen.meldungen.destroy' => 'Erlaubt das Loeschen von Dienstwagenmeldungen.',
            'dienstwagen.verlauf.index' => 'Erlaubt das Einsehen des Dienstwagenverlaufs.',
            'dienstwagen.kosten.update' => 'Erlaubt das Bearbeiten von Dienstwagenkosten.',
            'dienstwagen.kosten.destroy' => 'Erlaubt das Loeschen von Dienstwagenkosten.',
        ]);
    }

    public function down(): void
    {
        $permissionNames = [
            'dienstwagen.buchungen.index',
            'dienstwagen.buchungen.store',
            'dienstwagen.buchungen.update',
            'dienstwagen.buchungen.destroy',
            'dienstwagen.meldungen.index',
            'dienstwagen.meldungen.store',
            'dienstwagen.meldungen.update',
            'dienstwagen.meldungen.destroy',
            'dienstwagen.verlauf.index',
            'dienstwagen.kosten.update',
            'dienstwagen.kosten.destroy',
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('dienstwagen_verlaeufe');
        Schema::dropIfExists('dienstwagen_meldungen');
        Schema::dropIfExists('dienstwagen_buchungen');

        Schema::table('dienstwagenfahrtenbuches', function (Blueprint $table) {
            foreach (['startort', 'fahrtart', 'geschaeftspartner', 'bemerkung'] as $column) {
                if (Schema::hasColumn('dienstwagenfahrtenbuches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('dienstwagens', function (Blueprint $table) {
            $columns = [
                'bild_path',
                'fin',
                'hsn_tsn',
                'tuev_bis',
                'au_bis',
                'oelwechsel_am',
                'oelwechsel_km',
                'versicherung_bis',
                'steuer_faellig_am',
                'inspektion_am',
                'reifenwechsel_am',
                'leasing_bis',
                'tankkarte',
                'notizen',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('dienstwagens', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function upsertPermissions(array $permissions): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('berechtigungskategories')) {
            return;
        }

        $categoryId = DB::table('berechtigungskategories')
            ->where('name', 'Dienstwagen')
            ->value('id') ?: 25;

        if (! DB::table('berechtigungskategories')->where('id', $categoryId)->exists()) {
            return;
        }

        foreach ($permissions as $name => $beschreibung) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'berechtigungskategorie_id' => $categoryId,
                    'beschreibung' => $beschreibung,
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
            ->whereIn('name', ['Administrator', 'Developer'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        if (function_exists('app')) {
            app('cache')
                ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
                ->forget(config('permission.cache.key'));
        }
    }
};
