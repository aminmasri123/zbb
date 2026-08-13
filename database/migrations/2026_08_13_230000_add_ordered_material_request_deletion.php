<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'materialanforderung.bestellte.destroy';

    public function up(): void
    {
        Schema::create('materialanforderung_loeschprotokolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('materialanforderung_id')->index();
            $table->unsignedBigInteger('projekt_id')->nullable()->index();
            $table->unsignedBigInteger('ersteller_id')->nullable()->index();
            $table->foreignId('geloescht_von_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40);
            $table->string('bestellnummer')->nullable();
            $table->decimal('endsumme', 12, 2)->default(0);
            $table->text('begruendung');
            $table->json('snapshot');
            $table->timestamp('geloescht_am');
            $table->timestamps();
        });

        $categoryId = DB::table('berechtigungskategories')
            ->where('name', 'Bestellungen')
            ->value('id');

        if (! $categoryId) {
            $categoryId = DB::table('berechtigungskategories')->insertGetId([
                'name' => 'Bestellungen',
                'beschreibung' => 'Materialanforderungen, Freigaben und Bestellwesen.',
            ]);
        }

        DB::table('permissions')->updateOrInsert(
            ['name' => self::PERMISSION, 'guard_name' => 'web'],
            [
                'berechtigungskategorie_id' => $categoryId,
                'beschreibung' => 'Erlaubt das endgültige Löschen bestellter oder teilweise gelieferter Materialanforderungen mit Pflichtbegründung und Löschprotokoll.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        $roleIds = DB::table('roles')
            ->whereIn('name', ['Administrator', 'Developer'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_berechtigungskategories')->insertOrIgnore([
                'role_id' => $roleId,
                'berechtigungskategorie_id' => $categoryId,
            ]);
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        $this->clearPermissionCache();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::dropIfExists('materialanforderung_loeschprotokolls');
        $this->clearPermissionCache();
    }

    private function clearPermissionCache(): void
    {
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
