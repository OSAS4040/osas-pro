<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 2 — gates read-only intelligence HTTP surface (additive, default off).
 */
class EnsurePhase2ReadonlyEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $readModels = (bool) config('intelligent.read_models.enabled');
        $legacyP2   = (bool) config('intelligent.phase2.enabled');

        if (! $readModels && ! $legacyP2) {
            abort(404);
        }

        return $next($request);
    }
}
