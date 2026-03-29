<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = app('tenant_company_id');

        $subscription = Subscription::where('company_id', $companyId)
            ->latest()
            ->first();

        if (! $subscription) {
            return $this->blocked('No active subscription found.');
        }

        $status = $subscription->status;

        if ($status === SubscriptionStatus::Suspended) {
            return $this->blocked('Subscription suspended. Please renew to continue.');
        }

        if ($status === SubscriptionStatus::GracePeriod) {
            if ($this->isWriteOperation($request)) {
                return $this->blocked(
                    'Subscription in grace period. Read-only access only.',
                    423
                );
            }
        }

        app()->instance('subscription', $subscription);

        return $next($request);
    }

    private function isWriteOperation(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    private function blocked(string $message, int $status = 402): Response
    {
        return response()->json([
            'message'  => $message,
            'trace_id' => app('trace_id'),
        ], $status);
    }
}
