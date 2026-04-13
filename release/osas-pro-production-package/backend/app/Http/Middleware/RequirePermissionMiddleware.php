<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message'  => 'Unauthenticated.',
                'trace_id' => app('trace_id'),
            ], 401);
        }

        foreach ($permissions as $permission) {
            if (! $user->hasPermission($permission)) {
                return response()->json([
                    'message'    => "Permission denied: [{$permission}].",
                    'trace_id'   => app('trace_id'),
                    'permission' => $permission,
                ], 403);
            }
        }

        return $next($request);
    }
}
