<?php

namespace Tests\Unit;

use Database\Seeders\UserSeeder;
use App\Support\RoutePermissionMap;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use SplFileInfo;
use Tests\TestCase;

class PermissionCatalogTest extends TestCase
{
    public function test_catalog_entries_are_unique_and_assigned_to_known_categories(): void
    {
        $catalog = $this->permissionCatalog();

        $keys = array_map(
            fn (array $permission) => $permission['guard_name'] . '|' . $permission['name'],
            $catalog
        );
        $duplicates = array_keys(array_filter(array_count_values($keys), fn (int $count) => $count > 1));

        $invalidCategories = array_values(array_filter(
            $catalog,
            fn (array $permission) => ! in_array((int) $permission['berechtigungskategorie_id'], range(1, 29), true)
        ));

        $this->assertSame([], $duplicates, 'Permission-Namen duerfen im Katalog nicht doppelt vorkommen.');
        $this->assertSame(
            [],
            array_map(
                fn (array $permission) => $permission['name'] . ' => Kategorie ' . $permission['berechtigungskategorie_id'],
                $invalidCategories
            ),
            'Jede Permission muss einer bekannten Berechtigungskategorie zugeordnet sein.'
        );
    }

    public function test_catalog_covers_permissions_used_by_routes_and_controllers(): void
    {
        $catalogNames = array_flip(array_column($this->permissionCatalog(), 'name'));
        $references = $this->permissionReferences();
        $missing = [];

        foreach ($references as $permission => $locations) {
            if (! isset($catalogNames[$permission])) {
                $missing[$permission] = array_values(array_unique($locations));
            }
        }

        ksort($missing);

        $this->assertSame(
            [],
            $missing,
            'Jede im Code verwendete Permission muss im UserSeeder::permissionCatalog() existieren.'
        );
    }

    public function test_route_permission_overrides_reference_catalog_permissions(): void
    {
        $catalogNames = array_flip(array_column($this->permissionCatalog(), 'name'));
        $missing = [];

        foreach (RoutePermissionMap::overrides() as $routeName => $permissions) {
            foreach ((array) $permissions as $permission) {
                if (! isset($catalogNames[$permission])) {
                    $missing[$routeName][] = $permission;
                }
            }
        }

        ksort($missing);

        $this->assertSame(
            [],
            $missing,
            'Jede Route-Permission-Ueberschreibung muss auf eine Permission im Katalog zeigen.'
        );
    }

    public function test_authenticated_named_routes_are_mapped_to_catalog_permissions(): void
    {
        $catalogNames = array_flip(array_column($this->permissionCatalog(), 'name'));
        $unmapped = [];

        foreach (Route::getRoutes() as $route) {
            $routeName = $route->getName();

            if (! $routeName || ! in_array('routePermission', $route->gatherMiddleware(), true)) {
                continue;
            }

            if ($this->routeAlreadyDefinesAuthorization($route->gatherMiddleware())) {
                continue;
            }

            $mappedPermissions = RoutePermissionMap::permissionsFor($routeName);
            $hasCatalogPermission = collect($mappedPermissions)
                ->contains(fn (string $permission) => isset($catalogNames[$permission]));

            if (! $hasCatalogPermission) {
                $unmapped[$routeName] = $route->methods()[0] . ' ' . $route->uri();
            }
        }

        ksort($unmapped);

        $this->assertSame(
            [],
            $unmapped,
            'Jede benannte auth-Route ohne eigene can()-Middleware muss auf mindestens eine Katalog-Permission zeigen.'
        );
    }

    public function test_production_code_does_not_contain_active_debug_stoppers(): void
    {
        $debugCalls = [];

        foreach ($this->phpFiles([
            base_path('app'),
            base_path('routes'),
            base_path('database/seeders'),
        ]) as $file) {
            foreach ($this->activeFunctionCalls($file, ['dd', 'dump', 'ray']) as $call) {
                $debugCalls[] = $this->relativePath($file) . ':' . $call['line'] . ' ' . $call['name'] . '()';
            }
        }

        sort($debugCalls);

        $this->assertSame(
            [],
            $debugCalls,
            'Aktive Debug-Stopps duerfen nicht in produktivem Code bleiben.'
        );
    }

    private function permissionCatalog(): array
    {
        $seeder = app(UserSeeder::class);
        $method = new ReflectionMethod($seeder, 'permissionCatalog');
        $method->setAccessible(true);

        return $method->invoke($seeder);
    }

