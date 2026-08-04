<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

class DocumentExportPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasColumn('dokumentes', 'export_permission')) {
            $this->command?->warn('Spalte dokumentes.export_permission fehlt. Bitte zuerst php artisan migrate ausfuehren.');

            return;
        }

        $categoryId = $this->documentCategoryId();
        $administratorRoleId = DB::table('roles')->where('name', 'Administrator')->value('id');

        DB::table('dokumentes')
            ->select(['id', 'name', 'export_permission'])
            ->chunkById(100, function ($documents) use ($categoryId, $administratorRoleId): void {
                foreach ($documents as $document) {
                    $permissionName = $document->export_permission ?: $this->permissionName((int) $document->id);

                    DB::table('dokumentes')
                        ->where('id', $document->id)
                        ->update(['export_permission' => $permissionName]);

                    $permissionId = $this->upsertPermission(
                        $permissionName,
                        $categoryId,
                        'Erlaubt den Export der Dokumentvorlage "' . $document->name . '".'
                    );

                    if ($administratorRoleId && $permissionId) {
                        DB::table('role_has_permissions')->insertOrIgnore([
                            'permission_id' => $permissionId,
                            'role_id' => $administratorRoleId,
                        ]);
                    }
                }
            }, 'id');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function documentCategoryId(): int
    {
        $id = DB::table('berechtigungskategories')->where('name', 'Dokumentenexporte')->value('id');
        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('berechtigungskategories')->insertGetId([
            'name' => 'Dokumentenexporte',
            'beschreibung' => 'Einzelberechtigungen fuer den Export bestimmter Dokumentvorlagen.',
        ]);
    }

    private function upsertPermission(string $name, int $categoryId, string $description): int
    {
        $permissionId = DB::table('permissions')
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return (int) DB::table('permissions')->insertGetId([
                'name' => $name,
                'guard_name' => 'web',
                'berechtigungskategorie_id' => $categoryId,
                'beschreibung' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permissions')
            ->where('id', $permissionId)
            ->update([
                'berechtigungskategorie_id' => $categoryId,
                'beschreibung' => $description,
                'updated_at' => now(),
            ]);

        return (int) $permissionId;
    }

    private function permissionName(int $documentId): string
    {
        return 'dokumente.export.' . $documentId;
    }
}
