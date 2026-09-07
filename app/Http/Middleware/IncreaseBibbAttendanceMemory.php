<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IncreaseBibbAttendanceMemory
{
    public function handle(Request $request, Closure $next)
    {
        // Run before input normalization: decoding large signature payloads already
        // needs more memory, before route middleware or the controller can run.
        if ($request->is('export-anwesenheitsliste-pobo', 'export-anwesenheitsliste-pobo/*')) {
            $limit = trim((string) ini_get('memory_limit'));
            $bytes = (float) $limit * match (strtolower(substr($limit, -1))) {
                'g' => 1024 ** 3,
                'm' => 1024 ** 2,
                'k' => 1024,
                default => 1,
            };

            if ($limit !== '-1' && $bytes < 1280 * 1024 ** 2) {
                ini_set('memory_limit', '1280M');
            }
        }

        return $next($request);
    }
}
