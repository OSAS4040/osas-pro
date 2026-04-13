<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Enforces plan limits from the latest subscription row for the tenant.
 */
final class SubscriptionQuota
{
    public static function assertCanCreateBranch(int $companyId): void
    {
        $sub = self::latestSubscription($companyId);
        if ($sub === null) {
            self::deny(422, 'لا يوجد اشتراك مرتبط بهذه الشركة.');
        }

        $max = max(1, (int) ($sub->max_branches ?? 1));
        $count = Branch::query()->where('company_id', $companyId)->count();

        if ($count >= $max) {
            self::deny(
                422,
                "بلوغ الحد الأقصى للفروع في الباقة الحالية ({$max}). قم بترقية الاشتراك أو دمج الفروع غير المستخدمة."
            );
        }
    }

    public static function assertCanCreateUser(int $companyId): void
    {
        $sub = self::latestSubscription($companyId);
        if ($sub === null) {
            self::deny(422, 'لا يوجد اشتراك مرتبط بهذه الشركة.');
        }

        $max = max(1, (int) ($sub->max_users ?? 5));
        $count = User::query()->where('company_id', $companyId)->count();

        if ($count >= $max) {
            self::deny(
                422,
                "بلوغ الحد الأقصى للمستخدمين في الباقة الحالية ({$max}). قم بترقية الاشتراك."
            );
        }
    }

    private static function latestSubscription(int $companyId): ?Subscription
    {
        return Subscription::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->first();
    }

    private static function deny(int $code, string $message): void
    {
        throw new HttpResponseException(
            response()->json([
                'message'  => $message,
                'trace_id' => app('trace_id'),
            ], $code)
        );
    }
}
