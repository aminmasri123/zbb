<?php

namespace App\Http\Middleware;

use App\Models\NotificationRule;
use App\Notifications\ConfiguredEventNotification;
use App\Services\NotificationRecipientService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class DispatchConfiguredRouteNotification
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $routeName = $request->route()?->getName();

        if (
            ! $routeName
            || ! $this->isSuccessfulMutation($request, $response)
            || ! NotificationRule::isConfiguredEvent($routeName)
        ) {
            return $response;
        }

        $recipients = app(NotificationRecipientService::class)->forEvent($routeName, [
            'actor' => $request->user(),
            'creator_user' => $request->user(),
            'project_id' => $this->projectIdFromRequest($request),
        ]);

        if ($recipients->isEmpty()) {
            return $response;
        }

        Notification::send(
            $recipients,
            new ConfiguredEventNotification([
                'event_key' => $routeName,
                'message' => NotificationRule::labelForEvent($routeName) . '.',
                'link' => $this->indexLinkForEvent($routeName),
                'id' => $this->firstRouteParameterValue($request),
                'typ' => NotificationRule::moduleLabelForEvent($routeName),
            ])
        );

        return $response;
    }

    private function isSuccessfulMutation(Request $request, Response $response): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && $response->getStatusCode() >= 200
            && $response->getStatusCode() < 400;
    }

    private function projectIdFromRequest(Request $request): ?int
    {
        return $request->integer('projekt_id')
            ?: $request->integer('projekt')
            ?: $request->integer('project_id')
            ?: $request->integer('current_team_id')
            ?: null;
    }

    private function firstRouteParameterValue(Request $request): mixed
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            return is_object($parameter) && isset($parameter->id) ? $parameter->id : $parameter;
        }

        return null;
    }

    private function indexLinkForEvent(string $eventKey): ?string
    {
        $segments = explode('.', $eventKey);

        while (count($segments) > 0) {
            $candidate = implode('.', $segments) . '.index';

            if (Route::has($candidate)) {
                return route($candidate);
            }

            array_pop($segments);
        }

        return null;
    }
}
