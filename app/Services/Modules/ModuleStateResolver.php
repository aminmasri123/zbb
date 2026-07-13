<?php

namespace App\Services\Modules;

use App\Models\ModuleAssignment;
use App\Models\SystemModule;
use Illuminate\Support\Facades\Schema;

class ModuleStateResolver
{
    /**
     * Untergeordnete Module duerfen nur laufen, wenn ihr fachliches
     * Hauptmodul ebenfalls aktiv ist.
     */
    private const DEPENDENCIES = [
        'participant_portal' => ['participant_management'],
    ];

    public function enabled(string $moduleKey, ?int $locationId = null): bool
    {
        if (!Schema::hasTable('modules') || !Schema::hasTable('module_assignments')) {
            return true;
        }

        $module = SystemModule::query()->where('key', $moduleKey)->first();

        if (!$module || $module->status !== 'active') {
            return false;
        }

        if (!$module->is_enforced) {
            return $this->dependenciesEnabled($moduleKey, $locationId);
        }

        if ($locationId && $module->supports_location_scope) {
            $locationAssignment = $module->assignments()
                ->where('scope_key', self::locationScopeKey($locationId))
                ->first();

            if ($locationAssignment) {
                return $locationAssignment->enabled
                    && $this->dependenciesEnabled($moduleKey, $locationId);
            }
        }

        $globalAssignment = $module->assignments()
            ->where('scope_key', self::globalScopeKey())
            ->first();

        return ($globalAssignment?->enabled ?? $module->default_enabled)
            && $this->dependenciesEnabled($moduleKey, $locationId);
    }

    public function effectiveStates(?int $locationId = null): array
    {
        if (!Schema::hasTable('modules')) {
            return [];
        }

        return SystemModule::query()
            ->orderBy('key')
            ->pluck('key')
            ->mapWithKeys(fn (string $key) => [$key => $this->enabled($key, $locationId)])
            ->all();
    }

    public function available(string $moduleKey): bool
    {
        if ($this->enabled($moduleKey)) {
            return true;
        }

        if (!Schema::hasTable('modules') || !Schema::hasTable('module_assignments')) {
            return true;
        }

        $module = SystemModule::query()->where('key', $moduleKey)->first();

        if (!$module || $module->status !== 'active' || !$module->supports_location_scope) {
            return false;
        }

        $available = ModuleAssignment::query()
            ->where('module_id', $module->id)
            ->whereNotNull('location_id')
            ->where('enabled', true)
            ->exists();

        return $available && $this->dependenciesAvailable($moduleKey);
    }

    public function availableStates(): array
    {
        if (!Schema::hasTable('modules')) {
            return [];
        }

        return SystemModule::query()
            ->orderBy('key')
            ->pluck('key')
            ->mapWithKeys(fn (string $key) => [$key => $this->available($key)])
            ->all();
    }

    public function set(SystemModule $module, bool $enabled, ?int $locationId, ?int $userId): ModuleAssignment
    {
        if ($locationId && !$module->supports_location_scope) {
            throw new \InvalidArgumentException("Module {$module->key} does not support location assignments.");
        }

        return ModuleAssignment::query()->updateOrCreate(
            [
                'module_id' => $module->id,
                'scope_key' => $locationId ? self::locationScopeKey($locationId) : self::globalScopeKey(),
            ],
            [
                'location_id' => $locationId,
                'enabled' => $enabled,
                'activated_by_user_id' => $userId,
            ]
        );
    }

    public static function globalScopeKey(): string
    {
        return 'global';
    }

    public static function locationScopeKey(int $locationId): string
    {
        return 'location:' . $locationId;
    }

    private function dependenciesEnabled(string $moduleKey, ?int $locationId): bool
    {
        foreach (self::DEPENDENCIES[$moduleKey] ?? [] as $dependency) {
            if (!$this->enabled($dependency, $locationId)) {
                return false;
            }
        }

        return true;
    }

    private function dependenciesAvailable(string $moduleKey): bool
    {
        foreach (self::DEPENDENCIES[$moduleKey] ?? [] as $dependency) {
            if (!$this->available($dependency)) {
                return false;
            }
        }

        return true;
    }
}
