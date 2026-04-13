<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use App\Support\SubscriptionAccessEvaluator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = (int) app('tenant_company_id');

        $block = SubscriptionAccessEvaluator::evaluate($companyId, $request, false);
        if ($block !== null) {
            return response()->json([
                'message'  => $block['message'],
                'trace_id' => app('trace_id'),
            ], $block['code']);
        }

        $resolvedRow = SubscriptionAccessEvaluator::resolvedSubscriptionRow();
        $subscription = null;

        if ($resolvedRow !== null && isset($resolvedRow->id)) {
            $subscription = Subscription::withoutGlobalScopes()->find($resolvedRow->id);
        }

        if ($subscription === null) {
            $subscription = Subscription::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->orderByDesc('id')
                ->first();
        }

        if ($subscription) {
            app()->instance('subscription', $subscription);
        }

        return $next($request);
    }
}
