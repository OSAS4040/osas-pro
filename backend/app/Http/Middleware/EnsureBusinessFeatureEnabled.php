<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\TenantBusinessFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يتطلب تفعيل مفتاح في مصفوفة ملف النشاط (نوع المنشأة + التخصيص).
 * مثال: business.feature:org_structure
 */
class EnsureBusinessFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.', 'trace_id' => app('trace_id')], 401);
        }

        $company = Company::query()->find((int) $user->company_id);
        if ($company === null || ! TenantBusinessFeatures::isEnabled($company, $featureKey)) {
            return response()->json([
                'message'  => 'هذه الإمكانية غير مفعّلة لملف نشاط منشأتك. فعّلها من الإعدادات ← نشاط المنشأة، أو غيّر نوع النشاط المناسب.',
                'code'     => 'business_feature_disabled',
                'feature'  => $featureKey,
                'trace_id' => app('trace_id'),
            ], 403);
        }

        return $next($request);
    }
}
