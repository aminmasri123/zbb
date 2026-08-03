<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'teilnehmer.elterneinverstaendnis.update';

    public function up(): void
    {
        $permissionId = $this->ensurePermission();
        $this->copyRoleAssignments('teilnehmer.update', self::PERMISSION);
        $this->assignToSystemRoles($permissionId);
        $this->backfillLegacyParentalConsent();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function ensurePermission(): int
    {
        $existingId = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        $values = [
            'berechtigungskategorie_id' => $this->participantCategoryId(),
            'beschreibung' => 'Erlaubt das Umschalten der Elterneinverstaendniserklaerung fuer schulbezogene Teilnehmer im aktiven Projekt.',
            'updated_at' => now(),
        ];

        if ($existingId) {
            DB::table('permissions')->where('id', $existingId)->update($values);
        } else {
            DB::table('permissions')->insert([
                'name' => self::PERMISSION,
                'guard_name' => 'web',
                'created_at' => now(),
                ...$values,
            ]);
        }

        return (int) DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');
    }

    private function copyRoleAssignments(string $sourcePermission, string $targetPermission): void
    {
        $sourceId = DB::table('permissions')
            ->where('name', $sourcePermission)
            ->where('guard_name', 'web')
            ->value('id');
        $targetId = DB::table('permissions')
            ->where('name', $targetPermission)
            ->where('guard_name', 'web')
            ->value('id');

        if (!$sourceId || !$targetId) {
            return;
        }

        foreach (DB::table('role_has_permissions')->where('permission_id', $sourceId)->pluck('role_id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $targetId,
                'role_id' => $roleId,
            ]);
        }
    }

    private function assignToSystemRoles(int $permissionId): void
    {
        foreach (DB::table('roles')->whereIn('name', ['Administrator', 'Developer'])->where('guard_name', 'web')->pluck('id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    private function participantCategoryId(): int
    {
        $categoryId = DB::table('berechtigungskategories')->where('name', 'Teilnehmer')->value('id');

        if ($categoryId) {
            return (int) $categoryId;
        }

        return (int) DB::table('berechtigungskategories')->insertGetId([
            'name' => 'Teilnehmer',
            'beschreibung' => 'Teilnehmerdaten, Teilnehmerlisten und teilnehmerbezogene Funktionen.',
        ]);
    }

    private function backfillLegacyParentalConsent(): void
    {
        if (!Schema::hasTable('legacy_record_snapshots')
            || !Schema::hasTable('legacy_id_mappings')
            || !Schema::hasTable('personen_ist_schuelers')) {
            return;
        }

        DB::table('legacy_record_snapshots')
            ->where('source', 'bop')
            ->where('source_table', 'teilnehmers')
            ->orderBy('id')
            ->chunkById(200, function ($snapshots): void {
                foreach ($snapshots as $snapshot) {
                    $payload = json_decode((string) $snapshot->payload, true);

                    if (!is_array($payload) || !$this->legacyBoolean($payload['eee'] ?? $payload['eltereklaerung'] ?? false)) {
                        continue;
                    }

                    $personId = $this->mappedTargetId('teilnehmers', (string) $snapshot->source_id, 'personens');
                    $schoolId = isset($payload['schule_id'])
                        ? $this->mappedTargetId('schules', (string) $payload['schule_id'], 'partners')
                        : null;

                    if (!$personId || !$schoolId) {
                        continue;
                    }

                    DB::table('personen_ist_schuelers')
                        ->where('person_id', $personId)
                        ->where('schule_id', $schoolId)
                        ->when($payload['schuljahr'] ?? null, fn ($query, $value) => $query->where('schuljahr', $value))
                        ->when($payload['teil'] ?? null, fn ($query, $value) => $query->where('teil', $value))
                        ->when($payload['klasse'] ?? null, fn ($query, $value) => $query->where('klasse', $value))
                        ->update([
                            'eee' => true,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function mappedTargetId(string $sourceTable, string $sourceId, string $targetTable): ?int
    {
        $targetId = DB::table('legacy_id_mappings')
            ->where('source', 'bop')
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('target_table', $targetTable)
            ->value('target_id');

        return $targetId ? (int) $targetId : null;
    }

    private function legacyBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'ja', 'yes', 'y', 'j'], true);
    }
};
