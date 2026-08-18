<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdleSessionTimeout
{
    private const LAST_ACTIVITY_KEY = 'auth_last_user_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $now = now()->timestamp;
        $timeoutSeconds = max(60, (int) config('session.lifetime', 30) * 60);
        $lastActivity = (int) $request->session()->get(self::LAST_ACTIVITY_KEY, 0);

        if ($lastActivity > 0 && ($now - $lastActivity) >= $timeoutSeconds) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Ihre Sitzung ist wegen Inaktivität abgelaufen.',
                    'code' => 'session_expired',
                ], 401);
            }

            return redirect('/');
        }

        // Nur diese ausdrückliche Route wird durch echte Browseraktivität
        // aufgerufen. Statusabfragen und Hintergrund-Polling verlängern nicht.
        if ($lastActivity === 0 || $request->routeIs('system.session-activity')) {
            $request->session()->put(self::LAST_ACTIVITY_KEY, $now);
        }

        return $next($request);
    }
}
