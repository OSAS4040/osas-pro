<?php

namespace App\Http\Middleware;

use App\Actions\Auth\ResolveLoginEligibilityAction;
use App\Enums\CompanyStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures authenticated requests carry a valid tenant (company) context.
 * Same behaviour as the legacy tenant middleware alias; kept as the canonical implementation.
 */
class GlobalTenantGuardMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->company_id) {
            return response()->json([
                'message'  => 'Tenant context not found.',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $company = $user->company;

        if (! $company) {
            return response()->json([
                'message'  => 'Company not found for this user. The account may be orphaned — run seeders or fix company_id.',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        if ($company->status === CompanyStatus::Suspended) {
            return response()->json([
                'message'  => 'Company account is suspended.',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $eligibility = app(ResolveLoginEligibilityAction::class)($user);
        if (! $eligibility->allowed) {
            return response()->json($eligibility->toForbiddenResponseBody('ar'), 403);
        }

        app()->instance('tenant_company_id', $user->company_id);
        app()->instance('tenant_branch_id', $user->branch_id);

        return $next($request);
    }
}
