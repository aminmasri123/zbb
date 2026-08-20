<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeStoredPermission
{
    /**
     * Authorize against the current database assignments instead of the
     * long-lived Spatie permission cache.
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()?->hasStoredPermission($permission)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
