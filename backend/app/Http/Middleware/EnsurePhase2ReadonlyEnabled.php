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
        // Keep a single master gate so tests/config overrides are deterministic.
        $phase2Enabled = (bool) config('intelligent.phase2.enabled');
        if (! $phase2Enabled) {
            abort(404);
        }

        return $next($request);
    }
}
