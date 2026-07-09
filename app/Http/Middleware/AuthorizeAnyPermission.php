<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeAnyPermission
{
    /**
     * Allow the request when the user has at least one of the listed permissions.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        foreach (array_filter($permissions) as $permission) {
            if ($user?->can($permission)) {
                return $next($request);
            }
        }

        throw new AuthorizationException();
    }
}
