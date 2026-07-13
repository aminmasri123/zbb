<?php

namespace App\Http\Middleware;

use App\Services\Modules\ModuleStateResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(private readonly ModuleStateResolver $modules)
    {
    }

    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $locationId = $this->locationId($request);

        $enabled = $locationId
            ? $this->modules->enabled($moduleKey, $locationId)
            : $this->modules->available($moduleKey);

        abort_unless($enabled, 404);

        return $next($request);
    }

    private function locationId(Request $request): ?int
    {
        $inputLocationId = $request->integer('standort_id') ?: $request->integer('location_id');

        if ($inputLocationId) {
            return $inputLocationId;
        }

        $roomId = $request->integer('raum_id');
        if ($roomId) {
            $locationId = \App\Models\Raeume::query()->whereKey($roomId)->value('standort_id');
            if ($locationId) {
                return (int) $locationId;
            }
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (is_object($parameter) && isset($parameter->standort_id) && $parameter->standort_id) {
                return (int) $parameter->standort_id;
            }

            if (is_object($parameter) && method_exists($parameter, 'raum')) {
                $locationId = $parameter->raum()->value('standort_id');
                if ($locationId) {
                    return (int) $locationId;
                }
            }
        }

        if (str_starts_with((string) $request->route()?->getName(), 'raeumlichkeiten.')) {
            $roomRouteId = $request->route('id');
            if (is_numeric($roomRouteId)) {
                $locationId = \App\Models\Raeume::query()->whereKey((int) $roomRouteId)->value('standort_id');
                if ($locationId) {
                    return (int) $locationId;
                }
            }
        }

        return null;
    }
}
