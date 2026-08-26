<?php

namespace App\Http\Middleware;

use App\Models\ProjektHasPersonen;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureParticipantPortalFeatureEnabled
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $personId = $request->user()?->person_id;
        abort_unless($personId && $features !== [], 404);

        $enabled = ProjektHasPersonen::query()
            ->where('personen_id', $personId)
            ->where('status', 'aktiv')
            ->with('projekt:id,feature_settings,portal_feature_settings')
            ->get()
            ->contains(function (ProjektHasPersonen $participation) use ($features): bool {
                $project = $participation->projekt;

                return (bool) $project?->featureEnabled('participant_portal')
                    && collect($features)->contains(fn (string $feature) => $project->portalFeatureEnabled($feature));
            });

        abort_unless($enabled, 404);

        return $next($request);
    }
}