    private function permissionReferences(): array
    {
        $references = [];

        foreach ($this->routePermissionReferences() as $permission => $locations) {
            foreach ($locations as $location) {
                $this->addPermissionReference($references, $permission, $location);
            }
        }

        foreach ($this->sourcePermissionReferences() as $permission => $locations) {
            foreach ($locations as $location) {
                $this->addPermissionReference($references, $permission, $location);
            }
        }

        ksort($references);

        return $references;
    }

    private function routePermissionReferences(): array
    {
        $references = [];

        foreach (Route::getRoutes() as $route) {
            $location = 'route ' . ($route->getName() ?: $route->uri());

            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware)) {
                    continue;
                }

                if (str_starts_with($middleware, 'can:')) {
                    $permission = explode(',', substr($middleware, strlen('can:')))[0] ?? '';
                    $this->addPermissionReference($references, $permission, $location);
                }

                if (str_starts_with($middleware, 'canAnyPermission:')) {
                    $permissions = explode(',', substr($middleware, strlen('canAnyPermission:')));

                    foreach ($permissions as $permission) {
                        $this->addPermissionReference($references, $permission, $location);
                    }
                }
            }
        }

        return $references;
    }

    private function sourcePermissionReferences(): array
    {
        $references = [];

        foreach ($this->phpFiles([base_path('app')]) as $file) {
            $source = $this->sourceWithoutComments($file);
            $location = $this->relativePath($file);

            foreach ([
                '/->can\(\s*([\'"])([^\'"]+)\1\s*\)/u',
                '/::permission\(\s*([\'"])([^\'"]+)\1\s*[,)]/u',
            ] as $pattern) {
                if (! preg_match_all($pattern, $source, $matches)) {
                    continue;
                }

                foreach ($matches[2] as $permission) {
                    $this->addPermissionReference($references, $permission, $location);
                }
            }

            if (preg_match_all('/authorizeAny\s*\((.*?)\)\s*;/us', $source, $matches)) {
                foreach ($matches[1] as $arguments) {
                    if (! preg_match_all('/([\'"])([^\'"]+)\1/u', $arguments, $permissionMatches)) {
                        continue;
                    }

                    foreach ($permissionMatches[2] as $permission) {
                        $this->addPermissionReference($references, $permission, $location);
                    }
                }
            }
        }

        return $references;
    }

    private function addPermissionReference(array &$references, string $permission, string $location): void
    {
        $permission = trim($permission);

        if (! $this->shouldCheckPermission($permission)) {
            return;
        }

        $references[$permission][] = $location;
    }

    /**
     * @param array<int, mixed> $middleware
     */
    private function routeAlreadyDefinesAuthorization(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if (is_string($entry) && (str_starts_with($entry, 'can:') || str_starts_with($entry, 'canAnyPermission:'))) {
                return true;
            }
        }

        return false;
    }

    private function shouldCheckPermission(string $permission): bool
    {
        return str_contains($permission, '.')
            || in_array($permission, ['index-ausleihende'], true);
    }

    /**
     * @return array<int, array{name: string, line: int}>
     */
    private function activeFunctionCalls(SplFileInfo $file, array $functionNames): array
    {
        $calls = [];
        $tokens = token_get_all(file_get_contents($file->getPathname()));
        $functionNames = array_flip($functionNames);

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || $token[0] !== T_STRING) {
                continue;
            }

            $name = strtolower($token[1]);

            if (! isset($functionNames[$name])) {
                continue;
            }

            $previous = $this->previousSignificantToken($tokens, $index);
            $next = $this->nextSignificantToken($tokens, $index);

            if ($next !== '(' || $previous === T_OBJECT_OPERATOR || $previous === T_DOUBLE_COLON) {
                continue;
            }

            $calls[] = [
                'name' => $name,
                'line' => $token[2],
            ];
        }

        return $calls;
    }

    private function previousSignificantToken(array $tokens, int $index): int|string|null
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) ? $token[0] : $token;
        }

        return null;
    }

    private function nextSignificantToken(array $tokens, int $index): int|string|null
    {
        $count = count($tokens);

        for ($i = $index + 1; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) ? $token[0] : $token;
        }

        return null;
    }

    private function sourceWithoutComments(SplFileInfo $file): string
    {
        $source = '';

        foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $source .= is_array($token) ? $token[1] : $token;
        }

        return $source;
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function phpFiles(array $directories): array
    {
        $files = [];

        foreach ($directories as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    private function relativePath(SplFileInfo $file): string
    {
        return ltrim(str_replace(base_path(), '', $file->getPathname()), DIRECTORY_SEPARATOR);
    }
}
