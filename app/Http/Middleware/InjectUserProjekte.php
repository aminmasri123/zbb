<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\Projects\ActiveProjectContext;
use Inertia\Inertia;
use Illuminate\Http\Request;

class InjectUserProjekte
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {

        $user = $request->user();

        if ($user) {
            app(ActiveProjectContext::class)->currentFor($user);

            // Setze Rollen und Berechtigungen in die Shared Data von Inertia
            Inertia::share([
                'user' => $user->load('projekte'),
            ]);
        }
        return $next($request);
    }
}
