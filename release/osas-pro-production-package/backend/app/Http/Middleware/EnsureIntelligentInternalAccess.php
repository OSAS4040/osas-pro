<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects internal intelligence inspection APIs (owner/manager only, feature-flagged).
 */
class EnsureIntelligentInternalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('intelligent.internal_dashboard.enabled')) {
            abort(404);
        }

        $user = $request->user();
        if (! $user || ! $user->role->isAdmin()) {
            abort(403, 'This inspection endpoint is restricted to administrators.');
        }

        return $next($request);
    }
}
