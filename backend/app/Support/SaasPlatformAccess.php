<?php

namespace App\Support;

use App\Models\User;
use App\Support\Auth\PhoneNormalizer;

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

    /**
     * أرقام مضبوطة في الإعدادات، بعد توسيع صيغ المقارنة (مثل 05… و 9665…).
     *
     * @return array<string, true>
     */
    public static function platformAdminPhoneVariantLookup(): array
    {
        /** @var list<string> $raw */
        $raw = config('saas.platform_admin_phones', []);
        $lookup = [];
        foreach ($raw as $entry) {
            foreach (PhoneNormalizer::comparisonVariants((string) $entry) as $v) {
                if ($v !== '') {
                    $lookup[$v] = true;
                }
            }
        }

        return $lookup;
    }

    /**
     * مطابقة رقم المستخدم لقائمة مشغّلي المنصة عبر الجوال — فقط لحسابات بلا شركة (company_id = null).
     */
    public static function userMatchesPlatformAdminPhone(?User $user): bool
    {
        if ($user === null || $user->company_id !== null) {
            return false;
        }

        $lookup = self::platformAdminPhoneVariantLookup();
        if ($lookup === []) {
            return false;
        }

        // نماذج User غير المحفوظة (اختبارات) قد لا تملأ `original` — نقرأ من السمات عند الحاجة.
        $raw = (string) ($user->getRawOriginal('phone') ?: ($user->getAttributes()['phone'] ?? ''));
        if ($raw === '') {
            return false;
        }

        foreach (PhoneNormalizer::comparisonVariants($raw) as $v) {
            if (isset($lookup[$v])) {
                return true;
            }
        }

        return false;
    }

    /**
     * مشغّل منصة: إما حساب مُعلَّم {@see User::$is_platform_user} (بما في ذلك مرتبط بشركة للتجربة/التشغيل المختلط)،
     * أو حساب بلا شركة وبريد/جوال في قوائم الإعدادات.
     * مالك مستأجر ببريد مدرج في القائمة **بدون** is_platform_user ليس مشغّل منصة — لا وصول لـ /admin/companies.
     */
    public static function isPlatformOperator(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ((bool) ($user->is_platform_user ?? false)) {
            return true;
        }

        if ($user->company_id !== null) {
            return false;
        }

        $allowed = self::platformOperatorEmails();
        if ($allowed !== [] && is_string($user->email) && $user->email !== '') {
            if (in_array(strtolower(trim($user->email)), $allowed, true)) {
                return true;
            }
        }

        return self::userMatchesPlatformAdminPhone($user);
    }
}
