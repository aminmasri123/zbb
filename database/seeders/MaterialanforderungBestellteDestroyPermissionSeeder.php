<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class MaterialanforderungBestellteDestroyPermissionSeeder extends Seeder
{
    private const PERMISSION = 'materialanforderung.bestellte.destroy';

    public function run(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('berechtigungskategories')) {
            throw new RuntimeException(
                'Die Berechtigungstabellen fehlen. Bitte zuerst "php artisan migrate --force" ausführen.'
            );
        }

        DB::transaction(function (): void {
            $categoryId = DB::table('berechtigungskategories')
                ->where('name', 'Bestellungen')
                ->value('id');

            if (! $categoryId) {
                $categoryId = DB::table('berechtigungskategories')->insertGetId([
                    'name' => 'Bestellungen',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $permission = DB::table('permissions')
                ->where('name', self::PERMISSION)
                ->where('guard_name', 'web')
                ->first();

            $values = [
                'beschreibung' => 'Bestellte Materialanforderungen löschen',
                'berechtigungskategorie_id' => $categoryId,
                'updated_at' => now(),
            ];

            if ($permission) {
                DB::table('permissions')
                    ->where('id', $permission->id)
                    ->update($values);
            } else {
                DB::table('permissions')->insert([
                    'name' => self::PERMISSION,
                    'guard_name' => 'web',
                    ...$values,
                    'created_at' => now(),
                ]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Berechtigung "'.self::PERMISSION.'" wurde sichergestellt.');
    }
}
