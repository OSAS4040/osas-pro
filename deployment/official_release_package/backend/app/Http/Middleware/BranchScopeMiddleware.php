<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchScopeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $requestedBranchId = $request->header('X-Branch-Id')
            ?? $request->query('branch_id')
            ?? $user->branch_id;

        if ($requestedBranchId && $user->branch_id && (int) $requestedBranchId !== $user->branch_id) {
            $hasCrossBranchAccess = $user->hasPermission('cross_branch_access');

            if (! $hasCrossBranchAccess) {
                return response()->json([
                    'message'  => 'Cross-branch access is not permitted for your account.',
                    'trace_id' => app('trace_id'),
                ], 403);
            }
        }

        $effectiveBranchId = $requestedBranchId ?? $user->branch_id;

        if ($effectiveBranchId) {
            app()->instance('tenant_branch_id', (int) $effectiveBranchId);
        }

        return $next($request);
    }
}
