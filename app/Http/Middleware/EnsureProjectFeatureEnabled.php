<?php

namespace App\Http\Middleware;

use App\Services\Projects\ActiveProjectContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectFeatureEnabled
{
    public function __construct(private readonly ActiveProjectContext $projects)
    {
    }

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        $project = $user ? $this->projects->currentAvailableFor($user) : null;

        if (! $project || ! $project->featureEnabled($feature)) {
            abort(404);
        }

        return $next($request);
    }
}
