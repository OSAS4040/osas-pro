<?php

namespace App\Http\Middleware;

use App\Models\Branch;
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

        if ($requestedBranchId && $user->company_id) {
            $branchOk = Branch::query()
                ->withoutGlobalScope('tenant')
                ->where('id', (int) $requestedBranchId)
                ->where('company_id', (int) $user->company_id)
                ->whereNull('deleted_at')
                ->exists();
            if (! $branchOk) {
                return response()->json([
                    'message'  => 'Invalid branch context for your company.',
                    'trace_id' => app('trace_id'),
                ], 403);
            }
        }

        if ($requestedBranchId && $user->branch_id && (int) $requestedBranchId !== (int) $user->branch_id) {
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
