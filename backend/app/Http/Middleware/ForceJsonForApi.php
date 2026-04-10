<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures /api/* requests are always treated as JSON clients so Laravel never
 * redirects to web login or returns HTML error pages for missing Accept headers.
 */
class ForceJsonForApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api') || $request->is('api/*')) {
            $request->headers->set('Accept', 'application/json', true);
        }

        return $next($request);
    }
}
