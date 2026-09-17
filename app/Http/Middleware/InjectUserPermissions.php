<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InjectUserPermissions
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
            $permissions = $user->getAllPermissions()->pluck('name');
            if (\Illuminate\Support\Facades\Schema::hasTable('purchase_rules')
                && (app(\App\Services\Purchasing\PurchaseWorkflow::class)->isDirector($user)
                    || $user->can('materialanforderung.settings.manage'))) {
                // Navigation only. Controllers still authorize every operation and record.
                $permissions->push('materialanforderung.index');
            }

            $permissionAliases = $permissions->flatMap(function ($permission) {
                if (str_starts_with($permission, 'raeumlichkeiten.')) {
                    return [$permission, str_replace('raeumlichkeiten.', 'räumlichkeiten.', $permission)];
                }

                if (str_starts_with($permission, 'räumlichkeiten.')) {
                    return [$permission, str_replace('räumlichkeiten.', 'raeumlichkeiten.', $permission)];
                }

                return [$permission];
            })->unique()->values();

            // Setze Rollen und Berechtigungen in die Shared Data von Inertia
            Inertia::share([
                'roles' => $user->getRoleNames(), // Rollen des Benutzers
                'permissions' => $permissionAliases, // Berechtigungen des Benutzers
            ]);
        }

        return $next($request);
    }
}
