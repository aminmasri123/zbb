<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeProfileAccess
{
    private const ROUTE_NAMES = [
        'profile.show',
        'user-profile-information.update',
        'user-password.update',
        'current-user-photo.destroy',
        'other-browser-sessions.destroy',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
        'current-user.destroy',
        'profile.unterweisung-signature.update',
        'profile.unterweisung-signature.destroy',
    ];

    /**
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->route()?->getName(), self::ROUTE_NAMES, true)) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && ! $user->can('user.profil')) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
