<?php

namespace App\Support;

use App\Models\User;

final class SaasPlatformAccess
{
    /**
     * @return list<string>
     */
    public static function platformOperatorEmails(): array
    {
        /** @var list<string> $emails */
        $emails = config('saas.platform_admin_emails', []);

        return array_values(array_filter(array_map(
            static fn (string $e): string => strtolower(trim($e)),
            $emails
        )));
    }

    public static function isPlatformOperator(?User $user): bool
    {
        if (! $user || ! is_string($user->email) || $user->email === '') {
            return false;
        }

        $allowed = self::platformOperatorEmails();
        if ($allowed === []) {
            return false;
        }

        return in_array(strtolower($user->email), $allowed, true);
    }

    /**
     * تعديل كتالوج الباقات العالمي (جدول plans).
     */
    public static function canManageGlobalPlanCatalog(?User $user): bool
    {
        if (config('saas.allow_tenant_plan_catalog_edit', false)) {
            return true;
        }

        return self::isPlatformOperator($user);
    }
}
