<?php

namespace App\Http\Middleware;

use App\Actions\Auth\ResolveLoginEligibilityAction;
use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use App\Support\TenantBusinessFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures authenticated requests carry a valid tenant (company) context.
 * Same behaviour as the legacy tenant middleware alias; kept as the canonical implementation.
 *
 * Platform operators (no company, or working in delegate mode) may send
 * `X-On-Behalf-Company-Id` or `on_behalf_company_id` to pin the effective tenant
 * to a platform execution partner company (same rules as work order intake).
 */
class GlobalTenantGuardMiddleware
{
    public const ON_BEHALF_ATTR = 'platform_on_behalf_delegation';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $onBehalfId = $this->readOnBehalfCompanyId($request);
        $wantsDelegate = $onBehalfId !== null
            && (bool) $user->is_platform_user
            && ($user->hasPermission('platform.companies.read') || $user->hasPermission('platform.ops.read'));

        if ($wantsDelegate) {
            $delegateResponse = $this->bindDelegatedExecutionPartnerTenant($request, $user, $onBehalfId);
            if ($delegateResponse !== null) {
                return $delegateResponse;
            }

            return $next($request);
        }

        if (! $user->company_id) {
            return response()->json([
                'message'  => 'Tenant context not found.',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $company = $user->company;

        if (! $company) {
            return response()->json([
                'message'  => 'Company context is invalid for this account.',
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

    private function readOnBehalfCompanyId(Request $request): ?int
    {
        $header = trim((string) ($request->header('X-On-Behalf-Company-Id') ?? ''));
        if ($header !== '') {
            $cid = (int) $header;
            if ($cid > 0) {
                return $cid;
            }
        }
        $raw = $request->input('on_behalf_company_id');
        if ($raw !== null && $raw !== '' && (int) $raw > 0) {
            return (int) $raw;
        }

        return null;
    }

    /**
     * @return Response|null null when tenant was bound successfully
     */
    private function bindDelegatedExecutionPartnerTenant(Request $request, User $user, int $companyId): ?Response
    {
        $company = Company::query()->find($companyId);
        if ($company === null) {
            return response()->json([
                'message'  => 'الشركة المحدّدة غير موجودة.',
                'trace_id' => app('trace_id'),
            ], 404);
        }

        if (! TenantBusinessFeatures::isPlatformExecutionPartnerTenant($companyId)) {
            return response()->json([
                'message'  => 'الشركة المختارة ليست مُسجّلة كشريك تنفيذ منصّة.',
                'trace_id' => app('trace_id'),
            ], 422);
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

        app()->instance('tenant_company_id', $companyId);
        $request->attributes->set(self::ON_BEHALF_ATTR, true);

        return null;
    }
}
