<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $user = User::find(Auth::user()->id);

        if ($user) {
            // Setze Rollen und Berechtigungen in die Shared Data von Inertia
            Inertia::share([
                'user' => $request->user()->load('projekte'),  // Projekte des Users laden
            ]);
        }
        return $next($request);
    }
}
