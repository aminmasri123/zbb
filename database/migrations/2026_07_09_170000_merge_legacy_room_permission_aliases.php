<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION_ALIASES = [
        'räumlichkeiten.index' => 'raeumlichkeiten.index',
        'räumlichkeiten.store' => 'raeumlichkeiten.store',
        'räumlichkeiten.update' => 'raeumlichkeiten.update',
        'räumlichkeiten.destroy' => 'raeumlichkeiten.destroy',
    ];

    public function up(): void
    {
        foreach (self::PERMISSION_ALIASES as $legacyName => $canonicalName) {
            $legacy = $this->permission($legacyName);

            if (! $legacy) {
                continue;
            }

            $canonical = $this->permission($canonicalName);

            if (! $canonical) {
                DB::table('permissions')
                    ->where('id', $legacy->id)
                    ->update([
                        'name' => $canonicalName,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            $this->movePermissionAssignments((int) $legacy->id, (int) $canonical->id);

            DB::table('notification_rules')
                ->where('target_type', 'permission')
                ->where('target_value', $legacyName)
                ->update([
                    'target_value' => $canonicalName,
                    'updated_at' => now(),
                ]);

            DB::table('permissions')->where('id', $legacy->id)->delete();
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        foreach (self::PERMISSION_ALIASES as $legacyName => $canonicalName) {
            $canonical = $this->permission($canonicalName);

            if (! $canonical) {
                continue;
            }

            $legacy = $this->permission($legacyName);
            $legacyId = $legacy?->id ?? DB::table('permissions')->insertGetId([
                'name' => $legacyName,
                'guard_name' => 'web',
                'berechtigungskategorie_id' => $canonical->berechtigungskategorie_id,
                'beschreibung' => $canonical->beschreibung,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->copyPermissionAssignments((int) $canonical->id, (int) $legacyId);
        }

        $this->forgetPermissionCache();
    }

    private function permission(string $name): ?object
    {
        return DB::table('permissions')
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->first();
    }

    private function movePermissionAssignments(int $sourcePermissionId, int $targetPermissionId): void
    {
        $this->copyPermissionAssignments($sourcePermissionId, $targetPermissionId);

        foreach (['role_has_permissions', 'model_has_permissions'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->where('permission_id', $sourcePermissionId)->delete();
        }
    }

    private function copyPermissionAssignments(int $sourcePermissionId, int $targetPermissionId): void
    {
        foreach (['role_has_permissions', 'model_has_permissions'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)
                ->where('permission_id', $sourcePermissionId)
                ->get();

            foreach ($rows as $row) {
                $payload = (array) $row;
                $payload['permission_id'] = $targetPermissionId;

                DB::table($table)->insertOrIgnore($payload);
            }
        }
    }

    private function forgetPermissionCache(): void
    {
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
