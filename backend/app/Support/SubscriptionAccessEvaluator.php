<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Real-time subscription access (no cached subscription state).
 * Used by SubscriptionMiddleware and auth login — does not touch financial domains.
 */
final class SubscriptionAccessEvaluator
{
    private const RESOLVED_SUBSCRIPTION_APP_KEY = 'resolved_subscription_row';
    private const SUBSCRIPTIONS_TABLE_EXISTS_KEY = 'subscriptions_table_exists';

    /**
     * @return array{code: int, message: string}|null
     */
    public static function evaluate(int $companyId, Request $request, bool $isLoginAttempt): ?array
    {
        if (! self::subscriptionsTableExists()) {
            self::logDecision($companyId, 'block', 'no_subscriptions_table', 402, $isLoginAttempt, '');

            return ['code' => 402, 'message' => 'No active subscription found.'];
        }

        $row = self::resolveSubscriptionRow($companyId);

        if (! $row) {
            self::logDecision($companyId, 'block', 'no_subscription_row', 402, $isLoginAttempt, '');

            return ['code' => 402, 'message' => 'No active subscription found.'];
        }

        $status = (string) ($row->status ?? '');

        if ($status === 'suspended' || $status === 'expired') {
            self::logDecision($companyId, 'block', 'suspended', 402, $isLoginAttempt, $status);

            return ['code' => 402, 'message' => 'Subscription suspended. Please renew to continue.'];
        }

        $endsAt      = self::resolveEndsAt($row);
        $graceEndsAt = self::resolveGraceEndsAt($row);

        $now = now();

        $expiredByTime = $endsAt !== null && $endsAt->lt($now);

        if (! $expiredByTime) {
            self::logDecision($companyId, 'allow', 'within_billing_period', 200, $isLoginAttempt, $status);

            return null;
        }

        $inGrace = $graceEndsAt !== null && $now->lt($graceEndsAt);

        if ($inGrace || $status === 'past_due' || $status === 'grace_period') {
            if ($isLoginAttempt || self::isReadOperation($request)) {
                self::logDecision($companyId, 'allow', 'grace_read_or_login', 200, $isLoginAttempt, $status);

                return null;
            }

            self::logDecision($companyId, 'block', 'grace_write_forbidden', 423, $isLoginAttempt, $status);

            return ['code' => 423, 'message' => 'Subscription in grace period. Read-only access only.'];
        }

        self::logDecision($companyId, 'block', 'expired_no_grace', 402, $isLoginAttempt, $status);

        return ['code' => 402, 'message' => 'Subscription expired. Please renew to continue.'];
    }

    public static function resolvedSubscriptionRow(): ?object
    {
        return app()->bound(self::RESOLVED_SUBSCRIPTION_APP_KEY)
            ? app(self::RESOLVED_SUBSCRIPTION_APP_KEY)
            : null;
    }

    private static function subscriptionsTableExists(): bool
    {
        if (app()->bound(self::SUBSCRIPTIONS_TABLE_EXISTS_KEY)) {
            return (bool) app(self::SUBSCRIPTIONS_TABLE_EXISTS_KEY);
        }

        $exists = Schema::hasTable('subscriptions');
        app()->instance(self::SUBSCRIPTIONS_TABLE_EXISTS_KEY, $exists);

        return $exists;
    }

    private static function resolveSubscriptionRow(int $companyId): ?object
    {
        if (app()->bound(self::RESOLVED_SUBSCRIPTION_APP_KEY)) {
            return app(self::RESOLVED_SUBSCRIPTION_APP_KEY);
        }

        $row = DB::table('subscriptions')
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->first();

        app()->instance(self::RESOLVED_SUBSCRIPTION_APP_KEY, $row);

        return $row;
    }

    private static function resolveEndsAt(object $row): ?Carbon
    {
        if (! empty($row->ends_at)) {
            return Carbon::parse($row->ends_at);
        }
        if (property_exists($row, 'current_period_end') && ! empty($row->current_period_end)) {
            return Carbon::parse($row->current_period_end);
        }

        return null;
    }

    private static function resolveGraceEndsAt(object $row): ?Carbon
    {
        if (! empty($row->grace_ends_at)) {
            return Carbon::parse($row->grace_ends_at);
        }

        return null;
    }

    private static function isReadOperation(Request $request): bool
    {
        return in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private static function logDecision(
        int $companyId,
        string $decision,
        string $reason,
        int $httpCode,
        bool $isLogin,
        string $status,
    ): void {
        if ($decision === 'allow') {
            return;
        }

        Log::info('subscription.access', [
            'company_id' => $companyId,
            'decision'   => $decision,
            'reason'     => $reason,
            'http_code'  => $httpCode,
            'auth_login' => $isLogin,
            'status'     => $status,
            'trace_id'   => app()->bound('trace_id') ? app('trace_id') : null,
        ]);
    }
}
