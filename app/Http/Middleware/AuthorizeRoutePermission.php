<?php

namespace App\Http\Middleware;

use App\Support\RoutePermissionMap;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeRoutePermission
{
    /**
     * Protect named routes when their route name maps to an existing permission.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if (! $route || $this->routeAlreadyDefinesAuthorization($route->gatherMiddleware())) {
            return $next($request);
        }

        $permissions = $this->existingPermissions(
            RoutePermissionMap::permissionsFor($route->getName())
        );

        if ($permissions === []) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            if ($request->user()?->can($permission)) {
                return $next($request);
            }
        }

        throw new AuthorizationException();
    }

    /**
     * @param array<int, mixed> $middleware
     */
    private function routeAlreadyDefinesAuthorization(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            if (str_starts_with($entry, 'can:') || str_starts_with($entry, 'canAnyPermission:')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $permissions
     * @return array<int, string>
     */
    private function existingPermissions(array $permissions): array
    {
        if ($permissions === []) {
            return [];
        }

        return Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $permissions)
            ->pluck('name')
            ->all();
    }
}
