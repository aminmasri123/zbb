<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lager_artikel', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kategorie', 80)->nullable();
            $table->string('artikelnummer', 80)->nullable();
            $table->string('einheit', 30)->default('Stk');
            $table->decimal('bestand', 12, 2)->default(0);
            $table->decimal('mindestbestand', 12, 2)->default(0);
            $table->string('lagerort')->nullable();
            $table->string('lieferant')->nullable();
            $table->text('beschreibung')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();

            $table->unique('artikelnummer');
            $table->index(['aktiv', 'kategorie']);
        });

        Schema::create('lager_reservierungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lager_artikel_id')->constrained('lager_artikel')->cascadeOnDelete();
            $table->foreignId('angefordert_von_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('angefordert_von_personen_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->decimal('menge', 12, 2);
            $table->string('status', 40)->default('reserviert');
            $table->string('zweck')->nullable();
            $table->text('bemerkung')->nullable();
            $table->timestamp('ausgegeben_at')->nullable();
            $table->timestamp('storniert_at')->nullable();
            $table->timestamps();

            $table->index(['lager_artikel_id', 'status']);
            $table->index(['angefordert_von_user_id', 'status']);
        });

        Schema::create('lager_bewegungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lager_artikel_id')->constrained('lager_artikel')->cascadeOnDelete();
            $table->foreignId('lager_reservierung_id')->nullable()->constrained('lager_reservierungen')->nullOnDelete();
            $table->foreignId('gebucht_von_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gebucht_von_personen_id')->nullable()->constrained('personens')->nullOnDelete();
            $table->string('typ', 40);
            $table->decimal('menge', 12, 2);
            $table->decimal('bestand_nachher', 12, 2);
            $table->text('bemerkung')->nullable();
            $table->timestamps();

            $table->index(['lager_artikel_id', 'created_at']);
            $table->index('typ');
        });

        $categoryId = $this->ensureCategory();
        $this->upsertPermissions($categoryId, [
            'lager.index' => 'Erlaubt das Einsehen der internen Lageruebersicht inklusive Verfuegbarkeit und Reservierungen.',
            'lager.artikel.store' => 'Erlaubt das Anlegen neuer interner Lagerartikel.',
            'lager.artikel.update' => 'Erlaubt das Bearbeiten interner Lagerartikel und Stammdaten.',
            'lager.artikel.destroy' => 'Erlaubt das Deaktivieren oder Loeschen interner Lagerartikel.',
            'lager.bewegung.store' => 'Erlaubt das Buchen von Lagerbewegungen wie Eingang, Ausgang oder Korrektur.',
            'lager.reservierung.store' => 'Erlaubt das interne Reservieren von verfuegbaren Lagerartikeln.',
            'lager.reservierung.update' => 'Erlaubt das Ausgeben oder Stornieren interner Lagerreservierungen.',
        ]);
    }

    public function down(): void
    {
        $permissionNames = [
            'lager.index',
            'lager.artikel.store',
            'lager.artikel.update',
            'lager.artikel.destroy',
            'lager.bewegung.store',
            'lager.reservierung.store',
            'lager.reservierung.update',
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        $categoryId = DB::table('berechtigungskategories')->where('name', 'Lager')->value('id');

        if ($categoryId) {
            DB::table('role_berechtigungskategories')
                ->where('berechtigungskategorie_id', $categoryId)
                ->delete();

            DB::table('berechtigungskategories')
                ->where('id', $categoryId)
                ->where('name', 'Lager')
                ->delete();
        }

        Schema::dropIfExists('lager_bewegungen');
        Schema::dropIfExists('lager_reservierungen');
        Schema::dropIfExists('lager_artikel');

        $this->clearPermissionCache();
    }

    private function ensureCategory(): int
    {
        $category = DB::table('berechtigungskategories')->where('name', 'Lager')->first();

        if (! $category) {
            DB::table('berechtigungskategories')->insert([
                'name' => 'Lager',
                'beschreibung' => 'Interne Lagerverwaltung fuer Verbrauchsmaterial und Betriebsmittel.',
            ]);

            $category = DB::table('berechtigungskategories')->where('name', 'Lager')->first();
        }

        return (int) $category->id;
    }

    private function upsertPermissions(int $categoryId, array $permissions): void
    {
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
            ->whereIn('name', ['Administrator', 'Developer'])
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

    private function clearPermissionCache(): void
    {
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
