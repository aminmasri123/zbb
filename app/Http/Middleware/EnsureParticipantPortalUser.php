<?php

namespace App\Http\Middleware;

use App\Models\ProjektHasPersonen;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureParticipantPortalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->person?->typ === 'teilnehmer' && $request->user()->person->aktiv, 403);

        $hasEnabledPortalProject = ProjektHasPersonen::query()
            ->where('personen_id', $request->user()->person_id)
            ->with('projekt:id,feature_settings')
            ->get()
            ->contains(fn (ProjektHasPersonen $participation) => $participation->projekt?->featureEnabled('participant_portal'));

        abort_unless($hasEnabledPortalProject, 403, 'Das Teilnehmerportal ist für Ihre Projekte nicht aktiviert.');

        return $next($request);
    }
}
